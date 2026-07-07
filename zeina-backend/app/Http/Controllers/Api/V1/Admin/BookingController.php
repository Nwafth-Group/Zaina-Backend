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
use App\Notifications\BookingNotification;
use App\Models\Booking;
use App\Models\Worker;
use App\Models\BookingAssignment;
use App\Models\BookingHistory;
use App\Service\Admin\BookingStatusService;
use App\Traits\ApiResponseTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
        $booking->load(['customer', 'service', 'workers', 'address', 'histories.createdBy', 'visits']);

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

    /**
     * Get eligible and available workers for a booking slot.
     */
    public function eligibleWorkers(Booking $booking)
    {
        $service = $booking->service;
        if (!$service) {
            return $this->errorResponse('Service not found for this booking.', 404);
        }

        // Get busy worker IDs during this booking's time slot
        $busyWorkerIds = BookingAssignment::where('status', 'active')
            ->where('booking_id', '!=', $booking->id)
            ->whereHas('booking', function ($q) use ($booking) {
                $q->where('booking_date', $booking->booking_date)
                    ->whereIn('status', ['Upcoming', 'inService'])
                    ->where(function ($timeQ) use ($booking) {
                        $timeQ->where('start_time', '<', $booking->end_time)
                              ->where('end_time', '>', $booking->start_time);
                    });
            })
            ->pluck('worker_id')
            ->unique()
            ->toArray();

        // Get active workers assigned to the service and who are not busy
        $eligibleWorkers = $service->workers()
            ->where('workers.status', 'active')
            ->whereNotIn('workers.id', $busyWorkerIds)
            ->get()
            ->map(fn($w) => [
                'id'          => $w->id,
                'name'        => $w->name,
                'nationality' => $w->nationality,
                'avg_rating'  => $w->avg_rating,
                'status'      => $w->status,
            ]);

        return $this->successResponse($eligibleWorkers, __('messages.workers_fetched_success') ?? 'تم جلب العمال المتاحين بنجاح.');
    }

    /**
     * Manually assign or replace workers for a booking.
     */
    public function assignWorkers(Request $request, Booking $booking)
    {
        $request->validate([
            'worker_ids'   => ['required', 'array', 'min:1'],
            'worker_ids.*' => ['required', 'integer', 'exists:workers,id'],
            'note'         => ['nullable', 'string'],
            'override_time'=> ['nullable', 'boolean'],
        ]);

        // Rule BR-22: Cannot assign workers if payment status is failed
        if ($booking->payment_status === 'failed') {
            return $this->errorResponse('Cannot assign workers to a booking with failed payment.', 422);
        }

        $workerIds = $request->input('worker_ids');
        $workers = Worker::whereIn('id', $workerIds)->get();

        // Check if any worker is inactive
        foreach ($workers as $worker) {
            if ($worker->status !== 'active') {
                return $this->errorResponse("Worker {$worker->name} is inactive and cannot be assigned.", 422);
            }
        }

        // Validate service eligibility
        $service = $booking->service;
        $eligibleWorkerIds = $service->workers()->pluck('workers.id')->toArray();
        foreach ($workerIds as $id) {
            if (!in_array($id, $eligibleWorkerIds)) {
                $w = $workers->firstWhere('id', $id);
                return $this->errorResponse("Worker " . ($w->name ?? $id) . " is not eligible for this service.", 422);
            }
        }

        // Validate schedule conflicts
        $busyWorkerIds = BookingAssignment::where('status', 'active')
            ->where('booking_id', '!=', $booking->id)
            ->whereHas('booking', function ($q) use ($booking) {
                $q->where('booking_date', $booking->booking_date)
                    ->whereIn('status', ['Upcoming', 'inService'])
                    ->where(function ($timeQ) use ($booking) {
                        $timeQ->where('start_time', '<', $booking->end_time)
                              ->where('end_time', '>', $booking->start_time);
                    });
            })
            ->pluck('worker_id')
            ->toArray();

        foreach ($workerIds as $id) {
            if (in_array($id, $busyWorkerIds)) {
                $w = $workers->firstWhere('id', $id);
                return $this->errorResponse("Worker " . ($w->name ?? $id) . " has a schedule conflict during this slot.", 422);
            }
        }

        // Rule BR-13: Validate service started constraint
        $bookingStart = Carbon::parse($booking->booking_date . ' ' . $booking->start_time);
        if (now()->greaterThanOrEqualTo($bookingStart) && !$request->input('override_time')) {
            return $this->errorResponse('Cannot replace workers after the service has already started.', 422);
        }

        // Fetch previous worker names and new worker names for a descriptive history note
        $previousWorkers = $booking->workers()->pluck('name')->toArray();
        $newWorkers = Worker::whereIn('id', $workerIds)->pluck('name')->toArray();

        $historyNote = 'Manual worker assignment/replacement by Admin.';
        if (!empty($previousWorkers)) {
            $historyNote = 'Replaced workers (' . implode(', ', $previousWorkers) . ') with (' . implode(', ', $newWorkers) . ').';
        } else {
            $historyNote = 'Assigned workers: ' . implode(', ', $newWorkers) . '.';
        }

        if ($request->filled('note')) {
            $historyNote .= ' Note: ' . $request->input('note');
        }

        // Determine new booking status
        $requiredCount = (int) $booking->number_of_workers;
        $assignedCount = count($workerIds);
        $oldStatus = $booking->status;

        // If required worker count is met, status becomes 'Upcoming' (Confirmed), otherwise remains 'Pending Assignment'
        $newStatus = ($assignedCount >= $requiredCount) ? 'Upcoming' : 'Pending Assignment';

        DB::transaction(function () use ($booking, $workerIds, $oldStatus, $newStatus, $historyNote) {
            // Replace existing active assignments
            BookingAssignment::where('booking_id', $booking->id)
                ->where('status', 'active')
                ->update(['status' => 'replaced']);

            // Create new active assignments
            foreach ($workerIds as $workerId) {
                BookingAssignment::create([
                    'booking_id'  => $booking->id,
                    'worker_id'   => $workerId,
                    'status'      => 'active',
                    'assigned_at' => now(),
                ]);
            }

            // Update booking status
            $booking->update([
                'status' => $newStatus,
            ]);

            // Save log to booking histories
            BookingHistory::create([
                'booking_id' => $booking->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'note'       => $historyNote,
                'created_by' => auth()->id(),
            ]);
        });

        
        // Rule BR-16 / BR-17: Notify customer via push notification, database, and SMS queue
        try {
            if ($booking->customer) {
                $booking->customer->notify(new BookingNotification(
                    __('messages.worker_assignment_updated_title'),
                    __('messages.worker_assignment_updated_body', ['ref' => $booking->booking_ref]),
                    $booking->id,
                    [
                        'booking_ref' => $booking->booking_ref,
                        'current_status' => $booking->status,
                    ]
                ));
            }
        } catch (\Exception $ex) {
            Log::error('Notification failure: ' . $ex->getMessage());
        }

        $booking->load(['workers', 'histories']);

        return $this->successResponse(new AdminBookingDetailResource($booking), __('messages.booking_workers_updated') ?? 'تم تحديث تعيين العمالة بنجاح.');
    }
}
