<?php

namespace App\Http\Controllers\Api\V2\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V2\Customer\ValidateBookingRequest;
use App\Http\Requests\Api\V2\Customer\StoreBookingRequest;
use App\Http\Resources\Api\V2\Booking\BookingResource;
use App\Http\Resources\Api\V2\Booking\BookingListResource;
use App\Models\Booking;
use App\Service\Customer\CustomerBookingService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BookingController extends Controller
{
    use ApiResponseTrait;

    protected $bookingService;

    public function __construct(CustomerBookingService $bookingService)
    {
        $this->bookingService = $bookingService;
    }

    /**
     * Display a listing of the bookings for the logged-in customer.
     */
    public function index(Request $request)
    {
        $query = Booking::with(['service.media', 'visits'])
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
            $result = $this->bookingService->allocateWorkersAndCalculatePrice($user, $request->validated());
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
    public function store(StoreBookingRequest $request)
    {
        $user = auth()->user();

        // Atomic lock to prevent concurrent submissions
        $lockKey = 'booking_lock_user_' . $user->id . '_service_' . $request->service_id;
        $lock = Cache::lock($lockKey, 10);

        if (!$lock->get()) {
            return $this->errorResponse(__('messages.booking_in_progress') ?? 'جاري معالجة طلب حجزك الحالي بالفعل، الرجاء الانتظار.', 429);
        }

        try {
            $booking = $this->bookingService->createBookingV2($user, $request->validated());

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
}
