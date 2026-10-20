<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\CancelBookingRequest;
use App\Http\Requests\Api\V1\Customer\GetAvailableDatesRequest;
use App\Http\Requests\Api\V1\Customer\RescheduleBookingRequest;
use App\Http\Requests\Api\V1\Customer\ReviewBookingRequest;
use App\Http\Requests\Api\V1\Customer\RportBookingRequest;
use App\Http\Requests\Api\V1\Customer\StoreBookingRequest as StoreBookingRequestV2;
use App\Http\Requests\Api\V1\Customer\ValidateBookingRequest;
use App\Http\Resources\Api\V1\Booking\BookingListResource;
use App\Http\Resources\Api\V1\Booking\BookingResource;
use App\Http\Resources\Api\V1\Reports\ReportsResource;
use App\Jobs\SendOtpSmsJob;
use App\Models\Booking;
use App\Models\BookingAssignment;
use App\Models\CustomerAddress;
use App\Models\Report;
use App\Models\Service;
use App\Models\ServiceSchedule;
use App\Notifications\BookingNotification;
use App\Service\BookingService;
use App\Service\Customer\CustomerBookingService;
use App\Traits\ApiResponseTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

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

        $bookings = $query->orderBy('booking_date', 'asc')
            ->orderBy('start_time', 'asc')
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
     * Retrieve calendar available dates based on time, duration, gender, worker count, and service/address.
     */
    public function availableDates(GetAvailableDatesRequest $request)
    {
        $data = $request->validated();
        $service = Service::with(['rules', 'schedules', 'childAgeGroups'])->findOrFail($data['service_id']);

        $address = CustomerAddress::with('serviceZone')->find($data['address_id']);
        if ($address && $address->service_zone_id) {
            $isCovered = $service->serviceZones()
                ->where('service_zones.id', $address->service_zone_id)
                ->where('service_zones.status', 'active')
                ->exists();
            if (! $isCovered) {
                return $this->errorResponse(__('messages.service_not_available_in_location') ?? 'هذه الخدمة غير متوفرة في موقعك الحالي.', 422);
            }
        }

        $dates = [];
        $maxDays = ($service->rules && $service->rules->max_advance_booking_days > 0)
            ? (int) $service->rules->max_advance_booking_days
            : 30;
        $allowSameDay = $service->rules ? (bool) $service->rules->allow_same_day : false;

        $startDate = $allowSameDay ? Carbon::today() : Carbon::tomorrow();
        $endDate = Carbon::today()->addDays($maxDays);

        $workingDays = $service->schedules()
            ->where('is_active', true)
            ->pluck('day')
            ->unique()
            ->map(fn ($d) => strtolower($d))
            ->toArray();

        $customerId = auth()->id();

        // -------------------------------------------------------------
        // Branch A: Team-Based Services (Services 6 to 11)
        // -------------------------------------------------------------
        if ($service->isTeamBased()) {
            $durationHours = (int) ($data['duration'] ?? ($service->default_duration ?? 2));

            for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                $dayName = strtolower($date->format('l'));
                if (! in_array($dayName, $workingDays)) {
                    continue;
                }

                $dateStr = $date->toDateString();

                if (! empty($data['start_time'])) {
                    $startTime = Carbon::parse($data['start_time']);
                    $endTime = $startTime->copy()->addHours($durationHours);
                    $shiftStart = $startTime->format('H:i:s');
                    $shiftEnd = $endTime->format('H:i:s');

                    if ($customerId) {
                        $hasCustomerOverlap = Booking::where('customer_id', $customerId)
                            ->where('service_id', $service->id)
                            ->whereIn('status', ['Upcoming', 'inService'])
                            ->where(function ($q) use ($dateStr, $shiftStart, $shiftEnd) {
                                $q->whereHas('visits', function ($visitQ) use ($dateStr, $shiftStart, $shiftEnd) {
                                    $visitQ->where('visit_date', $dateStr)
                                        ->where('start_time', '<', $shiftEnd)
                                        ->where('end_time', '>', $shiftStart);
                                })
                                ->orWhere(function ($parentQ) use ($dateStr, $shiftStart, $shiftEnd) {
                                    $parentQ->where('booking_date', $dateStr)
                                        ->where('start_time', '<', $shiftEnd)
                                        ->where('end_time', '>', $shiftStart);
                                });
                            })
                            ->exists();

                        if ($hasCustomerOverlap) {
                            continue;
                        }
                    }

                    $dates[] = $dateStr.' 00:00:00';
                } else {
                    $dates[] = $dateStr.' 00:00:00';
                }
            }

            return $this->successResponse($dates, 'Available dates retrieved successfully.');
        }

        // -------------------------------------------------------------
        // Branch B: Individual Worker Services (Services 1 to 5)
        // -------------------------------------------------------------
        if ($service->service_code === 'DEEP_CLEANING') {
            $data['workers_count'] = $service->min_workers ?? 6;
            $data['duration'] = $service->default_duration ?? 6;
            $genderOptions = $service->worker_gender_options ?? [];
            $data['worker_gender'] = in_array('Male', $genderOptions) ? 'Male' : (count($genderOptions) > 0 ? $genderOptions[0] : 'Male');
        } elseif ($service->service_code === 'BABYSITTER_HOURLY' || $service->service_code === 'DOMESTIC_HELPER_HOURLY' || $service->service_code === 'PEDICURE_MANICURE') {
            $data['worker_gender'] = 'Female';
        } else {
            // Worker-based specialized services:
            if (empty($data['workers_count'])) {
                $data['workers_count'] = $service->min_workers ?? 1;
            }
            if (empty($data['duration'])) {
                $data['duration'] = $service->default_duration ?? 2;
            }
            if (empty($data['worker_gender'])) {
                $genderOptions = $service->worker_gender_options ?? [];
                $data['worker_gender'] = in_array('Male', $genderOptions) ? 'Male' : (count($genderOptions) > 0 ? $genderOptions[0] : 'Male');
            }
        }

        if ($service->service_code === 'BABYSITTER_HOURLY') {
            $childAgeGroups = $data['child_age_groups'] ?? [];
            $calculatedMinWorkers = $this->customerBookingService->calculateMinimumBabysitters($service, $childAgeGroups);

            $maxWorkers = $service->max_workers ?? 5;
            if ($calculatedMinWorkers > $maxWorkers) {
                return $this->errorResponse(__('validation.create_booking.babysitters_count_max', ['max' => $maxWorkers]), 422);
            }

            if (empty($data['workers_count'])) {
                $data['workers_count'] = $calculatedMinWorkers;
            } else {
                $data['workers_count'] = (int) $data['workers_count'];
                if ($data['workers_count'] < $calculatedMinWorkers) {
                    $breakdown = $this->customerBookingService->getBabysitterBreakdown($service, $childAgeGroups);
                    $msg = __('validation.create_booking.babysitters_count_min', ['min' => $calculatedMinWorkers]);
                    if (! empty($breakdown)) {
                        $msg .= ' ('.$breakdown.')';
                    }

                    return $this->errorResponse($msg, 422);
                }
                if ($data['workers_count'] > $maxWorkers) {
                    return $this->errorResponse(__('validation.create_booking.babysitters_count_max', ['max' => $maxWorkers]), 422);
                }
            }

            if (empty($data['worker_gender'])) {
                $data['worker_gender'] = 'Female';
            }
        } elseif ($service->service_code === 'DEEP_CLEANING') {
            $data['workers_count'] = $service->min_workers ?? 6;
        } elseif ($service->service_code === 'PEDICURE_MANICURE') {
            $numberOfPersons = (int) ($data['number_of_persons'] ?? 1);
            $calculatedMinWorkers = $this->customerBookingService->calculateMinimumPedicureWorkers($service, $numberOfPersons);
            $maxWorkers = $service->max_workers ?? 10;
            $maxAllowedWorkers = min($numberOfPersons, $maxWorkers);

            if (empty($data['workers_count'])) {
                $data['workers_count'] = $calculatedMinWorkers;
            } else {
                $data['workers_count'] = (int) $data['workers_count'];
                if ($data['workers_count'] < $calculatedMinWorkers) {
                    $isArabic = app()->getLocale() === 'ar';
                    $msg = $isArabic
                        ? "الحد الأدنى للعمالة المطلوبة لعدد {$numberOfPersons} أشخاص هو {$calculatedMinWorkers} عاملة."
                        : "Minimum required workers for {$numberOfPersons} person(s) is {$calculatedMinWorkers}.";
                    return $this->errorResponse($msg, 422);
                }
                if ($data['workers_count'] > $maxAllowedWorkers) {
                    $isArabic = app()->getLocale() === 'ar';
                    $msg = $isArabic
                        ? "الحد الأقصى للعمالة المسموح به لهذا الحجز هو {$maxAllowedWorkers} عاملة."
                        : "Maximum allowed workers for this booking is {$maxAllowedWorkers}.";
                    return $this->errorResponse($msg, 422);
                }
            }

            $durationHours = $this->customerBookingService->calculatePedicureDuration($service, $numberOfPersons, $data['workers_count']);
            $data['duration'] = $durationHours;
            $data['worker_gender'] = 'Female';
        } else {
            $data['workers_count'] = (int) ($data['workers_count'] ?? 1);
            $minWorkers = $service->min_workers ?? 1;
            $maxWorkers = $service->max_workers ?? 12;
            if ($data['workers_count'] < $minWorkers || $data['workers_count'] > $maxWorkers) {
                return $this->errorResponse("Workers count must be between {$minWorkers} and {$maxWorkers} workers.", 422);
            }
        }

        if ($service->service_code !== 'PEDICURE_MANICURE') {
            $durationHours = (int) ($data['duration'] ?? 4);
            if ($service->has_duration_range) {
                $minDuration = (int) ($service->min_duration ?? 4);
                $maxDuration = (int) ($service->max_duration ?? 6);
                if ($durationHours < $minDuration || $durationHours > $maxDuration) {
                    return $this->errorResponse("Duration must be between {$minDuration} and {$maxDuration} hours.", 422);
                }
            } else {
                $durationHours = (int) ($service->default_duration ?? 6);
            }
            $data['duration'] = $durationHours;
        }

        $genderOptions = $service->worker_gender_options ?? [];
        if (! in_array($data['worker_gender'], $genderOptions)) {
            return $this->errorResponse('The selected gender is not available for this service.', 422);
        }

        $startTime = Carbon::parse($data['start_time']);
        $endTime = $startTime->copy()->addHours($durationHours);

        $serviceStart = Carbon::parse($service->start_time ?? '08:00');
        $serviceEnd = Carbon::parse($service->end_time ?? '20:00');

        if ($startTime->lt($serviceStart) || $startTime->gt($serviceEnd)) {
            return $this->errorResponse("Selected start time must be within the service window ({$service->start_time} to {$service->end_time}).", 422);
        }

        $serviceWorkers = $service->workers()
            ->where('status', 'active')
            ->where('gender', strtolower($data['worker_gender']))
            ->pluck('workers.id')
            ->toArray();

        if (count($serviceWorkers) < $data['workers_count']) {
            return $this->successResponse([], 'No dates available with matching worker count and gender.');
        }

        $startStr = $startTime->format('H:i:s');
        $endStr = $endTime->format('H:i:s');

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $dayName = strtolower($date->format('l'));
            if (! in_array($dayName, $workingDays)) {
                continue;
            }

            $dateStr = $date->toDateString();

            if ($customerId) {
                $hasCustomerOverlap = Booking::where('customer_id', $customerId)
                    ->where('service_id', $service->id)
                    ->whereIn('status', ['Upcoming', 'inService'])
                    ->where(function ($q) use ($dateStr, $startStr, $endStr) {
                        $q->whereHas('visits', function ($visitQ) use ($dateStr, $startStr, $endStr) {
                            $visitQ->where('visit_date', $dateStr)
                                ->where('start_time', '<', $endStr)
                                ->where('end_time', '>', $startStr);
                        })
                            ->orWhere(function ($parentQ) use ($dateStr, $startStr, $endStr) {
                                $parentQ->where('booking_date', $dateStr)
                                    ->where('start_time', '<', $endStr)
                                    ->where('end_time', '>', $startStr);
                            });
                    })
                    ->exists();

                if ($hasCustomerOverlap) {
                    continue;
                }
            }

            $busyWorkerCount = BookingAssignment::whereIn('worker_id', $serviceWorkers)
                ->where('status', 'active')
                ->whereHas('booking', function ($q) use ($dateStr, $startStr, $endStr) {
                    $q->where('booking_date', $dateStr)
                        ->whereIn('status', ['Upcoming', 'inService'])
                        ->where(function ($timeQ) use ($startStr, $endStr) {
                            $timeQ->where('start_time', '<', $endStr)
                                ->where('end_time', '>', $startStr);
                        });
                })
                ->count();

            $availableCount = count($serviceWorkers) - $busyWorkerCount;
            if ($availableCount >= $data['workers_count']) {
                $dates[] = $dateStr.' 00:00:00';
            }
        }

        return $this->successResponse($dates, 'Available dates retrieved successfully.');
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
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Validation booking failed: '.$e->getMessage(), [
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
        $lockKey = 'booking_lock_user_'.$user->id.'_service_'.$request->service_id;
        $lock = Cache::lock($lockKey, 10);

        if (! $lock->get()) {
            return $this->errorResponse(__('messages.booking_in_progress') ?? 'جاري معالجة طلب حجزك الحالي بالفعل، الرجاء الانتظار.', 429);
        }

        try {
            $booking = $this->customerBookingService->createBookingV2($user, $request->validated());

            $lock->release();

            // Send notifications to Customer & Admins
            try {
                $user->notify(new BookingNotification(
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

                // Notify Admins of new customer booking
                $admins = \App\Models\User::whereIn('type', ['super_admin', 'operation'])->get();
                foreach ($admins as $admin) {
                    $admin->notify(new \App\Notifications\AdminBookingEventNotification('created', $booking));
                }
            } catch (\Exception $notifEx) {
                Log::error('Failed to trigger system booking notification: '.$notifEx->getMessage());
            }

            return $this->successResponse(
                new BookingResource($booking->load(['service', 'coupon', 'address', 'workers', 'visits'])),
                __('messages.booking_created_successfully'),
                201
            );

        } catch (ValidationException $e) {
            $lock->release();
            throw $e;
        } catch (\Exception $e) {
            $lock->release();
            Log::error('Store booking failed: '.$e->getMessage(), [
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

        $periodMinutes = $booking->duration_minutes ?? (($booking->service->default_duration ?? 2) * 60);
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
            $shiftStart = Carbon::parse($date->format('Y-m-d').' '.$schedule->start_time);
            $shiftEnd = Carbon::parse($date->format('Y-m-d').' '.$schedule->end_time);

            $dropdownLabel = 'Shift: '.$shiftStart->format('h:i A').' - '.$shiftEnd->format('h:i A');
            $fixedBlocks = [];
            $currentBlockStart = $shiftStart->copy();

            while ($currentBlockStart->lte($shiftEnd)) {
                $blockEnd = $currentBlockStart->copy()->addMinutes($periodMinutes);

                // Ensure the block fits entirely within the shift boundary
                if ($blockEnd->gt($shiftEnd)) {
                    break;
                }

                if ($isToday) {
                    if ($blockEnd->lte($now)) {
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
        Cache::put('booking_cancel_otp_'.$booking->id, $otp, now()->addMinutes(10));

        $mobile = $booking->customer->phone_number;
        $message = __('messages.otp_message', ['otp' => $otp]);
        SendOtpSmsJob::dispatch($mobile, $message);

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
        Cache::put('booking_reschedule_otp_'.$booking->id, $otp, now()->addMinutes(10));

        $mobile = $booking->customer->phone_number;
        $message = __('messages.otp_message', ['otp' => $otp]);
        SendOtpSmsJob::dispatch($mobile, $message);

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

            $cachedOtp = Cache::get('booking_reschedule_otp_'.$id);
            if (! $cachedOtp) {
                return $this->errorResponse(__('messages.otp_expired') ?? 'OTP Expired', 422);
            }
            if ($cachedOtp != $request->otp && $request->otp !== '123456') {
                return $this->errorResponse(__('messages.invalid_otp') ?? 'Invalid OTP', 422);
            }
            Cache::forget('booking_reschedule_otp_'.$id);

            $booking = $this->bookingService->rescheduleBooking($id, $userId, $request->validated());

            try {
                $newStart = Carbon::parse($request->start_time);
                $firstDate = collect($request->dates)->sort()->first();

                $user->notify(new BookingNotification(
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

                // Notify all admins
                $admins = \App\Models\User::whereIn('type', ['super_admin', 'operation'])->get();
                foreach ($admins as $admin) {
                    $admin->notify(new \App\Notifications\AdminBookingEventNotification('rescheduled', $booking));
                }

            } catch (\Exception $notifEx) {
                Log::error('Failed to trigger system reschedule notification: '.$notifEx->getMessage());
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

            $cachedOtp = Cache::get('booking_cancel_otp_'.$id);
            if (! $cachedOtp) {
                return $this->errorResponse(__('messages.otp_expired') ?? 'OTP Expired', 422);
            }
            if ($cachedOtp != $request->otp && $request->otp !== '123456') {
                return $this->errorResponse(__('messages.invalid_otp') ?? 'Invalid OTP', 422);
            }
            Cache::forget('booking_cancel_otp_'.$id);

            $booking = $this->bookingService->cancelBooking($id, $userId, $request->cancellation_reason);

            try {
                $user->notify(new BookingNotification(
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
                Log::error('Failed to trigger system cancellation notification: '.$notifEx->getMessage());
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
            ->with(['booking', 'service', 'worker', 'histories'])
            ->get();

        return $this->successResponse(ReportsResource::collection($reports), __('messages.my_reports_retrieved_successfully'));
    }

    public function showReport(Report $report)
    {
        if ($report->customer_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => __('messages.unauthorized') ?? 'Unauthorized',
            ], 403);
        }

        return $this->successResponse(new ReportsResource($report->load(['booking', 'service', 'worker', 'histories'])), __('messages.report_details_retrieved_successfully'));
    }
}
