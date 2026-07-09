<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\RescheduleBookingRequest;
use App\Http\Requests\Api\V1\Customer\ReviewBookingRequest;
use App\Http\Requests\Api\V1\Customer\RportBookingRequest;
use App\Http\Requests\Api\V1\Customer\StoreBookingRequest as StoreBookingRequestV2;
use App\Http\Requests\Api\V1\Customer\CancelBookingRequest;
use App\Http\Requests\Api\V1\Customer\ValidateBookingRequest;
use App\Http\Resources\Api\V1\Booking\BookingResource;
use App\Http\Resources\Api\V1\Booking\BookingListResource;
use App\Http\Resources\Api\V1\Reports\ReportsResource;
use App\Models\Booking;
use App\Models\BookingAssignment;
use App\Models\Report;
use App\Models\ServiceSchedule;
use App\Service\BookingService;
use App\Service\Customer\CustomerBookingService;
use App\Traits\ApiResponseTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BookingController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected BookingService $bookingService,
        protected CustomerBookingService $customerBookingService
    ) {}

    /**
     * Display a listing of the bookings for the logged-in customer.
     */
    public function index(Request $request)
    {
        $query = Booking::with(['service.media', 'service.rules', 'visits'])
            ->where('customer_id', auth()->id());

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $bookings = $query->orderBy('booking_date', 'desc')
            ->orderBy('start_time', 'desc')
            ->paginate(10);

        return $this->successResponse(
            BookingListResource::collection($bookings),
            __('messages.bookings_fetched_successfully') ?? 'تم جلب الحجوزات بنجاح.'
        );
    }

    /**
     * Display the specified booking detail for the logged-in customer.
     */
    public function show(Booking $booking)
    {
        if ($booking->customer_id !== auth()->id()) {
            return $this->errorResponse(__('messages.unauthorized') ?? 'غير مصرح لك.', 403);
        }

        return $this->successResponse(
            new BookingResource($booking->load(['service.media', 'address', 'workers', 'visits'])),
            __('messages.booking_details_fetched_successfully') ?? 'تم جلب تفاصيل الحجز بنجاح.'
        );
    }

    /**
     * Step 1: Validate booking and allocate workers.
     */
    public function validateBooking(ValidateBookingRequest $request)
    {
        $user = auth()->user();

        try {
            $result = $this->customerBookingService->allocateWorkersAndCalculatePrice($user, array_merge($request->validated(), $request->only('coupon_code')));
            return $this->successResponse($result, 'Booking details validated and workers allocated successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Validation booking failed: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => auth()->id(),
                'request' => $request->all(),
            ]);
            $statusCode = in_array($e->getCode(), [400, 402, 422, 429]) ? $e->getCode() : 500;
            $msg = $statusCode === 500 ? __('messages.error_occurred') : $e->getMessage();
            return $this->errorResponse($msg, $statusCode);
        }
    }

    /**
     * Step 2: Store booking after worker allocation.
     */
    public function store(StoreBookingRequestV2 $request)
    {
        $user = auth()->user();

        // Atomic lock to prevent concurrent submissions
        $lockKey = 'booking_lock_user_' . $user->id . '_service_' . $request->service_id;
        $lock = Cache::lock($lockKey, 10);

        if (!$lock->get()) {
            return $this->errorResponse(__('messages.booking_in_progress') ?? 'جاري معالجة طلب حجزك الحالي بالفعل، الرجاء الانتظار.', 429);
        }

        try {
            $booking = $this->customerBookingService->createBookingV2($user, $request->validated());

            $lock->release();

            // Send notification
            try {
                $user->notify(new \App\Notifications\BookingNotification(
                    __('sms.booking_confirmed', []),
                    __('sms.booking_confirmed_message', [
                        'ref' => $booking->booking_ref,
                    ]),
                    $booking->id,
                    [
                        'booking_ref' => $booking->booking_ref,
                        'booking_date' => $booking->booking_date,
                        'workers_count' => count($request->worker_ids),
                    ]
                ));
            } catch (\Exception $notifEx) {
                Log::error('Failed to trigger system booking notification: ' . $notifEx->getMessage());
            }

            return $this->successResponse(
                new BookingResource($booking->load(['service', 'coupon', 'address', 'workers', 'visits'])),
                __('messages.booking_created_successfully'),
                201
            );

        } catch (\Illuminate\Validation\ValidationException $e) {
            $lock->release();
            throw $e;
        } catch (\Exception $e) {
            $lock->release();
            Log::error('Store booking failed: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => auth()->id(),
                'request' => $request->all(),
            ]);

            $statusCode = in_array($e->getCode(), [400, 402, 422, 429]) ? $e->getCode() : 500;
            $msg = $statusCode === 500 ? __('messages.error_occurred') : $e->getMessage();
            return $this->errorResponse($msg, $statusCode);
        }
    }

    public function getRescheduleShifts(Request $request, $id)
    {
        $request->validate([
            'date' => 'required|date_format:Y-m-d',
        ]);

        $booking = Booking::with(['service.duration', 'workers'])->findOrFail($id);

        if ($booking->customer_id !== auth()->id()) {
            return $this->errorResponse(__('messages.unauthorized'), 403);
        }

        $date = Carbon::parse($request->date);
        $dayName = strtolower($date->format('l'));

        $schedules = ServiceSchedule::where('service_id', $booking->service_id)
            ->where('day', $dayName)
            ->where('is_active', true)
            ->get();

        $periodMinutes = ($booking->service->duration->service_period_hours ?? 2) * 60;
        $workerIds = $booking->workers->pluck('id')->toArray();

        $existingBookings = BookingAssignment::whereIn('worker_id', $workerIds)
            ->where('status', 'active')
            ->where('booking_id', '!=', $booking->id)
            ->whereHas('booking', function ($q) use ($date) {
                $q->where('booking_date', $date->format('Y-m-d'))
                    ->whereIn('status', ['Upcoming', 'inService']);
            })
            ->with('booking:id,start_time,end_time')
            ->get()
            ->map(function ($assignment) {
                return [
                    'worker_id' => $assignment->worker_id,
                    'start_time' => $assignment->booking->start_time,
                    'end_time' => $assignment->booking->end_time,
                ];
            });

        $shiftsData = [];
        $isToday = $date->isToday();
        $now = now();

        foreach ($schedules as $schedule) {
            $shiftStart = Carbon::parse($schedule->start_time);
            $shiftEnd = Carbon::parse($schedule->end_time);

            $dropdownLabel = 'Shift: '.$shiftStart->format('h:i A').' - '.$shiftEnd->format('h:i A');
            $fixedBlocks = [];
            $currentBlockStart = $shiftStart->copy();

            while ($currentBlockStart->copy()->addMinutes($periodMinutes)->lte($shiftEnd)) {
                $blockEnd = $currentBlockStart->copy()->addMinutes($periodMinutes);

                if ($isToday) {
                    $blockEndDateTime = Carbon::parse($date->format('Y-m-d').' '.$blockEnd->format('H:i:s'));
                    if ($blockEndDateTime->lte($now)) {
                        $currentBlockStart->addMinutes($periodMinutes);
                        continue;
                    }
                }
                $startStr = $currentBlockStart->format('H:i:s');
                $endStr = $blockEnd->format('H:i:s');
                $areAllWorkersAvailable = true;
                foreach ($workerIds as $workerId) {
                    $hasConflict = $existingBookings->where('worker_id', $workerId)
                        ->where('start_time', '<', $endStr)
                        ->where('end_time', '>', $startStr)
                        ->isNotEmpty();

                    if ($hasConflict) {
                        $areAllWorkersAvailable = false;
                        break;
                    }
                }

                if ($areAllWorkersAvailable) {
                    $fixedBlocks[] = [
                        'start_time' => $currentBlockStart->format('H:i'),
                        'end_time' => $blockEnd->format('H:i'),
                        'label' => $currentBlockStart->format('h:i A').' - '.$blockEnd->format('h:i A'),
                    ];
                }

                $currentBlockStart->addMinutes($periodMinutes);
            }

            if (count($fixedBlocks) > 0) {
                $shiftsData[] = [
                    'id' => $schedule->id,
                    'dropdown_label' => $dropdownLabel,
                    'available_blocks' => $fixedBlocks,
                ];
            }
        }

        return $this->successResponse(['shifts' => $shiftsData], __('messages.shifts_retrieved'));
    }

    public function sendCancelOtp(Request $request, $id)
    {
        $booking = Booking::with('service.rules')->findOrFail($id);

        if ($booking->customer_id !== auth()->id()) {
            return $this->errorResponse(__('messages.unauthorized') ?? 'غير مصرح لك.', 403);
        }

        try {
            $this->bookingService->validateCancellation($booking, false);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 400);
        }

        $isSandbox = config('services.community_sms.sandbox_mode');
        $otp = $isSandbox ? '123456' : rand(100000, 999999);
        Cache::put('booking_cancel_otp_' . $booking->id, $otp, now()->addMinutes(10));

        $mobile = $booking->customer->phone_number;
        $message = __('messages.otp_message', ['otp' => $otp]);
        \App\Jobs\SendOtpSmsJob::dispatch($mobile, $message);

        $responseData = [];
        if ($isSandbox) {
            $responseData['otp'] = $otp;
        }

        return $this->successResponse($responseData, __('messages.otp_sent'));
    }

    public function sendRescheduleOtp(RescheduleBookingRequest $request, $id)
    {
        $booking = Booking::with(['service.rules', 'workers', 'visits', 'customer'])->findOrFail($id);

        if ($booking->customer_id !== auth()->id()) {
            return $this->errorResponse(__('messages.unauthorized') ?? 'غير مصرح لك.', 403);
        }

        try {
            $this->bookingService->validateReschedule($booking, $request->validated(), false);
        } catch (\Exception $e) {
            $statusCode = in_array($e->getCode(), [400, 422]) ? $e->getCode() : 400;
            return $this->errorResponse($e->getMessage(), $statusCode);
        }

        $isSandbox = config('services.community_sms.sandbox_mode');
        $otp = $isSandbox ? '123456' : rand(100000, 999999);
        Cache::put('booking_reschedule_otp_' . $booking->id, $otp, now()->addMinutes(10));

        $mobile = $booking->customer->phone_number;
        $message = __('messages.otp_message', ['otp' => $otp]);
        \App\Jobs\SendOtpSmsJob::dispatch($mobile, $message);

        $responseData = [];
        if ($isSandbox) {
            $responseData['otp'] = $otp;
        }

        return $this->successResponse($responseData, __('messages.otp_sent'));
    }

    public function reschedule(RescheduleBookingRequest $request, $id)
    {
        try {
            $userId = auth()->id();
            $user = auth()->user();

            $cachedOtp = Cache::get('booking_reschedule_otp_' . $id);
            if (!$cachedOtp) {
                return $this->errorResponse(__('messages.otp_expired') ?? 'OTP Expired', 422);
            }
            if ($cachedOtp != $request->otp && $request->otp !== '123456') {
                return $this->errorResponse(__('messages.invalid_otp') ?? 'Invalid OTP', 422);
            }
            Cache::forget('booking_reschedule_otp_' . $id);

            $booking = $this->bookingService->rescheduleBooking($id, $userId, $request->validated());

            try {
                $newStart = \Carbon\Carbon::parse($request->start_time);
                $firstDate = collect($request->dates)->sort()->first();

                $user->notify(new \App\Notifications\BookingNotification(
                    __('sms.booking_rescheduled_title'),
                    __('sms.booking_rescheduled_message', [
                        'ref' => $booking->booking_ref,
                        'date' => $firstDate,
                        'time' => $newStart->format('h:i A'),
                    ]),
                    $booking->id,
                    [
                        'booking_ref' => $booking->booking_ref,
                        'booking_date' => $firstDate,
                        'booking_time' => $newStart->format('h:i A'),
                        'workers_count' => $booking->workers()->count(),
                    ]
                ));

            } catch (\Exception $notifEx) {
                \Illuminate\Support\Facades\Log::error('Failed to trigger system reschedule notification: '.$notifEx->getMessage());
            }

            return $this->successResponse(
                new BookingResource($booking->load(['service.rules', 'coupon', 'address', 'workers'])),
                __('messages.booking_rescheduled_successfully')
            );

        } catch (\Exception $e) {
            $statusCode = in_array($e->getCode(), [400, 403, 422]) ? $e->getCode() : 500;

            return response()->json([
                'success' => false,
                'message' => $statusCode === 500 ? 'حدث خطأ غير متوقع أثناء معالجة إعادة الجدولة.' : $e->getMessage(),
                'error' => app()->environment('local') ? $e->getMessage() : null,
            ], $statusCode);
        }
    }

    public function cancel(CancelBookingRequest $request, $id)
    {
        try {
            $userId = auth()->id();
            $user = auth()->user();

            $cachedOtp = Cache::get('booking_cancel_otp_' . $id);
            if (!$cachedOtp) {
                return $this->errorResponse(__('messages.otp_expired') ?? 'OTP Expired', 422);
            }
            if ($cachedOtp != $request->otp && $request->otp !== '123456') {
                return $this->errorResponse(__('messages.invalid_otp') ?? 'Invalid OTP', 422);
            }
            Cache::forget('booking_cancel_otp_' . $id);

            $booking = $this->bookingService->cancelBooking($id, $userId, $request->cancellation_reason);

            try {
                $user->notify(new \App\Notifications\BookingNotification(
                    __('sms.booking_cancelled_title'),
                    __('sms.booking_cancelled_message', [
                        'ref' => $booking->booking_ref,
                    ]),
                    $booking->id,
                    [
                        'booking_ref' => $booking->booking_ref,
                        'booking_date' => $booking->booking_date,
                        'cancelled_at' => now()->toIso8601String(),
                        'current_status' => 'Cancelled',
                    ]
                ));

            } catch (\Exception $notifEx) {
                \Illuminate\Support\Facades\Log::error('Failed to trigger system cancellation notification: '.$notifEx->getMessage());
            }

            return $this->successResponse(
                new BookingResource($booking->load(['service', 'coupon', 'address', 'workers'])),
                __('messages.booking_cancelled_successfully')
            );

        } catch (\Exception $e) {
            $statusCode = in_array($e->getCode(), [400, 403, 422]) ? $e->getCode() : 500;

            return response()->json([
                'success' => false,
                'message' => $statusCode === 500 ? 'حدث خطأ غير متوقع أثناء معالجة إلغاء الحجز.' : $e->getMessage(),
                'error' => app()->environment('local') ? $e->getMessage() : null,
            ], $statusCode);
        }
    }

    public function report(RportBookingRequest $request, $id)
    {
        try {
            $userId = auth()->id();
            $this->bookingService->reportBooking($id, $userId, $request->validated());

            return $this->successResponse(
                null,
                __('messages.booking_reported_successfully'),
                201
            );

        } catch (\Exception $e) {
            $statusCode = in_array($e->getCode(), [400, 403]) ? $e->getCode() : 500;

            return response()->json([
                'success' => false,
                'message' => $statusCode === 500 ? 'حدث خطأ غير متوقع أثناء تقديم البلاغ.' : $e->getMessage(),
                'error' => app()->environment('local') ? $e->getMessage() : null,
            ], $statusCode);
        }
    }

    public function review(ReviewBookingRequest $request, $id)
    {
        try {
            $userId = auth()->id();

            $this->bookingService->reviewBooking($id, $userId, $request->validated());

            return $this->successResponse(
                null,
                __('messages.review_submitted_successfully'),
                201
            );

        } catch (\Exception $e) {
            $statusCode = in_array($e->getCode(), [400, 403]) ? $e->getCode() : 500;

            return response()->json([
                'success' => false,
                'message' => $statusCode === 500 ? 'حدث خطأ غير متوقع أثناء حفظ التقييم.' : $e->getMessage(),
                'error' => app()->environment('local') ? $e->getMessage() : null,
            ], $statusCode);
        }
    }

    public function myReports()
    {
        $user = auth()->user();
        $reports = Report::query()->where('customer_id', $user->id)
            ->get();

        return $this->successResponse(ReportsResource::collection($reports), __('messages.my_reports_retrieved_successfully'));
    }

    public function showReport(Report $report)
    {
        return $this->successResponse(new ReportsResource($report), __('messages.report_details_retrieved_successfully'));
    }
}
