<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\Admin\BookingStatus;
use App\Filters\Booking\CategoryFilter;
use App\Filters\Booking\ServiceZoneFilter;
use App\Filters\Booking\DateFromFilter;
use App\Filters\Booking\DateToFilter;
use App\Filters\Booking\MaxAmountFilter;
use App\Filters\Booking\MinAmountFilter;
use App\Filters\Booking\PaymentStatusFilter;
use App\Filters\Booking\SearchFilter;
use App\Filters\Booking\ServiceFilter;
use App\Filters\Booking\StatusFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\AssignBookingTeamRequest;
use App\Http\Requests\Api\V1\Admin\ReplaceBookingWorkerRequest;
use App\Http\Requests\Api\V1\Admin\UpdateBookingStatusRequest;
use App\Http\Requests\Api\V1\Customer\RescheduleBookingRequest;
use App\Http\Resources\Api\V1\Booking\AdminBookingDetailResource;
use App\Http\Resources\Api\V1\Booking\AdminBookingResource;
use App\Notifications\BookingNotification;
use App\Models\Booking;
use App\Models\CompanyInfo;
use App\Models\Service;
use App\Models\Team;
use App\Models\Worker;
use App\Models\BookingAssignment;
use App\Models\BookingHistory;
use App\Service\Admin\BookingStatusService;
use App\Service\Booking\BookingScheduleConflictService;
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
        $countsQuery = app(Pipeline::class)
            ->send(Booking::query())
            ->through([
                SearchFilter::class,
                PaymentStatusFilter::class,
                ServiceFilter::class,
                CategoryFilter::class,
                ServiceZoneFilter::class,
                MinAmountFilter::class,
                MaxAmountFilter::class,
                DateFromFilter::class,
                DateToFilter::class,
            ])
            ->thenReturn();

        $counts = [
            'all' => (clone $countsQuery)->count(),
            'Upcoming' => (clone $countsQuery)->where('status', 'Upcoming')->count(),
            'inService' => (clone $countsQuery)->where('status', 'inService')->count(),
            'completed' => (clone $countsQuery)->where('status', 'completed')->count(),
            'cancelled' => (clone $countsQuery)->where('status', 'cancelled')->count(),
        ];

        $query = app(Pipeline::class)
            ->send(Booking::query()->with(['customer', 'service', 'workers', 'team']))
            ->through([
                SearchFilter::class,
                StatusFilter::class,
                PaymentStatusFilter::class,
                ServiceFilter::class,
                CategoryFilter::class,
                ServiceZoneFilter::class,
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

        return $this->successResponse([
            'items' => AdminBookingResource::collection($bookings),
            'counts' => $counts,
            'meta' => [
                'current_page' => $bookings->currentPage(),
                'last_page' => $bookings->lastPage(),
                'per_page' => $bookings->perPage(),
                'total' => $bookings->total(),
            ],
        ], __('messages.bookings_retrieved'));
    }

    /**
     * Export bookings to CSV / Excel spreadsheet based on applied filters.
     */
    public function export(Request $request)
    {
        $query = app(Pipeline::class)
            ->send(Booking::query()->with(['customer', 'service', 'workers', 'address.serviceZone', 'visits']))
            ->through([
                SearchFilter::class,
                StatusFilter::class,
                PaymentStatusFilter::class,
                ServiceFilter::class,
                CategoryFilter::class,
                ServiceZoneFilter::class,
                MinAmountFilter::class,
                MaxAmountFilter::class,
                DateFromFilter::class,
                DateToFilter::class,
            ])
            ->thenReturn();

        $sortBy = in_array($request->input('sort_by'), self::ALLOWED_SORTS, true) ? $request->input('sort_by') : 'created_at';
        $sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortDir);

        $bookings = $query->get();

        $filename = 'bookings_export_'.now()->format('Y-m-d_Hi').'.xls';

        $headers = [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($bookings) {
            $company = CompanyInfo::first();
            $companyName = $company?->name_ar ?? 'زينة للخدمات المنزلية';

            $currentDateStr = now()->locale('ar')->isoFormat('dddd، D MMMM YYYY - h:mm A');
            $totalCount = $bookings->count();
            $totalAmountSum = number_format($bookings->sum('total_amount'), 2);

            echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
            echo '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
            echo '<style>';
            echo 'body { font-family: "Segoe UI", Tahoma, Arial, sans-serif; direction: rtl; }';
            echo 'table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }';
            echo 'th, td { border: 1px solid #CBD5E1; padding: 10px 14px; text-align: center; vertical-align: middle; }';
            echo '.header-brand { background-color: #E91E63; color: #FFFFFF; font-size: 22px; font-weight: bold; text-align: center; padding: 16px; }';
            echo '.header-meta { background-color: #FDF0F3; color: #881337; font-size: 15px; font-weight: bold; text-align: center; padding: 12px; border: 1px solid #F43F5E; }';
            echo '.th-column { background-color: #D94A78; color: #FFFFFF; font-size: 14px; font-weight: bold; text-align: center; padding: 12px; }';
            echo '.tr-even { background-color: #F8FAFC; }';
            echo '.tr-odd { background-color: #FFFFFF; }';
            echo '</style></head>';
            echo '<body dir="rtl">';

            echo '<table>';
            echo '<tr><td colSpan="33" class="header-brand">👑 '.$companyName.' — Zeina Home Services</td></tr>';
            echo '<tr><td colSpan="33" class="header-meta">📅 <b>تاريخ ووقت التصدير:</b> '.$currentDateStr.'</td></tr>';
            echo '<tr><td colSpan="33" class="header-meta">📊 <b>إجمالي عدد الحجوزات:</b> '.$totalCount.' حجز &nbsp;|&nbsp; <b>إجمالي المبالغ:</b> '.$totalAmountSum.' EGP</td></tr>';
            echo '<tr><td colSpan="33" style="height: 10px; border: none;"></td></tr>';
            echo '</table>';

            echo '<table>';
            echo '<thead><tr>';
            $cols = [
                '#', 'رقم مرجعي (Booking Ref)', 'اسم العميل (Customer Name)', 'رقم جوال العميل (Customer Phone)',
                'إيميل العميل (Customer Email)', 'اسم الخدمة (Service Name)', 'كود الخدمة (Service Code)',
                'العمال المخصصين (Assigned Workers)', 'جنس العاملة (Worker Gender)', 'عدد العمال (Workers Count)',
                'عدد الأشخاص (Persons Count)', 'عدد الأطفال (Children Count)', 'المدينة (City)', 'المنطقة (Area)',
                'العنوان التفصيلي (Detailed Address)', 'تاريخ الحجز (Booking Date)', 'وقت البداية (Start Time)',
                'وقت النهاية (End Time)', 'المدة (Duration)', 'نوع الزيارة (Visit Type)', 'رقم الزيارة الحالية (Current Visit #)',
                'عدد الزيارات المتعددة (Visits Count)', 'جدول مواعيد الزيارات (Visits Schedule Details)',
                'المبلغ الفرعي (Subtotal)', 'قيمة الخصم (Discount)', 'الضريبة (Tax)', 'المبلغ الإجمالي (Total Amount)',
                'النقاط المستخدمة (Paid Points)', 'طريقة الدفع (Payment Method)', 'حالة الدفع (Payment Status)',
                'حالة الحجز (Booking Status)', 'ملاحظات العميل (Customer Notes)', 'تاريخ الإنشاء (Created At)'
            ];
            foreach ($cols as $col) {
                echo '<th class="th-column">'.$col.'</th>';
            }
            echo '</tr></thead>';
            echo '<tbody>';

            foreach ($bookings as $idx => $b) {
                $customerName = $b->customer ? trim(($b->customer->first_name ?? '').' '.($b->customer->last_name ?? '')) : 'N/A';
                $customerPhone = $b->customer?->phone_number ?? $b->customer?->phone ?? 'N/A';
                $customerEmail = $b->customer?->email ?? 'N/A';
                $serviceName = $b->service ? ($b->service->name_ar ?: $b->service->name_en) : 'N/A';
                $workersNames = $b->workers->isNotEmpty() ? $b->workers->pluck('name')->implode(' | ') : 'N/A';
                $city = $b->address?->city ?? $b->address?->serviceZone?->city ?? 'N/A';
                $area = $b->address?->serviceZone?->name ?? 'N/A';
                $detailedAddress = $b->address ? ($b->address->formatted_address ?? trim(($b->address->street ?? '').' '.($b->address->building_name ?? ''))) : 'N/A';
                $duration = $b->duration_minutes ? round($b->duration_minutes / 60, 1).' ساعة' : 'N/A';
                $rowClass = ($idx % 2 === 0) ? 'tr-even' : 'tr-odd';

                $visitsCount = $b->visits->isNotEmpty() ? $b->visits->count() : 1;
                $visitsScheduleDetails = '—';
                if ($b->visits->isNotEmpty()) {
                    $visitsScheduleDetails = $b->visits->map(function ($v, $i) {
                        $num = $i + 1;
                        $vDate = $v->visit_date ?? 'N/A';
                        $vTime = ($v->start_time && $v->end_time) ? "({$v->start_time} - {$v->end_time})" : '';
                        $vStatus = $v->status ? "[{$v->status}]" : '';
                        return "زيارة {$num}: {$vDate} {$vTime} {$vStatus}";
                    })->implode(' | ');
                }

                echo '<tr class="'.$rowClass.'">';
                echo '<td>'.$b->id.'</td>';
                echo '<td><b>'.htmlspecialchars($b->booking_ref ?? 'N/A').'</b></td>';
                echo '<td>'.htmlspecialchars($customerName).'</td>';
                echo '<td dir="ltr">'.htmlspecialchars($customerPhone).'</td>';
                echo '<td>'.htmlspecialchars($customerEmail).'</td>';
                echo '<td><b>'.htmlspecialchars($serviceName).'</b></td>';
                echo '<td>'.htmlspecialchars($b->service?->service_code ?? 'N/A').'</td>';
                echo '<td>'.htmlspecialchars($workersNames).'</td>';
                echo '<td>'.htmlspecialchars($b->worker_gender ?? 'N/A').'</td>';
                echo '<td>'.($b->number_of_workers ?? 1).'</td>';
                echo '<td>'.($b->number_of_persons ?? '—').'</td>';
                echo '<td>'.($b->number_of_children ?? '—').'</td>';
                echo '<td>'.htmlspecialchars($city).'</td>';
                echo '<td>'.htmlspecialchars($area).'</td>';
                echo '<td>'.htmlspecialchars($detailedAddress).'</td>';
                echo '<td>'.htmlspecialchars($b->booking_date ?? 'N/A').'</td>';
                echo '<td dir="ltr">'.htmlspecialchars($b->start_time ?? 'N/A').'</td>';
                echo '<td dir="ltr">'.htmlspecialchars($b->end_time ?? 'N/A').'</td>';
                echo '<td>'.htmlspecialchars($duration).'</td>';
                echo '<td>'.htmlspecialchars($b->visit_type ?? 'single_visit').'</td>';
                echo '<td>'.($b->visit_number ?? 1).'</td>';
                echo '<td><b>'.$visitsCount.'</b></td>';
                echo '<td>'.htmlspecialchars($visitsScheduleDetails).'</td>';
                echo '<td>'.number_format($b->price_subtotal ?? 0, 2).'</td>';
                echo '<td>'.number_format($b->discount_amount ?? 0, 2).'</td>';
                echo '<td>'.number_format($b->tax_amount ?? 0, 2).'</td>';
                echo '<td><b>'.number_format($b->total_amount ?? 0, 2).' EGP</b></td>';
                echo '<td>'.($b->paid_points ?? 0).'</td>';
                echo '<td>'.htmlspecialchars($b->payment_method ?? 'N/A').'</td>';
                echo '<td>'.htmlspecialchars($b->payment_status ?? 'N/A').'</td>';
                echo '<td><b>'.htmlspecialchars($b->status ?? 'N/A').'</b></td>';
                echo '<td>'.htmlspecialchars($b->customer_notes ?? '—').'</td>';
                echo '<td>'.($b->created_at ? $b->created_at->format('Y-m-d H:i') : 'N/A').'</td>';
                echo '</tr>';
            }

            echo '</tbody></table></body></html>';
        };

        return response()->stream($callback, 200, $headers);
    }

    public function show(Booking $booking)
    {
        $booking->load(['customer', 'service', 'workers', 'address', 'histories.createdBy', 'visits', 'team.workers']);

        return $this->successResponse(new AdminBookingDetailResource($booking), __('messages.booking_fetched_success'));
    }

    /**
     * Change a booking's status. Validation lives in the form request and the
     * transition + history logging in the BookingStatusService.
     */
    public function updateStatus(UpdateBookingStatusRequest $request, Booking $booking)
    {
        try {
            $booking = $this->bookingStatus->change(
                $booking,
                BookingStatus::from($request->validated('status')),
                $request->validated('note'),
                $request->user(),
            );

            return $this->successResponse(new AdminBookingResource($booking), __('messages.booking_status_updated'));
        } catch (\Exception $ex) {
            return $this->errorResponse($ex->getMessage(), $ex->getCode() ?: 400);
        }
    }

    /**
     * Get eligible and available workers for a booking slot.
     * Enforces active status, service eligibility, and the 60-minute transition buffer.
     */
    public function eligibleWorkers(Booking $booking)
    {
        $service = $booking->service;
        if (!$service) {
            return $this->errorResponse(__('validation.booking_conflict.worker_ineligible', ['name' => 'Service']), 404);
        }

        $conflictService = app(BookingScheduleConflictService::class);
        $candidateWorkerIds = $service->workers()->where('workers.status', 'active')->pluck('workers.id')->toArray();

        $busyWorkerIds = $conflictService->getBusyWorkerIdsForSlot(
            $candidateWorkerIds,
            $booking->booking_date,
            $booking->start_time,
            $booking->end_time,
            $booking->id,
            true // Enforce 60-minute transition gap
        );

        $eligibleWorkers = $service->workers()
            ->where('workers.status', 'active')
            ->whereNotIn('workers.id', $busyWorkerIds)
            ->get()
            ->map(fn($w) => [
                'id'          => $w->id,
                'name'        => $w->name,
                'nationality' => $w->nationality,
                'avg_rating'  => $w->avg_rating,
                'gender'      => $w->gender,
                'team_id'     => $w->team_id,
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
            return $this->errorResponse(__('validation.replace_worker.booking_payment_failed'), 422);
        }

        // Limit worker updates to Upcoming or Pending Assignment bookings only
        if ($booking->status !== 'Upcoming' && $booking->status !== 'Pending Assignment') {
            return $this->errorResponse(__('validation.replace_worker.booking_not_upcoming'), 422);
        }

        $workerIds = $request->input('worker_ids');
        $workers = Worker::whereIn('id', $workerIds)->get();

        // Check if any worker is inactive
        foreach ($workers as $worker) {
            if ($worker->status !== 'active') {
                return $this->errorResponse(__('validation.replace_worker.worker_inactive', ['name' => $worker->name]), 422);
            }
        }

        // Validate service eligibility
        $service = $booking->service;
        $eligibleWorkerIds = $service->workers()->pluck('workers.id')->toArray();
        foreach ($workerIds as $id) {
            if (!in_array($id, $eligibleWorkerIds)) {
                $w = $workers->firstWhere('id', $id);
                return $this->errorResponse(__('validation.replace_worker.worker_not_eligible', ['name' => $w->name ?? $id]), 422);
            }
        }

        // Validate schedule conflicts and 60-minute transition buffer
        $conflictService = app(BookingScheduleConflictService::class);
        $enforceBuffer = !$request->boolean('override_time');

        foreach ($workers as $w) {
            $conflictError = $conflictService->checkWorkerConflict(
                $w,
                $booking->booking_date,
                $booking->start_time,
                $booking->end_time,
                $booking->id,
                $enforceBuffer
            );
            if ($conflictError) {
                return $this->errorResponse($conflictError, 422);
            }
        }

        // Rule BR-13: Validate service started constraint
        $bookingStart = Carbon::parse($booking->booking_date . ' ' . $booking->start_time);
        if (now()->greaterThanOrEqualTo($bookingStart) && !$request->input('override_time')) {
            return $this->errorResponse(__('validation.replace_worker.service_started'), 422);
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
                $lang = $booking->customer->preferred_language === 'en' ? 'en' : 'ar';
                $title = $lang === 'en' ? 'Workers Assignment Updated' : 'تحديث تعيين العاملات';
                $body = $lang === 'en'
                    ? "The assigned workers for your booking {$booking->booking_ref} have been updated."
                    : "تم تحديث تعيين العاملات لحجزك رقم {$booking->booking_ref}.";

                $booking->customer->notify(new BookingNotification(
                    $title,
                    $body,
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

    /**
     * Replace a single worker in a booking (and swap team membership if it is a team booking).
     */
    public function replaceWorker(ReplaceBookingWorkerRequest $request, Booking $booking)
    {
        if ($booking->payment_status === 'failed') {
            return $this->errorResponse(__('validation.replace_worker.booking_payment_failed'), 422);
        }

        if ($booking->status !== 'Upcoming' && $booking->status !== 'Pending Assignment') {
            return $this->errorResponse(__('validation.replace_worker.booking_not_upcoming'), 422);
        }

        $bookingStart = Carbon::parse($booking->booking_date . ' ' . $booking->start_time);
        if (now()->greaterThanOrEqualTo($bookingStart) && !$request->boolean('override_buffer')) {
            return $this->errorResponse(__('validation.replace_worker.service_started'), 422);
        }

        $oldWorker = Worker::findOrFail($request->validated('old_worker_id'));
        $newWorker = Worker::findOrFail($request->validated('new_worker_id'));

        // Ensure old worker is currently an active assignment on this booking
        $oldAssignment = BookingAssignment::where('booking_id', $booking->id)
            ->where('worker_id', $oldWorker->id)
            ->where('status', 'active')
            ->first();

        if (!$oldAssignment) {
            return $this->errorResponse(__('validation.replace_worker.worker_not_in_booking'), 422);
        }

        if ($newWorker->status !== 'active') {
            return $this->errorResponse(__('validation.replace_worker.worker_inactive', ['name' => $newWorker->name]), 422);
        }

        // Validate service eligibility
        $service = $booking->service;
        $isEligible = $service->workers()->where('workers.id', $newWorker->id)->exists();
        if (!$isEligible) {
            return $this->errorResponse(__('validation.replace_worker.worker_not_eligible', ['name' => $newWorker->name]), 422);
        }

        // If team service or booking has team_id: must be male
        $isTeamService = $service->is_team_based ?? in_array($service->service_code, Service::getTeamBasedCodes());
        if (($isTeamService || $booking->team_id) && strtolower($newWorker->gender ?? '') !== 'male') {
            return $this->errorResponse(__('validation.replace_worker.worker_not_male_team', ['name' => $newWorker->name]), 422);
        }

        // Validate schedule conflict & 60-min transition buffer
        $conflictService = app(BookingScheduleConflictService::class);
        $enforceBuffer = !$request->boolean('override_buffer');
        $conflictErr = $conflictService->checkWorkerConflict(
            $newWorker,
            $booking->booking_date,
            $booking->start_time,
            $booking->end_time,
            $booking->id,
            $enforceBuffer
        );
        if ($conflictErr) {
            return $this->errorResponse($conflictErr, 422);
        }

        $reason = $request->validated('reason');
        $historyNote = "Worker replacement: Replaced ({$oldWorker->name}) with ({$newWorker->name}). Reason: {$reason}";

        DB::transaction(function () use ($booking, $oldWorker, $newWorker, $oldAssignment, $historyNote) {
            // If team-based / booking has team_id, swap team membership in workers table
            if ($booking->team_id) {
                $oldWorker->update(['team_id' => null]);
                $newWorker->update(['team_id' => $booking->team_id]);
            }

            // Update assignments
            $oldAssignment->update(['status' => 'replaced']);
            BookingAssignment::create([
                'booking_id'  => $booking->id,
                'worker_id'   => $newWorker->id,
                'status'      => 'active',
                'assigned_at' => now(),
            ]);

            // Save log to booking histories
            BookingHistory::create([
                'booking_id' => $booking->id,
                'old_status' => $booking->status,
                'new_status' => $booking->status,
                'note'       => $historyNote,
                'created_by' => auth()->id(),
            ]);
        });

        // Notify customer
        try {
            if ($booking->customer) {
                $lang = $booking->customer->preferred_language === 'en' ? 'en' : 'ar';
                $title = $lang === 'en' ? 'Worker Replacement' : 'استبدال عامل';
                $body = $lang === 'en'
                    ? "A worker for your booking {$booking->booking_ref} has been replaced ({$newWorker->name})."
                    : "تم استبدال عامل لحجزك رقم {$booking->booking_ref} بالعامل ({$newWorker->name}).";

                $booking->customer->notify(new BookingNotification(
                    $title,
                    $body,
                    $booking->id,
                    [
                        'booking_ref'    => $booking->booking_ref,
                        'current_status' => $booking->status,
                    ]
                ));
            }
        } catch (\Exception $ex) {
            Log::error('Notification failure during worker replacement: ' . $ex->getMessage());
        }

        $booking->load(['workers', 'histories', 'team.workers']);

        return $this->successResponse(
            new AdminBookingDetailResource($booking),
            __('validation.replace_worker.success')
        );
    }

    /**
     * Get eligible teams for a booking with their slot availability status.
     */
    public function eligibleTeams(Booking $booking)
    {
        $service = $booking->service;
        if (!$service) {
            return $this->errorResponse(__('validation.booking_conflict.worker_ineligible', ['name' => 'Service']), 404);
        }

        $conflictService = app(BookingScheduleConflictService::class);
        $teams = $service->teams()->where('teams.status', 'active')->with(['workers'])->get();

        $result = $teams->map(function ($team) use ($booking, $conflictService) {
            $isAvailable = $conflictService->isTeamAvailable(
                $team,
                $booking->booking_date,
                $booking->start_time,
                $booking->end_time,
                $booking->id
            );

            $isCurrentlyAssigned = ((int) $booking->team_id === (int) $team->id);

            return [
                'id' => $team->id,
                'name_ar' => $team->name_ar,
                'name_en' => $team->name_en,
                'code' => $team->code,
                'status' => $team->status,
                'workers_count' => $team->workers->count(),
                'workers' => $team->workers->map(fn($w) => [
                    'id' => $w->id,
                    'name' => $w->name,
                    'gender' => $w->gender,
                    'nationality' => $w->nationality,
                    'avg_rating' => $w->avg_rating,
                    'status' => $w->status,
                ]),
                'is_available' => $isAvailable,
                'is_currently_assigned' => $isCurrentlyAssigned,
            ];
        });

        return $this->successResponse($result, __('validation.assign_team.teams_fetched_success'));
    }

    /**
     * Assign or reassign an operational team to a booking.
     */
    public function assignTeam(AssignBookingTeamRequest $request, Booking $booking)
    {
        if ($booking->payment_status === 'failed') {
            return $this->errorResponse(__('validation.replace_worker.booking_payment_failed'), 422);
        }

        if ($booking->status !== 'Upcoming' && $booking->status !== 'Pending Assignment') {
            return $this->errorResponse(__('validation.replace_worker.booking_not_upcoming'), 422);
        }

        $service = $booking->service;
        $isTeamService = $service && ($service->isTeamBased() || in_array($service->service_code, Service::getTeamBasedCodes()));
        if (!$isTeamService && !$booking->team_id) {
            return $this->errorResponse(__('validation.assign_team.not_team_service'), 422);
        }

        $newTeam = Team::with('workers')->findOrFail($request->validated('team_id'));
        if ($newTeam->status !== 'active') {
            return $this->errorResponse(__('validation.assign_team.team_inactive'), 422);
        }

        if ($newTeam->workers->isEmpty()) {
            return $this->errorResponse(__('validation.assign_team.team_no_workers'), 422);
        }

        // Validate schedule conflict unless overridden
        $conflictService = app(BookingScheduleConflictService::class);
        $isAvailable = $conflictService->isTeamAvailable(
            $newTeam,
            $booking->booking_date,
            $booking->start_time,
            $booking->end_time,
            $booking->id
        );

        if (!$isAvailable && !$request->boolean('override_conflict')) {
            return $this->errorResponse(__('validation.assign_team.schedule_conflict'), 422);
        }

        // Validate service start constraint
        $bookingStart = Carbon::parse($booking->booking_date . ' ' . $booking->start_time);
        if (now()->greaterThanOrEqualTo($bookingStart) && !$request->boolean('override_conflict')) {
            return $this->errorResponse(__('validation.replace_worker.service_started'), 422);
        }

        $oldTeam = $booking->team;
        $oldTeamName = $oldTeam ? ($oldTeam->name_ar ?: $oldTeam->name_en ?: $oldTeam->code) : null;
        $newTeamName = $newTeam->name_ar ?: $newTeam->name_en ?: $newTeam->code;

        $historyNote = $oldTeam
            ? "Replaced team ({$oldTeamName}) with ({$newTeamName})."
            : "Assigned team ({$newTeamName}) to booking.";

        if ($request->filled('note')) {
            $historyNote .= ' Note: ' . $request->validated('note');
        }

        $oldStatus = $booking->status;
        $newStatus = 'Upcoming';

        DB::transaction(function () use ($booking, $newTeam, $oldStatus, $newStatus, $historyNote) {
            // Replace existing active assignments
            BookingAssignment::where('booking_id', $booking->id)
                ->where('status', 'active')
                ->update(['status' => 'replaced']);

            // Create new active assignments for each worker in the new team
            $firstWorkerId = null;
            foreach ($newTeam->workers as $worker) {
                if (!$firstWorkerId) {
                    $firstWorkerId = $worker->id;
                }
                BookingAssignment::create([
                    'booking_id'  => $booking->id,
                    'worker_id'   => $worker->id,
                    'status'      => 'active',
                    'assigned_at' => now(),
                ]);
            }

            // Update booking team_id, worker_id, number_of_workers, and status
            $booking->update([
                'team_id'           => $newTeam->id,
                'worker_id'         => $firstWorkerId,
                'number_of_workers' => $newTeam->workers->count(),
                'status'            => $newStatus,
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

        // Notify customer
        try {
            if ($booking->customer) {
                $lang = $booking->customer->preferred_language === 'en' ? 'en' : 'ar';
                $title = $lang === 'en' ? 'Team Assignment Updated' : 'تحديث تعيين فريق العمل';
                $body = $lang === 'en'
                    ? "The operational team for your booking {$booking->booking_ref} has been updated."
                    : "تم تحديث فريق العمل التشغيلي لحجزك رقم {$booking->booking_ref}.";

                $booking->customer->notify(new BookingNotification(
                    $title,
                    $body,
                    $booking->id,
                    [
                        'booking_ref'    => $booking->booking_ref,
                        'current_status' => $booking->status,
                        'team_id'        => $newTeam->id,
                    ]
                ));
            }
        } catch (\Exception $ex) {
            Log::error('Notification failure during team assignment: ' . $ex->getMessage());
        }

        $booking->load(['workers', 'histories', 'team.workers']);

        return $this->successResponse(
            new AdminBookingDetailResource($booking),
            __('validation.assign_team.success')
        );
    }

    public function reschedule(RescheduleBookingRequest $request, Booking $booking)
    {
        try {
            $bookingService = app(\App\Service\BookingService::class);
            $updatedBooking = $bookingService->rescheduleBooking($booking->id, auth()->id(), $request->validated(), true);

            // Notify customer
            try {
                if ($updatedBooking->customer) {
                    $newStart = \Carbon\Carbon::parse($request->start_time);
                    $firstDate = collect($request->dates)->sort()->first();
                    $lang = $updatedBooking->customer->preferred_language === 'en' ? 'en' : 'ar';
                    
                    $title = $lang === 'en' ? 'Booking Rescheduled' : 'إعادة جدولة الحجز';
                    $body = $lang === 'en'
                        ? "Your booking {$updatedBooking->booking_ref} has been rescheduled to {$firstDate} at " . $newStart->format('h:i A') . "."
                        : "تمت إعادة جدولة حجزك رقم {$updatedBooking->booking_ref} ليصبح بتاريخ {$firstDate} في تمام الساعة " . $newStart->format('h:i A') . ".";

                    $updatedBooking->customer->notify(new BookingNotification(
                        $title,
                        $body,
                        $updatedBooking->id,
                        [
                            'booking_ref' => $updatedBooking->booking_ref,
                            'booking_date' => $firstDate,
                            'booking_time' => $newStart->format('h:i A'),
                            'workers_count' => $updatedBooking->workers()->count(),
                        ]
                    ));
                }
            } catch (\Exception $notifEx) {
                Log::error('Failed to trigger admin reschedule notification: ' . $notifEx->getMessage());
            }

            return $this->successResponse(
                new AdminBookingDetailResource($updatedBooking->load(['customer', 'service', 'workers', 'address', 'histories.createdBy', 'visits'])),
                __('messages.booking_rescheduled_successfully')
            );
        } catch (\Exception $e) {
            $statusCode = in_array($e->getCode(), [400, 422]) ? $e->getCode() : 500;
            return $this->errorResponse($e->getMessage(), $statusCode);
        }
    }
}
