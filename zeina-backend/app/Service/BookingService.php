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


    public function rescheduleBooking($bookingId, $userId, array $data)
{
    // 1. جلب الحجز مع تفاصيل العمال والقواعد لحماية الأداء ومنع الـ N+1
    $booking = Booking::with(['service.rules', 'workers'])->findOrFail($bookingId);

    // 🔒 حماية وصلاحيات: التأكد أن الحجز يخص المستخدم الحالي
    if ($booking->customer_id !== $userId) {
        throw new \Exception(__('messages.unauthorized'), 403);
    }

    // الحجز يجب أن يكون بحالة Upcoming ليقبل إعادة الجدولة
    if ($booking->status !== 'Upcoming') {
        throw new \Exception(__('messages.booking_not_upcoming', ['status' => $booking->status]), 400);
    }

    // 2. حساب المدة الجديدة ومقارنتها بالقديمة تلافياً لأي تلاعب
    $newStart = Carbon::parse($data['new_start_time']);
    $newEnd = Carbon::parse($data['new_end_time']);
    $newDurationMinutes = (int) $newStart->diffInMinutes($newEnd);

    if ($newDurationMinutes !== $booking->duration_minutes) {
        throw new \Exception(__('messages.reschedule_duration_mismatch'), 422);
    }

    // 3. تطبيق قواعد الـ Business (Service Rules)
    $rules = $booking->service->rules;
    if ($rules) {
        $oldBookingDateTime = Carbon::parse($booking->booking_date . ' ' . $booking->start_time);
        $cutoffHours = $rules->reschedule_cutoff_hours;

        // أ. فحص الـ Cutoff
        if (now()->addHours($cutoffHours)->greaterThan($oldBookingDateTime)) {
            throw new \Exception(__('messages.reschedule_cutoff') ?? 'عذراً، لا يمكن إعادة الجدولة لاقتراب موعد الخدمة.', 400);
        }

        // ب. فحص الحد الأقصى لعدد مرات إعادة الجدولة
        if ($rules->max_changes_per_booking > 0) {
            $changesCount = BookingHistory::where('booking_id', $booking->id)
                ->where('note', 'like', '%إعادة جدولة%')
                ->count();

            if ($changesCount >= $rules->max_changes_per_booking) {
                throw new \Exception(__('messages.reschedule_max_changes', ['max_changes' => $rules->max_changes_per_booking]), 400);
            }
        }

        $newBookingDateTime = Carbon::parse($data['new_date'] . ' ' . $data['new_start_time']);

        // ج. فحص الـ Lead Time الأدنى
        if (now()->addHours($rules->lead_time_min_hours)->greaterThan($newBookingDateTime)) {
            throw new \Exception(__('messages.reschedule_lead_time_min'), 400);
        }

        // د. فحص الـ Lead Time الأقصى
        if (now()->addDays($rules->lead_time_max_days)->endOfDay()->lessThan($newBookingDateTime)) {
            throw new \Exception(__('messages.reschedule_lead_time_max'), 400);
        }
    }

    // 4. فحص الشفتات الرسمية للخدمة في اليوم الجديد
    $dayName = strtolower(Carbon::parse($data['new_date'])->format('l'));
    $schedules = \App\Models\ServiceSchedule::where('service_id', $booking->service_id)
        ->where('day', $dayName)
        ->where('is_active', true)
        ->get();

    if ($schedules->isEmpty()) {
        throw new \Exception(__('messages.service_not_scheduals'), 422);
    }

    $isValidShift = false;
    $newStartStr  = $newStart->format('H:i:s');
    $newEndStr    = $newEnd->format('H:i:s');

    foreach ($schedules as $schedule) {
        $shiftStart = Carbon::parse($schedule->start_time)->format('H:i:s');
        $shiftEnd   = Carbon::parse($schedule->end_time)->format('H:i:s');

        if ($newStartStr >= $shiftStart && $newEndStr <= $shiftEnd) {
            $isValidShift = true;
            break;
        }
    }

    if (!$isValidShift) {
        throw new \Exception(__('messages.service_not_scheduals') ?? 'الموعد المختار يقع خارج ساعات شفتات الخدمة.', 422);
    }

    // 5. استخراج الـ IDs لطاقم العمال الحاليين المرتبطين بالحجز
    $workerIds = $booking->workers->pluck('id')->toArray();

    // 🌟 التعديل الجوهري والحديدى: الفحص عبر جدول الـ BookingAssignment لتفادي التضارب 🌟
    $isAnyWorkerBusy = BookingAssignment::whereIn('worker_id', $workerIds)
        ->where('status', 'active')
        ->where('booking_id', '!=', $booking->id) // استثناء الحجز الحالي نفسه
        ->whereHas('booking', function ($q) use ($data, $newStartStr, $newEndStr) {
            $q->where('booking_date', $data['new_date'])
              ->whereIn('status', ['Upcoming', 'inService'])
              ->where(function ($timeQ) use ($newStartStr, $newEndStr) {
                  $timeQ->where('start_time', '<', $newEndStr)
                        ->where('end_time', '>', $newStartStr);
              });
        })
        ->exists();

    if ($isAnyWorkerBusy) {
        throw new \Exception(__('messages.worker_busy') ?? 'عذراً، أحد العمال المخصصين لحجزك مشغول في هذا الوقت المختار.', 422);
    }

    // 6. بدء المعاملة الآمنة والتحديث بقفل الحماية من الـ Race Condition
    DB::beginTransaction();
    try {
        // تفعيل الـ Pessimistic Lock (lockForUpdate) على جدول الـ Assignments لمنع أي تداخل متزامن
        BookingAssignment::whereIn('worker_id', $workerIds)
            ->where('status', 'active')
            ->where('booking_id', '!=', $booking->id)
            ->whereHas('booking', function ($q) use ($data, $newStartStr, $newEndStr) {
                $q->where('booking_date', $data['new_date'])
                  ->whereIn('status', ['Upcoming', 'inService'])
                  ->where(function ($timeQ) use ($newStartStr, $newEndStr) {
                      $timeQ->where('start_time', '<', $newEndStr)
                            ->where('end_time', '>', $newStartStr);
                  });
            })
            ->lockForUpdate()
            ->exists();

        // تحديث المواعيد بجدول الحجز الرئيسي
        $booking->update([
            'booking_date' => $data['new_date'],
            'start_time'   => $newStartStr,
            'end_time'     => $newEndStr,
        ]);

        // تسجيل العملية في سجل الـ History
        BookingHistory::create([
            'booking_id' => $booking->id,
            'old_status' => 'Upcoming',
            'new_status' => 'Upcoming',
            'note'       => 'عملية إعادة جدولة - تم تعديل الموعد إلى: ' . $data['new_date'] . ' ' . $newStart->format('h:i A'),
            'created_by' => $userId,
        ]);

        DB::commit();
        return $booking;

    } catch (\Exception $transactionEx) {
        DB::rollBack();
        throw $transactionEx;
    }
}



