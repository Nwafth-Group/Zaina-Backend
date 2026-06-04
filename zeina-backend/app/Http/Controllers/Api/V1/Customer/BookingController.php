<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\RescheduleBookingRequest;
use App\Http\Requests\Api\V1\Customer\RportBookingRequest;
use App\Http\Requests\Api\V1\Customer\StoreBookingRequest;
use App\Http\Resources\Api\V1\Booking\BookingResource;
use App\Models\Booking;
use App\Models\BookingAssignment;
use App\Models\BookingHistory;
use App\Models\Coupon;
use App\Models\CustomerAddress;
use App\Models\Report;
use App\Models\Review;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\ServiceRule;
use App\Models\ServiceSchedule;
use App\Models\Worker;
use App\Traits\ApiResponseTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    /**
     * Display a listing of the resource.
    */
    use ApiResponseTrait;
  public function index(Request $request)
    {
        $status = $request->query('status'); // All, Upcoming, inService, completed, cancelled

        $query = Booking::with(['service.media', 'address'])
            ->where('customer_id', auth()->id());

        // فلترة الحالة إذا لم تكن "All" أو فارغة
        if (!empty($status) && strtolower($status) !== 'all') {
            // تحويل أول حرف لكبير لتطابق الـ Enum في الداتابيز إذا لزم الأمر
            $query->where('status', $status); 
        }

        // ترتيب الحجوزات (الأقرب تاريخاً أولاً)
        $bookings = $query->orderBy('booking_date', 'desc')
                          ->orderBy('start_time', 'desc')
                          ->paginate(10);

         
        return $this->successResponse(BookingResource::collection($bookings),__('messages.bookings_fetched_successfully'));
            
    }

    // 2. جلب تفاصيل حجز معين (شاشة Booking Details)
    public function show(Booking $booking)
    {
        $booking->load(['service.media', 'service.rules', 'address.city', 'address.area', 'worker']);
        
        // حماية: التأكد أن الحجز يتبع للمستخدم الحالي
        // if ($booking->customer_id !== auth()->id()) {

        //     return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        // } 

            return $this->successResponse(new BookingResource($booking), __('messages.booking_details_fetched_successfully'));

      
    }

    /**
     * Store a newly created resource in storage.
     */
  public function store(StoreBookingRequest $request) 
{
    $user = auth()->user();
    $service = Service::with('duration')->findOrFail($request->service_id);
    
    if ($user->wallet_balance < 0) {
        return $this->errorResponse(__('messages.unpaid_penalties', ['amount' => abs($user->wallet_balance)]), 402);
    }

    // 2. حساب وقت البداية والنهاية الكلي بناءً على عدد الكتل الثابتة
    $periodHours = $service->duration->service_period_hours ?? 2; 
    $totalMinutes = $request->periods_count * $periodHours * 60;

    $start = Carbon::parse($request->start_time);
    $end = $start->copy()->addMinutes($totalMinutes);

    // 3. فحص أمان متزامن (Pessimistic Lock) لمنع الـ Race Condition
    $isAnyWorkerBusy = Booking::whereIn('worker_id', $request->worker_ids)
        ->where('booking_date', $request->date)
        ->whereIn('status', ['Upcoming', 'inService'])
        ->where(function ($query) use ($start, $end) {
            $query->where('start_time', '<', $end->format('H:i:s'))
                  ->where('end_time', '>', $start->format('H:i:s'));
        })
        ->lockForUpdate() 
        ->exists();

    if ($isAnyWorkerBusy) {
        return $this->errorResponse(__('messages.worker_busy') ?? 'عذراً، أحد العمال المختارين تم حجزه في هذا الوقت قبل قليل.', 422);
    }

    // 4. حساب الأسعار بناءً على النطاق الجغرافي لعنوان العميل
    $address = CustomerAddress::with('area')->findOrFail($request->address_id);
    $areaId = $address->area_id;
    $cityId = $address->area->city_id;
    $today = now()->toDateString();

    $servicePrice = ServicePrice::where('service_id', $request->service_id)
        ->where(function ($query) use ($areaId, $cityId) {
            $query->where(function ($q) use ($areaId) {
                $q->where('pricing_scope', 'area')->where('area_id', $areaId);
            })->orWhere(function ($q) use ($cityId) {
                $q->where('pricing_scope', 'city')->where('city_id', $cityId);
            });
        })
        ->where(function ($q) use ($today) {
            $q->whereNull('effective_start')->orWhere('effective_start', '<=', $today);
        })
        ->where(function ($q) use ($today) {
            $q->whereNull('effective_end')->orWhere('effective_end', '>=', $today);
        })
        ->orderByRaw("FIELD(pricing_scope, 'area', 'city')")
        ->first();

    if (!$servicePrice) {
        return $this->errorResponse(__('messages.service_not_available'), 400);
    }

    // 5. معادلة الحسبة الأساسية للتسعير الجذري 
    $pricePerPeriod = (float) $servicePrice->price_per_service_period; 
    $workersCount = count($request->worker_ids);
    $periodsCount = (int) $request->periods_count;

    $subtotal = $pricePerPeriod * $periodsCount * $workersCount;
    
    // 🌟 خطوة الكوبون الجديدة: حساب الخصومات ديناميكياً 🌟
    $discountAmount = 0;
    $coupon = null;

    if ($request->filled('coupon_code')) {
        $now = now();
        // جلب الكوبون والتحقق من صلاحيته ونشاطه زمنياً
        $coupon = Coupon::where('code', $request->coupon_code)
            ->where('status', 'active')
            ->where('start_date', '<=', $now)
            ->where('end_date', '>=', $now)
            ->first();

        if (!$coupon) {
            return $this->errorResponse(__('messages.invalid_or_expired_coupon') ?? 'كود الكوبون غير صحيح أو منتهي الصلاحية.', 422);
        }

        // أ. فحص العميل (User Scope) وعمّا إذا تخطى الحد الشخصي للاستخدام
        if (!$coupon->is_for_all_users) {
            $isUserEligible = $coupon->users()->where('user_id', $user->id)->exists();
            if (!$isUserEligible) {
                return $this->errorResponse(__('messages.coupon_not_for_you') ?? 'هذا الكوبون غير مخصص لحسابك الشخصي.', 422);
            }
        }

        // ب. فحص كم مرة استخدم هذا العميل تحديداً هذا الكوبون في قاعدة البيانات
        $userUsageCount = Booking::where('customer_id', $user->id)
            ->where('coupon_id', $coupon->id)
            ->whereIn('status', ['Upcoming', 'inService', 'completed'])
            ->count();

        if ($userUsageCount >= $coupon->user_usage_limit) {
            return $this->errorResponse(__('messages.coupon_usage_limit_reached') ?? 'لقد استنفدت الحد المسموح لك لاستخدام هذا الكوبون.', 422);
        }

        // ج. فحص إجمالي عدد مرات الاستخدام الكلي للنظام
        if ($coupon->usage_limit && $coupon->used_count >= $coupon->usage_limit) {
            return $this->errorResponse(__('messages.coupon_fully_consumed') ?? 'عذراً، انتهت صلاحية استخدام هذا الكوبون بالكامل.', 422);
        }

        // د. فحص الخدمة المشمولة (Service Scope)
        if (!$coupon->is_for_all_services) {
            $isServiceEligible = $coupon->services()->where('service_id', $service->id)->exists();
            if (!$isServiceEligible) {
                return $this->errorResponse(__('messages.coupon_does_not_include_service') ?? 'هذا الكوبون لا يشمل الخدمة المطلوبة.', 422);
            }
        }

        // هـ. فحص الحد الأدنى لقيمة الحجز (Min Booking Amount)
        if ($subtotal < $coupon->min_booking_amount) {
            return $this->errorResponse(__('messages.coupon_min_amount_not_met', ['amount' => $coupon->min_booking_amount]) ?? 'قيمة الحجز أقل من الحد الأدنى لتفعيل هذا الكوبون.', 422);
        }

        // و. حساب قيمة الخصم بناءً على نوع الكوبون
        if ($coupon->type === 'fixed') {
            $discountAmount = (float) $coupon->value;
        } elseif ($coupon->type === 'percentage') {
            $discountAmount = $subtotal * ($coupon->value / 100);
            // تطبيق سقف الخصم الأعلى إن وجد
            if ($coupon->max_discount && $discountAmount > $coupon->max_discount) {
                $discountAmount = (float) $coupon->max_discount;
            }
        }

        // حماية: التأكد أن الخصم لا يتجاوز قيمة الحجز الإجمالية
        if ($discountAmount > $subtotal) {
            $discountAmount = $subtotal;
        }
    }

    $tax = 0; 
    $total = ($subtotal - $discountAmount) + $tax;

    // 6. فحص الرصيد الكافي قبل إتمام الحجز
    if ($user->wallet_balance < $total) {
        return $this->errorResponse(__('messages.insufficient_balance') ?? 'عذراً، رصيد المحفظة غير كافٍ.', 402);
    }

    DB::beginTransaction();
    try {
        // 7. إنشاء الحجز الرئيسي في جدول bookings متضمناً الخصومات والكوبون المطبق
        $booking = Booking::create([
            'booking_ref'      => 'HC-' . rand(100000, 999999),
            'customer_id'      => $user->id,
            'service_id'       => $request->service_id,
            'address_id'       => $request->address_id,
            'worker_id'        => $request->worker_ids[0], 
            'coupon_id'        => $coupon ? $coupon->id : null, // 🌟 تخزين الكوبون المطبق كمرجع
            'booking_date'     => $request->date,
            'start_time'       => $start->format('H:i:s'),
            'end_time'         => $end->format('H:i:s'),
            'duration_minutes' => $totalMinutes,
            'price_subtotal'   => $subtotal,
            'tax_amount'       => $tax,
            'discount_amount'  => $discountAmount, // 🌟 تسجيل قيمة الخصم الفعلي
            'total_amount'     => $total,
            'status'           => 'Upcoming',
            'payment_status'   => 'paid', 
        ]);

        // 8. تسجيل جميع العمال في جدول الـ BookingAssignments
        foreach ($request->worker_ids as $workerId) {
            BookingAssignment::create([
                'booking_id' => $booking->id,
                'worker_id'  => $workerId,
                'status'     => 'active',
            ]);
        }

        // 9. إذا تم استخدام كوبون، نقوم بتحديث عدّاد الاستخدام الكلي للكوبون بزيادة 1
        if ($coupon) {
            $coupon->increment('used_count');
        }

        // 10. تسجيل التاريخ (History)
        BookingHistory::create([
            'booking_id' => $booking->id,
            'new_status' => 'Upcoming',
            'note'       => $coupon 
                ? "تم إنشاء الحجز وتطبيق الكوبون ({$coupon->code}) بخصم قدره {$discountAmount} جنيه."
                : "تم إنشاء الحجز بنظام الكتل الثابتة والدفع من المحفظة لـ {$workersCount} عمال.",
            'created_by' => $user->id,
        ]);

        // 11. الخصم من المحفظة وتسجيل الحركة المالية بالـ Net Total المخصوم
        $user->withdraw($total, 'Payment - Booking', $booking->booking_ref);
        
        DB::commit();
        return $this->successResponse(new BookingResource($booking), __('messages.booking_created_successfully'), 201);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json(['success' => false, 'message' => 'حدث خطأ أثناء معالجة الحجز.', 'error' => $e->getMessage()], 500);
    }
}
    // public function update(Request $request, string $id)
    // {
    //     //
    // }

    // /**
    //  * Remove the specified resource from storage.
    //  */
    // public function destroy(string $id)
    // {
    //     //
    // }

    public function reschedule(RescheduleBookingRequest $request, $id)
    {
        $booking = Booking::with('service.rules')->findOrFail($id);
        // حماية وصلاحيات
        if ($booking->customer_id !== auth()->id()) {
            return $this->errorResponse(__('messages.unauthorized'), 403);
        }
        if ($booking->status !== 'Upcoming') {
            return $this->errorResponse(__('messages.booking_not_upcoming'), 400);
        }
        // 1. حساب المدة الجديدة ومقارنتها بالقديمة (منع تغيير الساعات)
        $newStart = Carbon::parse($request->new_start_time);
        $newEnd = Carbon::parse($request->new_end_time);
        $newDurationMinutes = (int) $newStart->diffInMinutes($newEnd);

        if ($newDurationMinutes !== $booking->duration_minutes) {
            return $this->errorResponse(__('messages.reschedule_duration_mismatch'), 422);
        }
        // 2. تطبيق قواعد الـ Business (Service Rules)
        $rules = $booking->service->rules;
        if ($rules) {
            $oldBookingDateTime = Carbon::parse($booking->booking_date . ' ' . $booking->start_time);
            $cutoffHours = $rules->reschedule_cutoff_hours;
            
            if (now()->addHours($cutoffHours)->greaterThan($oldBookingDateTime)) {

                return $this->errorResponse(__('messages.reschedule_cutoff'), 400);
            }

            if ($rules->max_changes_per_booking > 0) {
                $changesCount = BookingHistory::where('booking_id', $booking->id)
                    ->where('note', 'like', '%إعادة جدولة%')
                    ->count();

                if ($changesCount >= $rules->max_changes_per_booking) {
                    return $this->errorResponse(__('messages.reschedule_max_changes', ['max_changes' => $rules->max_changes_per_booking]), 400);
                }
            }

            $newBookingDateTime = Carbon::parse($request->new_date . ' ' . $request->new_start_time);
            
            if (now()->addHours($rules->lead_time_min_hours)->greaterThan($newBookingDateTime)) {
                return $this->errorResponse(__('messages.reschedule_lead_time_min'), 400);
            }

            if (now()->addDays($rules->lead_time_max_days)->endOfDay()->lessThan($newBookingDateTime)) {
                return $this->errorResponse(__('messages.reschedule_lead_time_max'), 400);
            }
        }

        // 3. فحص الشفتات للموعد الجديد
        $dayName = strtolower(Carbon::parse($request->new_date)->format('l'));
        $schedules = ServiceSchedule::where('service_id', $booking->service_id)
            ->where('day', $dayName)
            ->where('is_active', true)
            ->get();

        if ($schedules->isEmpty()) {
            
            return $this->errorResponse(__('messages.service_not_scheduals'), 422);
        }

        $isValidShift = false;
        foreach ($schedules as $schedule) {
            $shiftStart = Carbon::parse($schedule->start_time)->format('H:i:s');
            $shiftEnd = Carbon::parse($schedule->end_time)->format('H:i:s');
            
            if ($newStart->format('H:i:s') >= $shiftStart && $newEnd->format('H:i:s') <= $shiftEnd) {
                $isValidShift = true;
                break;
            }
        }

        if (!$isValidShift) {
            return $this->errorResponse(__('messages.service_not_scheduals'), 422);
        }

        // 4. فحص توفر العامل في الموعد الجديد
        $isWorkerBusy = Booking::where('worker_id', $booking->worker_id)
            ->where('id', '!=', $booking->id) 
            ->where('booking_date', $request->new_date)
            ->whereIn('status', ['Upcoming', 'inService'])
            ->where(function ($query) use ($newStart, $newEnd) {
                $query->where('start_time', '<', $newEnd->format('H:i:s'))
                      ->where('end_time', '>', $newStart->format('H:i:s'));
            })->exists();

        if ($isWorkerBusy) {
            return $this->errorResponse(__('messages.worker_busy'), 422);
        }

        // 5. التحديث في قاعدة البيانات
        DB::beginTransaction();
        try {
            $booking->update([
                'booking_date'     => $request->new_date,
                'start_time'       => $newStart->format('H:i:s'),
                'end_time'         => $newEnd->format('H:i:s'),
                'duration_minutes' => $newDurationMinutes, 
            ]);

            BookingHistory::create([
                'booking_id' => $booking->id,
                'old_status' => 'Upcoming',
                'new_status' => 'Upcoming',
                'note'       => 'عملية إعادة جدولة - تم تعديل الموعد إلى: ' . $request->new_date . ' ' . $newStart->format('H:i'),
                'created_by' => auth()->id(),
            ]);

            DB::commit();

            return $this->successResponse(
                new BookingResource($booking), 
              __('messages.booking_rescheduled_successfully')
            );

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('حدث خطأ أثناء إعادة الجدولة: ' . $e->getMessage(), 500);
        }
    }


    // ---------------------------------------------------------
    // 5. إلغاء الحجز (Cancel Booking)
    // ---------------------------------------------------------
    public function cancel(Request $request, $id)
    {
        $booking = Booking::with('service.rules')->findOrFail($id);
        
        // 1. الحماية والصلاحيات
        if ($booking->customer_id !== auth()->id()) {
            return $this->errorResponse(__('messages.unauthorized'), 403);
            }
            
            if ($booking->status !== 'Upcoming') {
                return $this->errorResponse(__('messages.booking_not_upcoming',['status' => $booking->status]), 400);
                }
                
                $rules = $booking->service->rules;
           
        $cancellationFee = 0;
        $historyNote = 'تم إلغاء الحجز من قبل العميل (إلغاء مجاني).';

        // 2. تطبيق قواعد الإلغاء (إن وجدت)
        if ($rules) {
            $bookingDateTime = Carbon::parse($booking->booking_date . ' ' . $booking->start_time);
            $cutoffHours = $rules->free_cancel_cutoff_hours; // مثلاً 24 ساعة
            // هل الإلغاء متأخر؟ (تجاوز فترة الإلغاء المجاني)
            if (now()->addHours($cutoffHours)->greaterThan($bookingDateTime)) {
                
                // الأكشن 1: منع الإلغاء (Block)
                if ($rules->late_cancel_action === 'block') {
                    return $this->errorResponse(__('messages.late_cancel_blocked',['cutoffHours' => $cutoffHours]), 400);
                    }
                    
                    // الأكشن 2: تطبيق غرامة (Fee)
                    if ($rules->late_cancel_action === 'fee') {
                    if ($rules->late_cancel_fee_type === 'fixed') {
                        $cancellationFee = $rules->late_cancel_fee_value;
                    } elseif ($rules->late_cancel_fee_type === 'percent') {
                        $cancellationFee = ($booking->price_subtotal * $rules->late_cancel_fee_value) / 100;
                    }
                    
                    $historyNote = __('messages.late_cancel_fee_applied', ['fee' => number_format($cancellationFee, 2)]) . ' - تم إلغاء الحجز من قبل العميل بعد فترة الإلغاء المجاني.';
                }
            }
        }

        // 3. تنفيذ الإلغاء في الداتابيز
        DB::beginTransaction();
        try {
            // تحديث حالة الحجز
            // ملاحظة: إذا كان في غرامة، ممكن تحفظها في حقل جديد (مثلاً cancellation_fee) إذا أضفته للجدول مستقبلاً
            $booking->update([
                'status' => 'cancelled',
            ]);

            // تحرير العامل (إلغاء التعيين عشان يقدر يستقبل حجوزات ثانية بهاد الوقت)
            BookingAssignment::where('booking_id', $booking->id)
                ->where('worker_id', $booking->worker_id)
                ->update([
                    'status' => 'cancelled' 
                ]);

            // تسجيل العملية في الـ History
            BookingHistory::create([
                'booking_id' => $booking->id,
                'old_status' => 'Upcoming',
                'new_status' => 'cancelled',
                'note'       => $historyNote,
                'created_by' => auth()->id(),
            ]);

            DB::commit();

            return $this->successResponse(
                new BookingResource($booking), 
                __('messages.booking_cancelled_successfully')
            );

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('حدث خطأ أثناء الإلغاء: ' . $e->getMessage(), 500);
        }
    }

    public function report(RportBookingRequest $request, $id)
    {
        $booking = Booking::findOrFail($id);
    
        // حماية
        if ($booking->customer_id !== auth()->id()) {
            return $this->errorResponse(__('messages.unauthorized_report'), 403);
        }

        if (!in_array($booking->status, ['inService', 'completed'])) {
            return $this->errorResponse(__('messages.invalid_booking_status_for_report'), 400);
        }

        // منع تكرار البلاغ لنفس الحجز (اختياري، حسب البزنس تبعك)
        $existingReport = Report::where('booking_id', $booking->id)->exists();
        if ($existingReport) {
            return $this->errorResponse(__('messages.existing_report'), 400);
        }

        // إنشاء البلاغ
       Report::create([
            'booking_id'  => $booking->id,
            'customer_id' => auth()->id(),
            'reason'      => $request->reason,
            'description' => $request->description, // اختياري حسب الشاشة
        ]);

        return $this->successResponse(null, __('messages.booking_reported_successfully'), 201);
    }

    public function review(Request $request, $id)
    {
    
        $booking = Booking::findOrFail($id);

        if ($booking->customer_id !== auth()->id()) {
             return $this->errorResponse(__('messages.unauthorized_review'), 403);
        }

        // التقييم بصير بس للحجوزات المكتملة
        if ($booking->status !== 'completed') {
            return $this->errorResponse(__('messages.invalid_booking_status_for_review'), 400);
        }

        DB::beginTransaction();
        try {
            // 1. تقييم الخدمة (Service)
            // نستخدم updateOrCreate لمنع العميل من تقييم نفس الخدمة في نفس الحجز أكثر من مرة
           Review::updateOrCreate(
                [
                    'booking_id'      => $booking->id,
                    'customer_id'     => auth()->id(),
                    'reviewable_type' => \App\Models\Service::class,
                    'reviewable_id'   => $booking->service_id,
                ],
                [
                    'rating'  => $request->service_rating,
                    'comment' => $request->service_comment,
                ]
            );

            // 2. تقييم العمال (Workers)
            foreach ($request->workers as $workerData) {
                // التأكد أن العامل فعلاً كان من ضمن العمال اللي اشتغلوا بهذا الحجز
                // $isAssigned = \App\Models\BookingAssignment::where('booking_id', $booking->id)
                //     ->where('worker_id', $workerData['worker_id'])
                //     ->exists();

                // if ($isAssigned) {
                    \App\Models\Review::updateOrCreate(
                        [
                            'booking_id'      => $booking->id,
                            'customer_id'     => auth()->id(),
                            'reviewable_type' => \App\Models\Worker::class,
                            'reviewable_id'   => $workerData['worker_id'],
                        ],
                        [
                            'rating'  => $workerData['rating'],
                            'comment' => $workerData['comment'] ?? null,
                        ]
                    );
                // }
            }

            $this->updateAverageRating(\App\Models\Service::class, $booking->service_id);
            foreach ($request->workers as $workerData) {
                $this->updateAverageRating(\App\Models\Worker::class, $workerData['worker_id']);
            }

            DB::commit();

            return $this->successResponse(null, __('messages.review_submitted_successfully'), 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('حدث خطأ أثناء حفظ التقييم: ' . $e->getMessage(), 500);
        }
    }

    // دالة مساعدة (Helper) لتحديث متوسط التقييم (يفضل وضعها في المودل أو هنا للسرعة)
    private function updateAverageRating($modelClass, $modelId)
    {
        $avgRating = \App\Models\Review::where('reviewable_type', $modelClass)
            ->where('reviewable_id', $modelId)
            ->avg('rating');

        $modelClass::where('id', $modelId)->update([
            'avg_rating' => round($avgRating, 1) // التقريب لمنزلة عشرية واحدة مثل 4.4
        ]);
    }



}
