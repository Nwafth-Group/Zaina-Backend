<?php

namespace App\Service;

use App\Models\Booking;
use App\Models\BookingAssignment;
use App\Models\BookingHistory;
use App\Models\Coupon;
use App\Models\CustomerAddress;
use App\Models\Report;
use App\Models\ReportHistory;
use App\Models\Review;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\ServiceSchedule;
use App\Models\User;
use App\Models\Worker;
use App\Notifications\ReportNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class BookingService
{
    /**
     * معالجة وإنشاء عملية الحجز بالكامل
     */
    public function createBooking($user, array $data)
    {
        // 1. جلب العناوين والخدمة
        $address = CustomerAddress::where('user_id', $user->id)
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
        $pricePerPeriod = (float) $service->base_price;
        $workersCount = count($workerIds);
        $periodsCount = (int) $data['periods_count'];
        if ($service->service_code === 'DEEP_CLEANING') {
            $subtotal = $pricePerPeriod * $periodsCount;
        } else {
            $subtotal = $pricePerPeriod * $periodsCount * $workersCount;
        }

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
        } elseif ($paymentMethod === 'wallet') {
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
                'booking_ref' => 'HC-' . rand(100000, 999999),
                'customer_id' => $user->id,
                'service_id' => $data['service_id'],
                'address_id' => $data['address_id'],
                'worker_id' => $workerIds[0],
                'coupon_id' => $coupon ? $coupon->id : null,
                'booking_date' => $bookingDate,
                'start_time' => $startStr,
                'end_time' => $endStr,
                'duration_minutes' => $totalMinutes,
                'price_subtotal' => $subtotal,
                'tax_amount' => $tax,
                'discount_amount' => $discountAmount,
                'total_amount' => $total,
                'status' => 'Upcoming',
                'payment_status' => 'paid',
                'payment_method' => $paymentMethod,
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
                    'booking_id' => $booking->id,
                    'worker_id' => $workerId,
                    'status' => 'active',
                    'assigned_at' => now(),
                ]);
            }

            if ($coupon) {
                $coupon->increment('used_count');
            }

            BookingHistory::create([
                'booking_id' => $booking->id,
                'new_status' => 'Upcoming',
                'note' => "تم إنشاء الحجز والدفع بنجاح عبر ({$paymentMethod}). إجمالي المبلغ: {$total} جنيه.",
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
                'note' => $isAdmin
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
            throw new \Exception(__('messages.booking_not_upcoming', ['status' => $booking->status]), 400);
        }

        // Allow reschedule rule check (bypassed for admin)
        $rules = $booking->service->rules;
        if (!$isAdmin && $rules && isset($rules->allow_reschedule) && !$rules->allow_reschedule) {
            throw new \Exception(__('messages.rescheduling_not_allowed'), 400);
        }

        // Block reschedule if any visit is completed or inService
        if ($booking->relationLoaded('visits') && $booking->visits && $booking->visits->isNotEmpty()) {
            $nonUpcomingCount = $booking->visits->where('status', '!=', 'Upcoming')->count();
            if ($nonUpcomingCount > 0) {
                throw new \Exception(__('messages.booking_started_or_completed'), 400);
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
                throw new \Exception(__('messages.reschedule_cutoff_exceeded', ['hours' => $cutoffHours]), 400);
            }
        }

        // Validate dates count based on visit_type
        $dates = $data['dates'];
        if ($booking->visit_type === 'three_visits_per_week') {
            if (count($dates) !== 3) {
                throw new \Exception(__('messages.select_exactly_3_dates'), 400);
            }
            // Check all 3 belong to the same week
            $weeks = collect($dates)->map(fn($d) => Carbon::parse($d)->startOfWeek(Carbon::SATURDAY)->toDateString())->unique();
            if ($weeks->count() > 1) {
                throw new \Exception(__('messages.dates_must_belong_to_same_week'), 400);
            }
        } else {
            if (count($dates) !== 1) {
                throw new \Exception(__('messages.select_exactly_1_date'), 400);
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
            $schedules = ServiceSchedule::where('service_id', $booking->service_id)
                ->where('day', $dayName)
                ->where('is_active', true)
                ->get();

            if ($schedules->isEmpty()) {
                throw new \Exception(__('messages.service_no_active_schedules', ['date' => $date]), 422);
            }

            $isValidShift = false;
            foreach ($schedules as $schedule) {
                $shiftStart = Carbon::parse($schedule->start_time)->format('H:i:s');
                $shiftEnd = Carbon::parse($schedule->end_time)->format('H:i:s');
                if ($newStartStr >= $shiftStart && $newStartStr <= $shiftEnd) {
                    $isValidShift = true;
                    break;
                }
            }

            if (!$isValidShift) {
                throw new \Exception(__('messages.time_slot_outside_shifts', ['date' => $date]), 422);
            }
        }

        // Worker Availability Check & Allocation (Auto-reallocate random workers if assigned are unavailable)
        $targetCount = max($booking->workers_count, $booking->workers->count(), 1);
        $availableAssigned = collect();

        foreach ($booking->workers as $worker) {
            if ($worker->status === 'active') {
                $isBusy = false;
                foreach ($dates as $date) {
                    if ($this->isWorkerBusyOnDateSlot($worker->id, $date, $newStartStr, $newEndStr, $booking->id)) {
                        $isBusy = true;
                        break;
                    }
                }
                if (!$isBusy) {
                    $availableAssigned->push($worker);
                }
            }
        }

        $finalWorkers = $availableAssigned;
        $needed = $targetCount - $finalWorkers->count();

        if ($needed > 0) {
            $serviceWorkers = $booking->service->workers()->where('status', 'active')->get();
            $otherAvailableWorkers = collect();

            foreach ($serviceWorkers as $worker) {
                if ($finalWorkers->contains('id', $worker->id)) {
                    continue;
                }
                $isBusy = false;
                foreach ($dates as $date) {
                    if ($this->isWorkerBusyOnDateSlot($worker->id, $date, $newStartStr, $newEndStr, $booking->id)) {
                        $isBusy = true;
                        break;
                    }
                }
                if (!$isBusy) {
                    $otherAvailableWorkers->push($worker);
                }
            }

            if ($otherAvailableWorkers->count() < $needed) {
                throw new \Exception(__('messages.worker_not_available') ?? 'No available worker was found for the selected reschedule date and time.', 422);
            }

            $chosenRandom = $otherAvailableWorkers->random($needed);
            $finalWorkers = $finalWorkers->merge($chosenRandom);
        }

        return [
            'final_workers' => $finalWorkers->all(),
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
                $coupon = Coupon::find($booking->coupon_id);
                if ($coupon && $coupon->used_count > 0) {
                    $coupon->decrement('used_count');
                }
            }

            // Log history change
            BookingHistory::create([
                'booking_id' => $booking->id,
                'old_status' => 'Upcoming',
                'new_status' => 'cancelled',
                'note' => $isAdmin ? 'Cancelled by admin. Reason: ' . $reason : 'Cancelled by customer. Reason: ' . $reason,
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

            // Notify admin or customer based on who initiated the cancellation
            try {
                if ($isAdmin) {
                    if ($booking->customer) {
                        $lang = $booking->customer->preferred_language === 'en' ? 'en' : 'ar';
                        $title = $lang === 'en' ? 'Booking Cancelled' : 'تم إلغاء الحجز';
                        $body = $lang === 'en'
                            ? "Your booking {$booking->booking_ref} has been cancelled by Admin."
                            : "تم إلغاء حجزك رقم {$booking->booking_ref} بواسطة الإدارة.";

                        $booking->customer->notify(new \App\Notifications\BookingNotification(
                            $title,
                            $body,
                            $booking->id,
                            [
                                'booking_ref' => $booking->booking_ref,
                                'booking_date' => $booking->booking_date,
                                'current_status' => 'cancelled',
                            ]
                        ));
                    }
                } else {
                    $admins = \App\Models\User::where('type', 'admin')->get();
                    foreach ($admins as $admin) {
                        $adminTitle = app()->getLocale() === 'ar' ? 'إلغاء حجز من العميل' : 'Customer Booking Cancelled';
                        $admin->notify(new \App\Notifications\BookingNotification(
                            $adminTitle,
                            "Customer {$booking->customer?->full_name} has cancelled booking ref: {$booking->booking_ref}. Reason: " . ($reason ?? 'None'),
                            $booking->id,
                            [
                                'booking_ref' => $booking->booking_ref,
                                'customer_name' => $booking->customer?->full_name,
                                'current_status' => 'cancelled',
                            ]
                        ));
                    }
                }
            } catch (\Exception $ex) {
                Log::error('Cancellation notification failure: ' . $ex->getMessage());
            }

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

        // Completed or inService visit constraint (blocks both customer and admin)
        if ($booking->relationLoaded('visits') && $booking->visits && $booking->visits->isNotEmpty()) {
            $nonUpcomingCount = $booking->visits->where('status', '!=', 'Upcoming')->count();
            if ($nonUpcomingCount > 0) {
                throw new \Exception('Cannot cancel a booking that is in service or has completed visits.', 400);
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

        // البلاغ يقبل فقط لو كانت الخدمة مكتملة أو قيد التنفيذ
        $statusLower = strtolower($booking->status);
        if ($statusLower !== 'completed' && $statusLower !== 'inservice') {
            throw new \Exception(__('messages.invalid_booking_status_for_report') ?? 'Reports are only allowed for in-service or completed bookings.', 400);
        }

        // 2. منع تكرار البلاغ المفتوح لنفس الحجز ونفس السبب
        $existingReport = Report::query()->where('booking_id', $booking->id)
            ->where('reason', $data['reason'])
            ->where('status', '!=', 'resolved')
            ->exists();
        if ($existingReport) {
            throw new \Exception(__('messages.open_report_exists'), 400);
        }

        // 3. إنشاء رقم تذكرة فريد
        $reportNumber = 'REP-' . date('Ymd') . '-' . strtoupper(Str::random(4));

        // 4. إنشاء ريكورد البلاغ
        $report = Report::create([
            'booking_id' => $booking->id,
            'customer_id' => $userId,
            'report_number' => $reportNumber,
            'service_id' => $booking->service_id,
            'worker_id' => $data['worker_id'] ?? null,
            'reason' => $data['reason'],
            'description' => $data['description'] ?? null,
            'status' => 'submitted',
        ]);

        // 5. تسجيل حركة الحالة
        ReportHistory::create([
            'report_id' => $report->id,
            'old_status' => null,
            'new_status' => 'submitted',
            'created_by' => $userId,
        ]);

        // 6. إرسال إشعار للمسؤولين
        try {
            $admins = User::whereIn('type', ['super_admin', 'operation'])->get();
            Notification::send($admins, new ReportNotification($report, 'submitted'));
        } catch (\Exception $ex) {
            Log::error('Failed to send report notification to admins: ' . $ex->getMessage());
        }

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

        // منع تكرار التقييم للحجز بأكمله
        $alreadyReviewed = Review::where('booking_id', $booking->id)
            ->where('customer_id', $userId)
            ->exists();
        if ($alreadyReviewed) {
            throw new \Exception(__('messages.booking_already_reviewed'), 400);
        }

        DB::beginTransaction();
        try {
            // 2. تقييم الخدمة (Service) - يضاف بحالة قيد المراجعة pending
            Review::create([
                'booking_id' => $booking->id,
                'customer_id' => $userId,
                'reviewable_type' => Service::class,
                'reviewable_id' => $booking->service_id,
                'rating' => $data['service_rating'],
                'comment' => $data['service_comment'] ?? null,
                'status' => 'pending',
            ]);

            // 3. تقييم العمال (Workers) المخصصين فعلياً للحجز
            foreach ($data['workers'] as $workerData) {
                $isAssigned = BookingAssignment::where('booking_id', $booking->id)
                    ->where('worker_id', $workerData['worker_id'])
                    ->exists();

                if ($isAssigned) {
                    Review::create([
                        'booking_id' => $booking->id,
                        'customer_id' => $userId,
                        'reviewable_type' => Worker::class,
                        'reviewable_id' => $workerData['worker_id'],
                        'rating' => $workerData['rating'],
                        'comment' => $workerData['comment'] ?? null,
                        'status' => 'pending',
                    ]);
                }
            }

            \App\Service\LoyaltyPointService::awardPointsForReview($booking);

            DB::commit();

            return true;

        } catch (\Exception $transactionEx) {
            DB::rollBack();
            throw $transactionEx;
        }
    }

    /**
     * دالة مساعدة لتحديث متوسط التقييم بشكل ديناميكي (للتقييمات المعتمدة فقط)
     */
    public static function updateAverageRating($modelClass, $modelId)
    {
        $avgRating = Review::where('reviewable_type', $modelClass)
            ->where('reviewable_id', $modelId)
            ->where('status', 'approved')
            ->avg('rating');

        $modelClass::where('id', $modelId)->update([
            'avg_rating' => round((float) ($avgRating ?: 0), 2),
        ]);
    }
}
