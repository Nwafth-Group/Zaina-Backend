<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\Admin\BookingStatus;
use App\Filters\Booking\AreaFilter;
use App\Filters\Booking\CategoryFilter;
use App\Filters\Booking\CityFilter;
use App\Filters\Booking\DateFromFilter;
use App\Filters\Booking\DateToFilter;
use App\Filters\Booking\MaxAmountFilter;
use App\Filters\Booking\MinAmountFilter;
use App\Filters\Booking\PaymentStatusFilter;
use App\Filters\Booking\SearchFilter;
use App\Filters\Booking\ServiceFilter;
use App\Filters\Booking\StatusFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UpdateBookingStatusRequest;
use App\Http\Resources\Api\V1\Booking\AdminBookingDetailResource;
use App\Http\Resources\Api\V1\Booking\AdminBookingResource;
use App\Models\Booking;
use App\Service\Admin\BookingStatusService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;

class BookingController extends Controller
{
    use ApiResponseTrait;

    /** Columns an admin may sort the listing by. */
    private const ALLOWED_SORTS = ['booking_date', 'total_amount', 'created_at', 'id'];

    public function __construct(private readonly BookingStatusService $bookingStatus) {}

    /**
     * Admin bookings listing — filterable by status, payment status, service,
     * date range and a free-text search (ref / customer / service), with a safe
     * sort allow-list.
     */
    public function index(Request $request)
    {
        $query = app(Pipeline::class)
            ->send(Booking::query()->with(['customer', 'service', 'workers']))
            ->through([
                SearchFilter::class,
                StatusFilter::class,
                PaymentStatusFilter::class,
                ServiceFilter::class,
                CategoryFilter::class,
                CityFilter::class,
                AreaFilter::class,
                MinAmountFilter::class,
                MaxAmountFilter::class,
                DateFromFilter::class,
                DateToFilter::class,
            ])
            ->thenReturn();

        $sortBy = in_array($request->input('sort_by'), self::ALLOWED_SORTS, true) ? $request->input('sort_by') : 'created_at';
        $sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortDir);

        $bookings = $query->paginate((int) $request->input('per_page', 15))->withQueryString();

        return $this->successResponse(AdminBookingResource::collection($bookings), __('messages.bookings_retrieved'));
    }

    public function show(Booking $booking)
    {
        $booking->load(['customer', 'service', 'workers', 'address', 'histories.createdBy']);

        return $this->successResponse(new AdminBookingDetailResource($booking), __('messages.booking_fetched_success'));
    }

    /**
     * Change a booking's status. Validation lives in the form request and the
     * transition + history logging in the BookingStatusService.
     */
    public function updateStatus(UpdateBookingStatusRequest $request, Booking $booking)
    {
        $booking = $this->bookingStatus->change(
            $booking,
            BookingStatus::from($request->validated('status')),
            $request->validated('note'),
            $request->user(),
        );

        return $this->successResponse(new AdminBookingResource($booking), __('messages.booking_status_updated'));
    }
}