public function cancelBooking($bookingId, $userId)
{
    // جلب الحجز مع القواعد لضمان سرعة معالجة البيانات ومنع الـ N+1
    $booking = Booking::with('service.rules')->findOrFail($bookingId);

    // 1. الحماية والصلاحيات
    if ($booking->customer_id !== $userId) {
        throw new \Exception(__('messages.unauthorized'), 403);
    }

    if ($booking->status !== 'Upcoming') {
        throw new \Exception(__('messages.booking_not_upcoming', ['status' => $booking->status]), 400);
    }

    $rules = $booking->service->rules;
    $cancellationFee = 0;
    $historyNote = 'تم إلغاء الحجز من قبل العميل (إلغاء مجاني كامل).';

    // 2. تطبيق قواعد الإلغاء وحساب الغرامة المخصومة
    if ($rules) {
        $bookingDateTime = Carbon::parse($booking->booking_date . ' ' . $booking->start_time);
        $cutoffHours = $rules->free_cancel_cutoff_hours; 

        // فحص هل الإلغاء تم بعد تخطي المدة المجانية المسموحة؟
        if (now()->addHours($cutoffHours)->greaterThan($bookingDateTime)) {

            // الأكشن 1: منع الإلغاء نهائياً (Block)
            if ($rules->late_cancel_action === 'block') {
                throw new \Exception(__('messages.late_cancel_blocked', ['cutoffHours' => $cutoffHours]), 400);
            }

            // الأكشن 2: حساب الغرامة (Fee)
            if ($rules->late_cancel_action === 'fee') {
                if ($rules->late_cancel_fee_type === 'fixed') {
                    $cancellationFee = (float) $rules->late_cancel_fee_value;
                } elseif ($rules->late_cancel_fee_type === 'percent') {
                    $cancellationFee = ($booking->total_amount * $rules->late_cancel_fee_value) / 100;
                }

                // حماية: الغرامة لا يمكن أن تتخطى القيمة الإجمالية المدفوعة للحجز
                if ($cancellationFee > $booking->total_amount) {
                    $cancellationFee = $booking->total_amount;
                }

                $historyNote = __('messages.late_cancel_fee_applied', ['fee' => number_format($cancellationFee, 2)]) 
                    . ' - تم إلغاء الحجز من قبل العميل وتطبيق غرامة إلغاء متأخر.';
            }
        }
    }

    // 3. حساب قيمة المسترد الصافي للمحفظة
    $refundAmount = $booking->total_amount - $cancellationFee;

    // 4. تنفيذ العمليات داخل قاعدة البيانات (Database Transaction)
    DB::beginTransaction();
    try {
        // أ. تحديث رأس الحجز (يبقى بالحجز فقط قيمة الغرامة المثبتة كإيراد)
        $booking->update([
            'status' => 'cancelled',
            'total_amount' => $cancellationFee, 
        ]);

        // ب. تحرير "جميع" العمال المرتبطين بالحجز دفعة واحدة
        BookingAssignment::where('booking_id', $booking->id)
            ->update([
                'status' => 'cancelled'
            ]);

        // ج. ميكانيكية الكوبون: خفض عداد الاستخدام إن وجد
        if ($booking->coupon_id) {
            $coupon = \App\Models\Coupon::find($booking->coupon_id);
            if ($coupon && $coupon->used_count > 0) {
                $coupon->decrement('used_count');
            }
        }

        // د. تسجيل الحركة التاريخية
        BookingHistory::create([
            'booking_id' => $booking->id,
            'old_status' => 'Upcoming',
            'new_status' => 'cancelled',
            'note'       => $historyNote,
            'created_by' => $userId,
        ]);

        // هـ. ميكانيكية المحفظة: إرجاع المبلغ الصافي للعميل
        if ($refundAmount > 0) {
            $user = auth()->user(); // أو مرير مستخدم الحجز كمعامل إذا رغبت
            $user->deposit($refundAmount, 'Refund - Cancelled Booking', $booking->booking_ref);
        }

        DB::commit();
        return $booking;

    } catch (\Exception $transactionEx) {
        DB::rollBack();
        throw $transactionEx;
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