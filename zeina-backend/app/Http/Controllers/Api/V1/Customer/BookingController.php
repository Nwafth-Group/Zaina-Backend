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
use Illuminate\Support\Facades\Cache;
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
        $booking->load(['service.media', 'service.rules', 'address.city', 'address.area', 'workers']);
        
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
        
        // 1. قفل البوابة الذري (Atomic Lock): منع النقرات المتزامنة المتكررة في نفس الجزء من الثانية
        $lockKey = 'booking_lock_user_' . $user->id . '_service_' . $request->service_id;
        $lock = Cache::lock($lockKey, 10); // قفل مؤقت لمدة 10 ثوانٍ كافية للمعالجة

        if (!$lock->get()) {
            return $this->errorResponse(__('messages.booking_in_progress') ?? 'جاري معالجة طلب حجزك الحالي بالفعل، الرجاء الانتظار.', 429);
        }

        // متغير خارجي للاحتفاظ ببيانات الحجز الناجح لإرجاعه في نهاية الدالة
        $createdBooking = null;

        try {
            // 2. حماية العناوين: التأكد من ملكية الكستمر للعنوان وأنه فعال
            $address = CustomerAddress::with('area')
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->findOrFail($request->address_id);

            $service = Service::with('duration')->findOrFail($request->service_id);
            
            // فحص الغرامات أو المحفظة السالبة
            if ($user->wallet_balance < 0) {
                $lock->release();
                return $this->errorResponse(__('messages.unpaid_penalties', ['amount' => abs($user->wallet_balance)]), 402);
            }

            // 3. حساب وقت البداية والنهاية الكلي بناءً على عدد الكتل الثابتة
            $periodHours = $service->duration->service_period_hours ?? 2; 
            $totalMinutes = $request->periods_count * $periodHours * 60;

            $start = Carbon::parse($request->start_time);
            $end = $start->copy()->addMinutes($totalMinutes);
            
            $bookingDate = $request->date;
            $startStr = $start->format('H:i:s');
            $endStr = $end->format('H:i:s');

            // 4. حماية العمال الصارمة: الفحص عبر جدول الـ BookingAssignment الوسيط
            $workerIds = $request->worker_ids;
            $areaId = $address->area_id;
            $cityId = $address->area->city_id;

            foreach ($workerIds as $workerId) {
                // أ. هل العامل مسجل في هذه الخدمة ونشط؟
                $workerExists = $service->workers()
                    ->where('workers.id', $workerId)
                    ->where('workers.status', 'active')
                    ->exists();

                if (!$workerExists) {
                    $lock->release();
                    return $this->errorResponse(__('messages.invalid_worker_for_service') ?? 'عذراً، أحد العمال المختارين غير متاح لتقديم هذه الخدمة.', 422);
                }

                // ب. الفحص الجوهري: هل العامل محجوز في جدول الـ assignments لحساب حجوزات نشطة؟
                $hasConflict = BookingAssignment::where('worker_id', $workerId)
                    ->where('status', 'active')
                    ->whereHas('booking', function ($q) use ($bookingDate, $startStr, $endStr) {
                        $q->where('booking_date', $bookingDate)
                          ->whereIn('status', ['Upcoming', 'inService'])
                          ->where(function ($timeQ) use ($startStr, $endStr) {
                              $timeQ->where('start_time', '<', $endStr)
                                    ->where('end_time', '>', $startStr);
                          });
                    })
                    ->exists();

                if ($hasConflict) {
                    $lock->release();
                    return $this->errorResponse(__('messages.worker_already_booked') ?? 'عذراً، أحد العمال المختارين تم حجزه في هذا الوقت بالفعل.', 422);
                }
            }

            // 5. حساب الأسعار بناءً على النطاق الجغرافي لعنوان العميل الموثق
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
                $lock->release();
                return $this->errorResponse(__('messages.service_not_available'), 400);
            }

            // الحسبة الأساسية: (سعر الكتلة) * (عدد الكتل) * (عدد العمال)
            $pricePerPeriod = (float) $servicePrice->price_per_service_period; 
            $workersCount = count($workerIds);
            $periodsCount = (int) $request->periods_count;
            $subtotal = $pricePerPeriod * $periodsCount * $workersCount;
            
            // 6. ميكانيكية حساب الكوبونات والخصومات ديناميكياً
            $discountAmount = 0;
            $coupon = null;

            if ($request->filled('coupon_code')) {
                $now = now();
                $coupon = Coupon::where('code', $request->coupon_code)
                    ->where('status', 'active')
                    ->where('start_date', '<=', $now)
                    ->where('end_date', '>=', $now)
                    ->first();

                if (!$coupon) {
                    $lock->release();
                    return $this->errorResponse(__('messages.invalid_or_expired_coupon') ?? 'كود الكوبون غير صحيح أو منتهي الصلاحية.', 422);
                }

                if (!$coupon->is_for_all_users) {
                    $isUserEligible = $coupon->users()->where('user_id', $user->id)->exists();
                    if (!$isUserEligible) {
                        $lock->release();
                        return $this->errorResponse(__('messages.coupon_not_for_you'), 422);
                    }
                }

                $userUsageCount = Booking::where('customer_id', $user->id)
                    ->where('coupon_id', $coupon->id)
                    ->whereIn('status', ['Upcoming', 'inService', 'completed'])
                    ->count();

                if ($userUsageCount >= $coupon->user_usage_limit) {
                    $lock->release();
                    return $this->errorResponse(__('messages.coupon_usage_limit_reached'), 422);
                }

                if ($coupon->usage_limit && $coupon->used_count >= $coupon->usage_limit) {
                    $lock->release();
                    return $this->errorResponse(__('messages.coupon_fully_consumed'), 422);
                }

                if (!$coupon->is_for_all_services) {
                    $isServiceEligible = $coupon->services()->where('service_id', $service->id)->exists();
                    if (!$isServiceEligible) {
                        $lock->release();
                        return $this->errorResponse(__('messages.coupon_does_not_include_service'), 422);
                    }
                }

                if ($subtotal < $coupon->min_booking_amount) {
                    $lock->release();
                    return $this->errorResponse(__('messages.coupon_min_amount_not_met', ['amount' => $coupon->min_booking_amount]), 422);
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

            // فحص كفاية رصيد المحفظة النهائي
            if ($user->wallet_balance < $total) {
                $lock->release();
                return $this->errorResponse(__('messages.insufficient_balance') ?? 'عذراً، رصيد المحفظة غير كافٍ.', 402);
            }

            // 7. فتح المعاملة المالية وإغلاق الـ Pessimistic Lock للعمال
            DB::beginTransaction();

            BookingAssignment::whereIn('worker_id', $workerIds)
                ->where('status', 'active')
                ->whereHas('booking', function ($q) use ($bookingDate, $startStr, $endStr) {
                    $q->where('booking_date', $bookingDate)
                      ->whereIn('status', ['Upcoming', 'inService'])
                      ->where(function ($timeQ) use ($startStr, $endStr) {
                          $timeQ->where('start_time', '<', $endStr)
                                ->where('end_time', '>', $startStr);
                      });
                })
                ->lockForUpdate()
                ->exists();

            // إنشاء رأس الحجز الرئيسي في جدول bookings
            $booking = Booking::create([
                'booking_ref'      => 'HC-' . rand(100000, 999999),
                'customer_id'      => $user->id,
                'service_id'       => $request->service_id,
                'address_id'       => $request->address_id,
                'worker_id'        => $workerIds[0], // مرجعية سريعة لأول عامل لربط كود الـ Lists القديم والـ UI
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
            ]);

            // توزيع وإثبات العمال المتعددين في جدول التخصيصات الوسيط
            foreach ($workerIds as $workerId) {
                BookingAssignment::create([
                    'booking_id'  => $booking->id,
                    'worker_id'   => $workerId,
                    'status'      => 'active',
                    'assigned_at' => now(),
                ]);
            }

            // تحديث إحصائيات الكوبون إن وجد
            if ($coupon) {
                $coupon->increment('used_count');
            }

            // تسجيل العملية بالسجل التاريخي للحجز
            BookingHistory::create([
                'booking_id' => $booking->id,
                'new_status' => 'Upcoming',
                'note'       => $coupon 
                    ? "تم إنشاء الحجز وتطبيق الكوبون ({$coupon->code}) بخصم قدره {$discountAmount} جنيه."
                    : "تم إنشاء الحجز بنظام الكتل الثابتة والدفع لـ {$workersCount} عمال.",
                'created_by' => $user->id,
            ]);
            // تنفيذ عملية السحب المالي من محفظة الكستمر
            $user->withdraw($total, 'Payment - Booking', $booking->booking_ref);
            DB::commit();
            
            // حفظ نسخة الحجز الناجح لإرساله بالـ Resource خارج الـ try
            $createdBooking = $booking;

        } catch (\Exception $e) {
            // حماية تامة: تحرير القفل وإلغاء الـ Transaction فوراً عند حدوث أي خطأ بالسيرفر
            $lock->release(); 
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            return response()->json([
                'success' => false, 
                'message' => 'حدث خطأ أثناء معالجة الحجز الحجز.', 
                'error' => $e->getMessage()
            ], 500);
        }

        // 🌟 تحرير قفل المعاملة بنجاح واستقرار بعد إتمام الـ Commit والأمان التام
        $lock->release(); 

        return $this->successResponse(
            new BookingResource($createdBooking->load(['service', 'coupon', 'address', 'workers'])), 
            __('messages.booking_created_successfully'), 
            201
        );
    }


    public function getRescheduleShifts(Request $request, $id)
{
    // 1. التحقق من المدخلات الأساسية (التاريخ المطلوب للجدولة)
    $request->validate([
        'date' => 'required|date_format:Y-m-d',
    ]);

    // 2. جلب الحجز الحالي مع العمال المخصصين له (Assignments) لضمان فحص توفرهم هم بالذات
    $booking = Booking::with(['service.duration', 'workers'])->findOrFail($id);
    
    // حماية أمنية: التأكد أن الحجز يخص المستخدم الحالي
    if ($booking->customer_id !== auth()->id()) {
        return $this->errorResponse(__('messages.unauthorized'), 403);
    }

    $date = Carbon::parse($request->date);
    $dayName = strtolower($date->format('l'));
    
    // 3. جلب الشفتات الرسمية للخدمة بناءً على اليوم الجديد المختار
    $schedules = ServiceSchedule::where('service_id', $booking->service_id)
        ->where('day', $dayName)
        ->where('is_active', true)
        ->get();
        
    // حساب طول الكتلة الثابتة للخدمة بالدقائق (مثلاً ساعتين = 120 دقيقة)
    $periodMinutes = ($booking->service->duration->service_period_hours ?? 2) * 60;
    // 4. استخراج الـ IDs للعمال الحاليين المرتبطين بالحجز
    $workerIds = $booking->workers->pluck('id')->toArray();
    // 5. جلب الحجوزات القائمة لهؤلاء العمال "فقط" في التاريخ الجديد المختار
    // 🌟 ملاحظة حاسمة: نستثني الحجز الحالي نفسه (id != $booking->id) لكي لا يحجبه السيرفر لو كان في نفس اليوم
    $existingBookings = Booking::whereIn('worker_id', $workerIds)
        ->where('id', '!=', $booking->id) 
        ->where('booking_date', $date->format('Y-m-d'))
        ->whereIn('status', ['Upcoming', 'inService'])
        ->get(['worker_id', 'start_time', 'end_time']);

    $shiftsData = [];
    $isToday = $date->isToday(); 
    $now = now(); 

    // 6. تقسيم الشفتات إلى كتل ثابتة (Blocks) وفحص توافقها مع العمال
    foreach ($schedules as $schedule) {
        $shiftStart = Carbon::parse($schedule->start_time);
        $shiftEnd = Carbon::parse($schedule->end_time);
        
        $dropdownLabel = "Shift: " . $shiftStart->format('h:i A') . ' - ' . $shiftEnd->format('h:i A');
        $fixedBlocks = [];
        $currentBlockStart = $shiftStart->copy();

        while ($currentBlockStart->copy()->addMinutes($periodMinutes)->lte($shiftEnd)) {
            $blockEnd = $currentBlockStart->copy()->addMinutes($periodMinutes);
            
            // حماية منطقية: إذا كان التاريخ المختار هو "اليوم"، نتخطى الكتل التي مضت في الساعات السابقة
            if ($isToday) {
                $blockEndDateTime = Carbon::parse($date->format('Y-m-d') . ' ' . $blockEnd->format('H:i:s'));
                if ($blockEndDateTime->lte($now)) {
                    $currentBlockStart->addMinutes($periodMinutes);
                    continue;
                }
            }

            $startStr = $currentBlockStart->format('H:i:s');
            $endStr = $blockEnd->format('H:i:s');
            
            // 🌟 الفحص الجوهري لشاشة الفيجما: يجب أن يكون "جميع" عمال الحجز متاحين معاً في هذه الكتلة
            $areAllWorkersAvailable = true;
            
            foreach ($workerIds as $workerId) {
                // نتحقق إذا كان هذا العامل بالذات لديه تضارب في هذه الكتلة الزمنية
                $hasConflict = $existingBookings->where('worker_id', $workerId)
                    ->where('start_time', '<', $endStr)
                    ->where('end_time', '>', $startStr)
                    ->isNotEmpty();

                if ($hasConflict) {
                    // بمجرد انشغال عامل واحد من المجموعة، تسقط هذه الكتلة الزمنية فوراً
                    $areAllWorkersAvailable = false;
                    break;
                }
            }

            // إذا كان كافة العمال المخصصين للحجز فارغين، نعيد الوقت ككتلة متاحة للفرونت إند
            if ($areAllWorkersAvailable) {
                $fixedBlocks[] = [
                    'start_time' => $currentBlockStart->format('H:i'),
                    'end_time'   => $blockEnd->format('H:i'),
                    'label'      => $currentBlockStart->format('h:i A') . ' - ' . $blockEnd->format('h:i A'),
                ];
            }

            // الانتقال للكتلة التالية
            $currentBlockStart->addMinutes($periodMinutes);
        }

        if (count($fixedBlocks) > 0) {
            $shiftsData[] = [
                'id' => $schedule->id,
                'dropdown_label' => $dropdownLabel,
                'available_blocks' => $fixedBlocks 
            ];
        }
    }

    return $this->successResponse(['shifts' => $shiftsData], __('messages.shifts_retrieved'));
}




  public function reschedule(RescheduleBookingRequest $request, $id)
    {
        // 1. جلب الحجز مع علاقة العمال (assignments) والـ rules لحماية الأداء لمنع الـ N+1
        $booking = Booking::with(['service.rules', 'workers'])->findOrFail($id);
        
        // 🔒 حماية وصلاحيات: التأكد أن الحجز يخص المستخدم الحالي المسجل دخوله
        if ($booking->customer_id !== auth()->id()) {
            return $this->errorResponse(__('messages.unauthorized'), 403);
        }

        // الحجز يجب أن يكون بحالة Upcoming ليقبل إعادة الجدولة
        if ($booking->status !== 'Upcoming') {
            return $this->errorResponse(__('messages.booking_not_upcoming'), 400);
        }

        // 2. حساب المدة الجديدة ومقارنتها بالقديمة (تأمين صارم: يمنع تلاعب الفرونت إند بزيادة أو نقصان الساعات)
        $newStart = Carbon::parse($request->new_start_time);
        $newEnd = Carbon::parse($request->new_end_time);
        $newDurationMinutes = (int) $newStart->diffInMinutes($newEnd);

        if ($newDurationMinutes !== $booking->duration_minutes) {
            return $this->errorResponse(__('messages.reschedule_duration_mismatch'), 422);
        }

        // 3. تطبيق قواعد الـ Business (Service Rules) المحددة من الأدمن
        $rules = $booking->service->rules;
        if ($rules) {
            $oldBookingDateTime = Carbon::parse($booking->booking_date . ' ' . $booking->start_time);
            $cutoffHours = $rules->reschedule_cutoff_hours;
            
            // أ. فحص الـ Cutoff: منع التعديل إذا اقترب موعد الحجز الأصلي وتخطى الحد المسموح (مثلاً قبل الحجز بـ ساعتين)
            if (now()->addHours($cutoffHours)->greaterThan($oldBookingDateTime)) {
                return $this->errorResponse(__('messages.reschedule_cutoff') ?? 'عذراً، لا يمكن إعادة الجدولة لاقتراب موعد الخدمة.', 400);
            }

            // ب. فحص الحد الأقصى لعدد مرات إعادة الجدولة المسموحة للحجز الواحد
            if ($rules->max_changes_per_booking > 0) {
                $changesCount = BookingHistory::where('booking_id', $booking->id)
                    ->where('note', 'like', '%إعادة جدولة%')
                    ->count();

                if ($changesCount >= $rules->max_changes_per_booking) {
                    return $this->errorResponse(__('messages.reschedule_max_changes', ['max_changes' => $rules->max_changes_per_booking]), 400);
                }
            }

            $newBookingDateTime = Carbon::parse($request->new_date . ' ' . $request->new_start_time);
            
            // ج. فحص الـ Lead Time الأدنى (أقل فترة زمنية مسموحة بين لحظة التعديل والموعد الجديد)
            if (now()->addHours($rules->lead_time_min_hours)->greaterThan($newBookingDateTime)) {
                return $this->errorResponse(__('messages.reschedule_lead_time_min'), 400);
            }

            // د. فحص الـ Lead Time الأقصى (أبعد فترة زمنية مسموحة للحجز في المستقبل)
            if (now()->addDays($rules->lead_time_max_days)->endOfDay()->lessThan($newBookingDateTime)) {
                return $this->errorResponse(__('messages.reschedule_lead_time_max'), 400);
            }
        }

        // 4. فحص الشفتات الرسمية للخدمة في اليوم الجديد (للتأكد أن الموعد يقع بالكامل ضمن دوام الشركة)
        $dayName = strtolower(Carbon::parse($request->new_date)->format('l'));
        $schedules = ServiceSchedule::where('service_id', $booking->service_id)
            ->where('day', $dayName)
            ->where('is_active', true)
            ->get();

        if ($schedules->isEmpty()) {
            return $this->errorResponse(__('messages.service_not_scheduals'), 422);
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
            return $this->errorResponse(__('messages.service_not_scheduals') ?? 'الموعد المختار يقع خارج ساعات شفتات الخدمة.', 422);
        }

        // 5. استخراج الـ IDs لطاقم العمال الحاليين المثبتين بالحجز لفحص توفرهم
        $workerIds = $booking->workers->pluck('id')->toArray();

        // 🌟 فحص التوفر الصارم لتجنب التعارض (Double Booking Check) 🌟
        // نتحقق من عدم انشغال "أي" عامل من المجموعة، مع استثناء الحجز الحالي نفسه لكي لا يرى موعده القديم كتعارض
        $isAnyWorkerBusy = Booking::whereIn('worker_id', $workerIds)
            ->where('id', '!=', $booking->id) 
            ->where('booking_date', $request->new_date)
            ->whereIn('status', ['Upcoming', 'inService'])
            ->where(function ($query) use ($newStartStr, $newEndStr) {
                $query->where('start_time', '<', $newEndStr)
                      ->where('end_time', '>', $newStartStr);
            })
            ->exists();

        if ($isAnyWorkerBusy) {
            return $this->errorResponse(__('messages.worker_busy') ?? 'عذراً، أحد العمال المخصصين لحجزك مشغول في هذا الوقت المختار.', 422);
        }

        // 6. بدء المعاملة الآمنة والتحديث بقفل الحماية من الـ Race Condition
        DB::beginTransaction();
        try {
            // تفعيل الـ Pessimistic Lock (lockForUpdate) على العمال لمنع أي حجز متزامن في نفس الجزء من الثانية
            Booking::whereIn('worker_id', $workerIds)
                ->where('id', '!=', $booking->id)
                ->where('booking_date', $request->new_date)
                ->whereIn('status', ['Upcoming', 'inService'])
                ->where(function ($query) use ($newStartStr, $newEndStr) {
                    $query->where('start_time', '<', $newEndStr)
                          ->where('end_time', '>', $newStartStr);
                })
                ->lockForUpdate()
                ->exists();

            // تحديث المواعيد في جدول الحجز الرئيسي
            $booking->update([
                'booking_date' => $request->new_date,
                'start_time'   => $newStartStr,
                'end_time'     => $newEndStr,
            ]);

            // تسجيل العملية رسمياً في سجل حركات الحجز (History)
            BookingHistory::create([
                'booking_id' => $booking->id,
                'old_status' => 'Upcoming',
                'new_status' => 'Upcoming',
                'note'       => 'عملية إعادة جدولة - تم تعديل الموعد إلى: ' . $request->new_date . ' ' . $newStart->format('h:i A'),
                'created_by' => auth()->id(),
            ]);

            DB::commit();

            // إرجاع الـ Resource الموحد والمترجم مع الـ Eager Loading لكافة تفاصيل الحجز لشاشة التفاصيل
            return $this->successResponse(
                new BookingResource($booking->load(['service.rules', 'coupon', 'address', 'workers'])), 
                __('messages.booking_rescheduled_successfully')
            );

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('حدث خطأ أثناء معالجة إعادة الجدولة: ' . $e->getMessage(), 500);
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
