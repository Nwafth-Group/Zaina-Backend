<?php

namespace App\Service;

use App\Models\Booking;
use App\Models\BookingAssignment;
use App\Models\BookingHistory;
use App\Models\Coupon;
use App\Models\CustomerAddress;
use App\Models\Report;
use App\Models\Review;
use App\Models\Service;
use App\Models\ServicePrice;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BookingService
{
    /**
     * معالجة وإنشاء عملية الحجز بالكامل
     */
    public function createBooking($user, array $data)
    {
        // 1. جلب العناوين والخدمة
        $address = CustomerAddress::with('area')
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->findOrFail($data['address_id']);

        $service = Service::with('duration')->findOrFail($data['service_id']);

        // 2. فحص المحفظة السالبة
        if ($user->wallet_balance < 0) {
            throw new \Exception(__('messages.unpaid_penalties', ['amount' => abs($user->wallet_balance)]), 402);
        }

        // 3. حساب الأوقات والكتل الثابتة
        $periodHours = $service->duration->service_period_hours ?? 2;
        $totalMinutes = $data['periods_count'] * $periodHours * 60;

        $start = Carbon::parse($data['start_time']);
        $end = $start->copy()->addMinutes($totalMinutes);

        $bookingDate = $data['date'];
        $startStr = $start->format('H:i:s');
        $endStr = $end->format('H:i:s');

        // 4. فحص وتأكيد عمال الخدمة وعدم انشغالهم
        $workerIds = $data['worker_ids'];
        $areaId = $address->area_id;
        $cityId = $address->area->city_id;

        foreach ($workerIds as $workerId) {
            $workerExists = $service->workers()
                ->where('workers.id', $workerId)
                ->where('workers.status', 'active')
                ->exists();

            if (!$workerExists) {
                throw new \Exception(__('messages.invalid_worker_for_service'), 422);
            }

            $hasConflict = BookingAssignment::where('worker_id', $workerId)
                ->where('status', 'active')
                ->whereHas('booking', function ($q) use ($bookingDate, $startStr, $endStr) {
                    $q->where('booking_date', $bookingDate)
                        ->whereIn('status', ['Upcoming', 'inService'])
                        ->where(function ($timeQ) use ($startStr, $endStr) {
                            $timeQ->where('start_time', '<', $endStr)->where('end_time', '>', $startStr);
                        });
                })
                ->exists();

            if ($hasConflict) {
                throw new \Exception(__('messages.worker_already_booked'), 422);
            }
        }

        // 5. حساب الأسعار جغرافياً بناءً على النظام الهرمي
        $today = now()->toDateString();
        $servicePrice = ServicePrice::where('service_id', $data['service_id'])
            ->where('city_id', $cityId)
            ->where(function ($query) use ($areaId) {
                $query->where('area_id', $areaId)->orWhereNull('area_id');
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('effective_start')->orWhere('effective_start', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('effective_end')->orWhere('effective_end', '>=', $today);
            })
            ->orderByRaw('area_id IS NULL ASC') 
            ->first();

        if (!$servicePrice) {
            throw new \Exception(__('messages.service_not_available_in_location'), 400);
        }

        $pricePerPeriod = (float) $servicePrice->price_per_service_period;
        $workersCount = count($workerIds);
        $periodsCount = (int) $data['periods_count'];
        $subtotal = $pricePerPeriod * $periodsCount * $workersCount;

        // 6. ميكانيكية الكوبونات
        $discountAmount = 0;
        $coupon = null;

        if (!empty($data['coupon_code'])) {
            $now = now();
            $coupon = Coupon::where('code', $data['coupon_code'])
                ->where('status', 'active')
                ->where('start_date', '<=', $now)
                ->where('end_date', '>=', $now)
                ->first();

            if (!$coupon) {
                throw new \Exception(__('messages.invalid_or_expired_coupon'), 422);
            }

            if ($coupon->type === 'fixed') {
                $discountAmount = (float) $coupon->value;
            } elseif ($coupon->type === 'percentage') {
                $discountAmount = $subtotal * ($coupon->value / 100);
                if ($coupon->max_discount && $discountAmount > $coupon->max_discount) {
                    $discountAmount = (float) $coupon->max_discount;
                }
            }
            if ($discountAmount > $subtotal) {
                $discountAmount = $subtotal;
            }
        }

        $tax = 0;
        $total = ($subtotal - $discountAmount) + $tax;

        // 7. معالجة حساب وفحص طرق الدفع
        $paymentMethod = $data['payment_method'];
        
        if ($paymentMethod === 'points') {
            $totalEarnedPoints = $user->pointTransactions()->where('type', 'earn')->sum('amount');
            $totalRedeemedPoints = $user->pointTransactions()->where('type', 'redeem')->sum('amount');
            $userPointsBalance = $totalEarnedPoints - $totalRedeemedPoints;

            $pointsRequired = (int) ceil($total); 
            if ($userPointsBalance < $pointsRequired) {
                throw new \Exception("عذراً، رصيد نقاطك الحالي ({$userPointsBalance} نقطة) غير كافٍ. يتطلب الحجز {$pointsRequired} نقطة.", 422);
            }
        } 
        elseif ($paymentMethod === 'wallet') {
            if ($user->wallet_balance < $total) {
                if (app()->environment('local', 'testing')) {
                    $deficit = $total - $user->wallet_balance;
                    $user->deposit($deficit, 'Auto Topup - Development Mock Mode');
                } else {
                    throw new \Exception(__('messages.insufficient_balance'), 402);
                }
            }
        }

        // 8. تنفيذ الحفظ الفعلي الآمن داخل الـ Database Transaction بقفل الحماية
        DB::beginTransaction();
        try {
            BookingAssignment::whereIn('worker_id', $workerIds)
                ->where('status', 'active')
                ->whereHas('booking', function ($q) use ($bookingDate, $startStr, $endStr) {
                    $q->where('booking_date', $bookingDate)
                        ->whereIn('status', ['Upcoming', 'inService'])
                        ->where(function ($timeQ) use ($startStr, $endStr) {
                            $timeQ->where('start_time', '<', $endStr)->where('end_time', '>', $startStr);
                        });
                })
                ->lockForUpdate()
                ->exists();

            $booking = Booking::create([
                'booking_ref'      => 'HC-' . rand(100000, 999999),
                'customer_id'      => $user->id,
                'service_id'       => $data['service_id'],
                'address_id'       => $data['address_id'],
                'worker_id'        => $workerIds[0],
                'coupon_id'        => $coupon ? $coupon->id : null,
                'booking_date'     => $bookingDate,
                'start_time'       => $startStr,
                'end_time'         => $endStr,
                'duration_minutes' => $totalMinutes,
                'price_subtotal'   => $subtotal,
                'tax_amount'       => $tax,
                'discount_amount'  => $discountAmount,
                'total_amount'     => $total,
                'status'           => 'Upcoming',
                'payment_status'   => 'paid',
                'payment_method'   => $paymentMethod,
            ]);

            // خصم الدفع المالي
            if ($paymentMethod === 'points') {
                $user->pointTransactions()->create([
                    'type' => 'redeem',
                    'amount' => (int) ceil($total),
                    'title_ar' => 'دفع مقابل حجز برقم مرجعي: ' . $booking->booking_ref,
                    'title_en' => 'Payment for Booking Ref: ' . $booking->booking_ref,
                ]);
            } elseif ($paymentMethod === 'wallet') {
                $user->withdraw($total, 'Payment - Booking', $booking->booking_ref);
            }

            // تعيين العمال
            foreach ($workerIds as $workerId) {
                BookingAssignment::create([
                    'booking_id'  => $booking->id,
                    'worker_id'   => $workerId,
                    'status'      => 'active',
                    'assigned_at' => now(),
                ]);
            }

            if ($coupon) {
                $coupon->increment('used_count');
            }

            BookingHistory::create([
                'booking_id' => $booking->id,
                'new_status' => 'Upcoming',
                'note'       => "تم إنشاء الحجز والدفع بنجاح عبر ({$paymentMethod}). إجمالي المبلغ: {$total} جنيه.",
                'created_by' => $user->id,
            ]);

            DB::commit();
            return $booking;

        } catch (\Exception $transactionEx) {
            DB::rollBack();
            throw $transactionEx;
        }
    }


    public function rescheduleBooking($bookingId, $userId, array $data, $isAdmin = false)
    {
        // 1. Fetch booking with details
        $booking = Booking::with(['service.rules', 'workers', 'visits', 'customer'])->findOrFail($bookingId);

        // Ownership validation (bypassed for admin)
        if (!$isAdmin && $booking->customer_id !== $userId) {
            throw new \Exception(__('messages.unauthorized') ?? 'Unauthorized', 403);
        }

        // Validate
        $validationResult = $this->validateReschedule($booking, $data, $isAdmin);
        $finalWorkers = $validationResult['final_workers'];
        $newStartStr = $validationResult['new_start_str'];
        $newEndStr = $validationResult['new_end_str'];
        $dates = $data['dates'];
        $newStart = Carbon::parse($data['start_time']);

        // DB Transaction
        DB::beginTransaction();
        try {
            // Update Booking Assignments
            BookingAssignment::where('booking_id', $booking->id)
                ->where('status', 'active')
                ->update(['status' => 'replaced']);

            foreach ($finalWorkers as $worker) {
                BookingAssignment::create([
                    'booking_id' => $booking->id,
                    'worker_id' => $worker->id,
                    'status' => 'active',
                    'assigned_at' => now(),
                ]);
            }

            // Sort dates to ensure earliest is main booking_date
            $sortedDates = collect($dates)->sort()->values();

            // Update Booking Header
            $booking->update([
                'booking_date' => $sortedDates[0],
                'start_time' => $newStartStr,
                'end_time' => $newEndStr,
            ]);

            // Update visits
            if ($booking->relationLoaded('visits') && $booking->visits) {
                foreach ($booking->visits as $index => $visit) {
                    if (isset($sortedDates[$index])) {
                        $visit->update([
                            'visit_date' => $sortedDates[$index],
                            'start_time' => $newStartStr,
                            'end_time' => $newEndStr,
                            'status' => 'Upcoming',
                        ]);
                    }
                }
            }

            // Record History log
            BookingHistory::create([
                'booking_id' => $booking->id,
                'old_status' => 'Upcoming',
                'new_status' => 'Upcoming',
                'note'       => $isAdmin 
                    ? 'Rescheduled by admin. New schedule starts on: ' . $sortedDates[0] . ' at ' . $newStart->format('h:i A')
                    : 'Rescheduled by customer. New schedule starts on: ' . $sortedDates[0] . ' at ' . $newStart->format('h:i A'),
                'created_by' => $isAdmin ? $userId : $booking->customer_id,
            ]);

            DB::commit();
            return $booking;

        } catch (\Exception $transactionEx) {
            DB::rollBack();
            throw $transactionEx;
        }
    }

    public function validateReschedule(Booking $booking, array $data, $isAdmin = false)
    {
        $booking->loadMissing(['visits', 'workers', 'service.rules']);

        // Must be Upcoming status
        if ($booking->status !== 'Upcoming') {
            throw new \Exception(__('messages.booking_not_upcoming', ['status' => $booking->status]) ?? 'Booking is not upcoming', 400);
        }

        // Allow reschedule rule check (bypassed for admin)
        $rules = $booking->service->rules;
        if (!$isAdmin && $rules && !$rules->allow_reschedule) {
            throw new \Exception('Rescheduling is not allowed for this service.', 400);
        }

        // Block reschedule if any visit is completed or inService
        if ($booking->relationLoaded('visits') && $booking->visits && $booking->visits->isNotEmpty()) {
            $nonUpcomingCount = $booking->visits->where('status', '!=', 'Upcoming')->count();
            if ($nonUpcomingCount > 0) {
                throw new \Exception('Cannot reschedule a booking that has already started or been completed.', 400);
            }
        }

        // Cut-off timing validation (bypassed for admin)
        if (!$isAdmin) {
            $cutoffHours = $rules ? (int) $rules->reschedule_cutoff_hours : 0;
            
            $earliestVisit = null;
            if ($booking->relationLoaded('visits') && $booking->visits && $booking->visits->isNotEmpty()) {
                $earliestVisit = $booking->visits->sortBy(fn($v) => $v->visit_date . ' ' . $v->start_time)->first();
            }
            
            $earliestDateTimeStr = $earliestVisit 
                ? ($earliestVisit->visit_date . ' ' . $earliestVisit->start_time)
                : ($booking->booking_date . ' ' . $booking->start_time);
                
            $earliestDateTime = Carbon::parse($earliestDateTimeStr);

            if (now()->addHours($cutoffHours)->greaterThan($earliestDateTime)) {
                throw new \Exception(__('messages.reschedule_cutoff') ?? 'Cannot reschedule as the service starts in less than the cutoff limit.', 400);
            }
        }

        // Validate dates count based on visit_type
        $dates = $data['dates'];
        if ($booking->visit_type === 'three_visits_per_week') {
            if (count($dates) !== 3) {
                throw new \Exception('Please select exactly 3 dates for a 3-visit booking.', 400);
            }
            // Check all 3 belong to the same week
            $weeks = collect($dates)->map(fn($d) => Carbon::parse($d)->startOfWeek(Carbon::SATURDAY)->toDateString())->unique();
            if ($weeks->count() > 1) {
                throw new \Exception('All selected dates must belong to the same calendar week.', 400);
            }
        } else {
            if (count($dates) !== 1) {
                throw new \Exception('Please select exactly 1 date for a single visit booking.', 400);
            }
        }

        // Calculate start and end time
        $newStart = Carbon::parse($data['start_time']);
        $newEnd = $newStart->copy()->addMinutes($booking->duration_minutes);
        $newStartStr = $newStart->format('H:i:s');
        $newEndStr = $newEnd->format('H:i:s');

        // Validate service shifts for the new dates
        foreach ($dates as $date) {
            $dayName = strtolower(Carbon::parse($date)->format('l'));
            $schedules = \App\Models\ServiceSchedule::where('service_id', $booking->service_id)
                ->where('day', $dayName)
                ->where('is_active', true)
                ->get();

            if ($schedules->isEmpty()) {
                throw new \Exception("The service does not have active schedules on {$date}.", 422);
            }

            $isValidShift = false;
            foreach ($schedules as $schedule) {
                $shiftStart = Carbon::parse($schedule->start_time)->format('H:i:s');
                $shiftEnd = Carbon::parse($schedule->end_time)->format('H:i:s');
                if ($newStartStr >= $shiftStart && $newEndStr <= $shiftEnd) {
                    $isValidShift = true;
                    break;
                }
            }

            if (!$isValidShift) {
                throw new \Exception("The selected time slot is outside the available shifts for {$date}.", 422);
            }
        }

        // Worker Availability Check & Allocation
        $requiredCount = $booking->relationLoaded('workers') ? $booking->workers->count() : $booking->workers()->count();
        if ($requiredCount === 0) {
            $requiredCount = 1;
        }
        $availableCurrentWorkers = [];

        foreach ($booking->workers as $worker) {
            $isAvailable = true;
            foreach ($dates as $date) {
                if ($this->isWorkerBusyOnDateSlot($worker->id, $date, $newStartStr, $newEndStr, $booking->id)) {
                    $isAvailable = false;
                    break;
                }
            }
            if ($isAvailable && $worker->status === 'active') {
                $availableCurrentWorkers[] = $worker;
            }
        }

        $gap = $requiredCount - count($availableCurrentWorkers);
        $finalWorkers = $availableCurrentWorkers;

        if ($gap > 0) {
            $allEligibleWorkers = $booking->service->workers()->where('workers.status', 'active')->get();
            $availableNewWorkers = [];

            foreach ($allEligibleWorkers as $worker) {
                if (collect($availableCurrentWorkers)->contains('id', $worker->id)) {
                    continue;
                }
                $isAvailable = true;
                foreach ($dates as $date) {
                    if ($this->isWorkerBusyOnDateSlot($worker->id, $date, $newStartStr, $newEndStr, $booking->id)) {
                        $isAvailable = false;
                        break;
                    }
                }
                if ($isAvailable) {
                    $availableNewWorkers[] = $worker;
                }
            }

            if (count($availableNewWorkers) < $gap) {
                throw new \Exception('Cannot reschedule: not enough available workers during the selected times.', 422);
            }

            $randomSelections = collect($availableNewWorkers)->random($gap);
            $finalWorkers = array_merge($finalWorkers, $randomSelections->all());
        }

        return [
            'final_workers' => $finalWorkers,
            'new_start_str' => $newStartStr,
            'new_end_str' => $newEndStr,
        ];
    }

    private function isWorkerBusyOnDateSlot($workerId, $date, $start, $end, $bookingId)
    {
        return BookingAssignment::where('status', 'active')
            ->where('worker_id', $workerId)
            ->where('booking_id', '!=', $bookingId)
            ->where(function ($q) use ($date, $start, $end) {
                $q->whereHas('booking', function ($subQ) use ($date, $start, $end) {
                    $subQ->where('booking_date', $date)
                         ->whereIn('status', ['Upcoming', 'inService'])
                         ->where('start_time', '<', $end)
                         ->where('end_time', '>', $start);
                })
                ->orWhereHas('booking.visits', function ($subQ) use ($date, $start, $end) {
                    $subQ->where('visit_date', $date)
                         ->whereIn('status', ['Upcoming', 'inService'])
                         ->where('start_time', '<', $end)
                         ->where('end_time', '>', $start);
                });
            })
            ->exists();
    }

public function cancelBooking($bookingId, $userId, $reason = null, $isAdmin = false)
{
    // Retrieve the booking with rules, visits, and customer
    $booking = Booking::with(['service.rules', 'visits', 'customer'])->findOrFail($bookingId);

    // 1. Ownership validation (bypassed for admin)
    if (!$isAdmin && $booking->customer_id !== $userId) {
        throw new \Exception(__('messages.unauthorized') ?? 'Unauthorized', 403);
    }

    // 2. Validate cancellation rules
    $this->validateCancellation($booking, $isAdmin);

    // 3. Calculate Refund details
    $refundAmount = 0;
    $refundPoints = 0;
    $refundDestination = null;

    if ($booking->payment_status === 'paid') {
        if ($booking->payment_method === 'points') {
            $refundPoints = (int) $booking->paid_points;
            $refundDestination = 'points';
        } else {
            $refundAmount = (float) $booking->total_amount;
            $refundDestination = 'wallet';
        }
    }

    // 4. DB Transaction
    DB::beginTransaction();
    try {
        // Update Booking Header
        $booking->update([
            'status' => 'cancelled',
            'cancelled_by' => $isAdmin ? 'admin' : 'customer',
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
            'refund_amount' => $refundAmount,
            'refund_points' => $refundPoints,
            'refund_destination' => $refundDestination,
            'refund_status' => ($refundDestination ? 'completed' : null),
        ]);

        // Update booking visits status to cancelled
        if ($booking->relationLoaded('visits') && $booking->visits) {
            foreach ($booking->visits as $visit) {
                $visit->update(['status' => 'cancelled']);
            }
        }

        // We keep the assignments active so the client knows what workers were selected,
        // but since the booking status is 'cancelled', these workers are free to be assigned elsewhere.

        // Adjust Coupon
        if ($booking->coupon_id) {
            $coupon = \App\Models\Coupon::find($booking->coupon_id);
            if ($coupon && $coupon->used_count > 0) {
                $coupon->decrement('used_count');
            }
        }

        // Log history change
        BookingHistory::create([
            'booking_id' => $booking->id,
            'old_status' => 'Upcoming',
            'new_status' => 'cancelled',
            'note'       => $isAdmin ? 'Cancelled by admin. Reason: ' . $reason : 'Cancelled by customer. Reason: ' . $reason,
            'created_by' => $isAdmin ? $userId : $booking->customer_id,
        ]);

        // Refund points or wallet
        if ($booking->payment_status === 'paid') {
            $customer = $booking->customer;
            if ($refundDestination === 'points') {
                $customer->pointTransactions()->create([
                    'type' => 'earn',
                    'amount' => $refundPoints,
                    'title_ar' => 'استرداد نقاط للحجز رقم: ' . $booking->booking_ref,
                    'title_en' => 'Refund points for Booking Ref: ' . $booking->booking_ref,
                ]);
            } elseif ($refundDestination === 'wallet') {
                $customer->deposit($refundAmount, 'Refund - Cancelled Booking', $booking->booking_ref);
            }
        }

        DB::commit();
        return $booking;

    } catch (\Exception $transactionEx) {
        DB::rollBack();
        throw $transactionEx;
    }
}

public function validateCancellation(Booking $booking, $isAdmin = false)
{
    $booking->loadMissing(['visits', 'service.rules']);

    // Can only cancel Upcoming bookings
    if ($booking->status !== 'Upcoming') {
        throw new \Exception(__('messages.booking_not_upcoming', ['status' => $booking->status]) ?? 'Booking is not upcoming', 400);
    }

    // Completed visit constraint (blocks both customer and admin)
    if ($booking->relationLoaded('visits') && $booking->visits && $booking->visits->isNotEmpty()) {
        $completedVisits = $booking->visits->where('status', 'completed')->count();
        if ($completedVisits > 0) {
            throw new \Exception('Cannot cancel a booking that has already completed visits.', 400);
        }
    }

    $rules = $booking->service->rules;

    // Cut-off time validation (bypassed for admin)
    if (!$isAdmin && $rules) {
        if (!$rules->allow_cancel) {
            throw new \Exception('Cancellation is not allowed for this service.', 400);
        }

        $earliestVisit = null;
        if ($booking->relationLoaded('visits') && $booking->visits && $booking->visits->isNotEmpty()) {
            $earliestVisit = $booking->visits->sortBy(fn($v) => $v->visit_date . ' ' . $v->start_time)->first();
        }
        
        $earliestDateTimeStr = $earliestVisit 
            ? ($earliestVisit->visit_date . ' ' . $earliestVisit->start_time)
            : ($booking->booking_date . ' ' . $booking->start_time);
            
        $bookingDateTime = Carbon::parse($earliestDateTimeStr);
        $cutoffHours = (int) $rules->free_cancel_cutoff_hours;

        if (now()->addHours($cutoffHours)->greaterThan($bookingDateTime)) {
            throw new \Exception(__('messages.late_cancel_blocked', ['cutoffHours' => $cutoffHours]) ?? 'Cannot cancel within cutoff limit.', 400);
        }
    }
}

public function reportBooking($bookingId, $userId, array $data)
{
    $booking = Booking::findOrFail($bookingId);

    // 1. حماية وصلاحيات
    if ($booking->customer_id !== $userId) {
        throw new \Exception(__('messages.unauthorized_report'), 403);
    }

    // البلاغ يقبل فقط لو كانت الخدمة بدأت أو انتهت فعلياً
    if (!in_array($booking->status, ['inService', 'completed'])) {
        throw new \Exception(__('messages.invalid_booking_status_for_report'), 400);
    }

    // 2. منع تكرار البلاغ لنفس الحجز
    $existingReport = Report::where('booking_id', $booking->id)->exists();
    if ($existingReport) {
        throw new \Exception(__('messages.existing_report'), 400);
    }

    // 3. إنشاء ريكورد البلاغ
    $report = Report::create([
        'booking_id'  => $booking->id,
        'customer_id' => $userId,
        'reason'      => $data['reason'],
        'description' => $data['description'] ?? null,
    ]);

    return $report;
}



public function reviewBooking($bookingId, $userId, array $data)
{
    $booking = Booking::findOrFail($bookingId);

    // 1. حماية وصلاحيات العميل والحالة
    if ($booking->customer_id !== $userId) {
        throw new \Exception(__('messages.unauthorized_review'), 403);
    }

    if ($booking->status !== 'completed') {
        throw new \Exception(__('messages.invalid_booking_status_for_review'), 400);
    }

    DB::beginTransaction();
    try {
        // 2. تقييم الخدمة (Service) - يمنع التكرار عبر updateOrCreate
        Review::updateOrCreate(
            [
                'booking_id'      => $booking->id,
                'customer_id'     => $userId,
                'reviewable_type' => \App\Models\Service::class,
                'reviewable_id'   => $booking->service_id,
            ],
            [
                'rating'  => $data['service_rating'],
                'comment' => $data['service_comment'] ?? null,
            ]
        );

        // 3. تقييم العمال (Workers) المخصصين فعلياً للحجز
        foreach ($data['workers'] as $workerData) {
            // 🌟 تفعيل كود الحماية: نتحقق من جدول الـ BookingAssignment 🌟
            $isAssigned = BookingAssignment::where('booking_id', $booking->id)
                ->where('worker_id', $workerData['worker_id'])
                ->exists();

            if ($isAssigned) {
                Review::updateOrCreate(
                    [
                        'booking_id'      => $booking->id,
                        'customer_id'     => $userId,
                        'reviewable_type' => \App\Models\Worker::class,
                        'reviewable_id'   => $workerData['worker_id'],
                    ],
                    [
                        'rating'  => $workerData['rating'],
                        'comment' => $workerData['comment'] ?? null,
                    ]
                );
            }
        }

        // 4. تحديث متوسط التقييمات في جداول الـ Services والـ Workers
        $this->updateAverageRating(\App\Models\Service::class, $booking->service_id);
        
        foreach ($data['workers'] as $workerData) {
            $this->updateAverageRating(\App\Models\Worker::class, $workerData['worker_id']);
        }

        DB::commit();
        return true;

    } catch (\Exception $transactionEx) {
        DB::rollBack();
        throw $transactionEx;
    }
}

/**
 * دالة مساعدة لتحديث متوسط التقييم بشكل ديناميكي
 */
private function updateAverageRating($modelClass, $modelId)
{
    $avgRating = Review::where('reviewable_type', $modelClass)
        ->where('reviewable_id', $modelId)
        ->avg('rating');

    $modelClass::where('id', $modelId)->update([
        'avg_rating' => round($avgRating, 1) // مثال: 4.7
    ]);
}









}