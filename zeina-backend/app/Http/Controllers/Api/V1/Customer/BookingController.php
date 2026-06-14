<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\RescheduleBookingRequest;
use App\Http\Requests\Api\V1\Customer\ReviewBookingRequest;
use App\Http\Requests\Api\V1\Customer\RportBookingRequest;
use App\Http\Requests\Api\V1\Customer\StoreBookingRequest;
use App\Http\Resources\Api\V1\Booking\BookingResource;
use App\Http\Resources\Api\V1\Reports\ReportsResource;
use App\Jobs\SendBookingNotificationSmsJob;
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
use App\Service\BookingService;
use App\Service\SmsService;
use App\Traits\ApiResponseTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BookingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    use ApiResponseTrait;
    protected $bookingService;

    public function __construct(BookingService $bookingService)
    {
        $this->bookingService = $bookingService;

    }
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


        return $this->successResponse(BookingResource::collection($bookings), __('messages.bookings_fetched_successfully'));
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
    
    // 1. قفل البوابة الذري (Atomic Lock) لمنع النقرات المتزامنة
    $lockKey = 'booking_lock_user_' . $user->id . '_service_' . $request->service_id;
    $lock = Cache::lock($lockKey, 10);

    if (!$lock->get()) {
        return $this->errorResponse(__('messages.booking_in_progress') ?? 'جاري معالجة طلب حجزك الحالي بالفعل، الرجاء الانتظار.', 429);
    }

    try {
        // 2. استدعاء المعالجة المالية وحجز العمال من الـ Service
        $booking = $this->bookingService->createBooking($user, $request->validated());
        
        // تحرير القفل فور النجاح والاستقرار
        $lock->release();

        // 🌟 3. السحر الجديد: إطلاق نظام الإشعارات الشامل لـ لارافيل 🌟
        // بما أن الـ Notification مدمج بـ `ShouldQueue` سينطلق فورا بالخلفية دون تأخير الـ API
        try {
            $user->notify(new \App\Notifications\BookingNotification(
                __('sms.booking_confirmed'), // عنوان الإشعار على الهاتف وفي الـ Inbox
                __('sms.booking_confirmed_message', [
                    'booking_ref' => $booking->booking_ref
                ]), 
                $booking->id, // الـ ID للتوجه لشاشة التفاصيل
                [
                    'booking_ref'   => $booking->booking_ref,
                    'booking_date'  => $booking->booking_date,
                    'workers_count' => count($request->worker_ids)
                ]
            ));
        } catch (\Exception $notifEx) {
            // كاش داخلي حماية؛ لو تعطل الفايربيز لا نخرب على المستخدم نجاح الحجز
            Log::error("Failed to trigger system booking notification: " . $notifEx->getMessage());
        }

        // 4. إرجاع الـ Response النهائي المستقر
        return $this->successResponse(
            new BookingResource($booking->load(['service', 'coupon', 'address', 'workers'])),
            __('messages.booking_created_successfully'),
            201
        );

    } catch (\Exception $e) {
        // تحرير القفل فوراً عند حدوث خطأ
        $lock->release();

        $statusCode = in_array($e->getCode(), [400, 402, 422, 429]) ? $e->getCode() : 500;
        return response()->json([
            'success' => false,
            'message' => $statusCode === 500 ? 'حدث خطأ غير متوقع أثناء معالجة الحجز.' : $e->getMessage(),
            'error'   => app()->environment('local') ? $e->getMessage() : null
        ], $statusCode);
    }
}

    public function getRescheduleShifts(Request $request, $id)
    {
        // 1. التحقق من المدخلات الأساسية (التاريخ المطلوب للجدولة)
        $request->validate([
            'date' => 'required|date_format:Y-m-d',
        ]);

        // 2. جلب الحجز الحالي مع العمال المخصصين له عبر العلاقة المباشرة المحدثة (workers)
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

        // حساب طول الكتلة الثابتة للخدمة بالدقائق
        $periodMinutes = ($booking->service->duration->service_period_hours ?? 2) * 60;

        // 4. استخراج الـ IDs للعمال الحاليين المرتبطين بالحجز
        $workerIds = $booking->workers->pluck('id')->toArray();

        // 🌟 5. التعديل الجوهري: جلب الحجوزات القائمة لهؤلاء العمال من جدول الـ Assignments 🌟
        // نضمن استثناء الحجز الحالي نفسه (booking_id != $booking->id)
        $existingBookings = BookingAssignment::whereIn('worker_id', $workerIds)
            ->where('status', 'active') // التخصيص فعال
            ->where('booking_id', '!=', $booking->id) // استثناء الحجز الحالي من كشف التعارضات
            ->whereHas('booking', function ($q) use ($date) {
                $q->where('booking_date', $date->format('Y-m-d'))
                    ->whereIn('status', ['Upcoming', 'inService']); // الحجوزات القائمة فقط
            })
            ->with('booking:id,start_time,end_time') // منع الـ N+1 أثناء الـ Loop
            ->get()
            ->map(function ($assignment) {
                return [
                    'worker_id'  => $assignment->worker_id,
                    'start_time' => $assignment->booking->start_time,
                    'end_time'   => $assignment->booking->end_time,
                ];
            });

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

                // حماية منطقية: إذا كان التاريخ المختار هو "اليوم"، نتخطى الكتل التي مرت في الماضي
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
                    // نتحقق إذا كان هذا العامل بالذات لديه تضارب في هذه الكتلة الزمنية من خلال الـ Collection المفلترة
                    $hasConflict = $existingBookings->where('worker_id', $workerId)
                        ->where('start_time', '<', $endStr)
                        ->where('end_time', '>', $startStr)
                        ->isNotEmpty();

                    if ($hasConflict) {
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

    try {
        $userId = auth()->id();
        $user = auth()->user();
        
        // 1. استدعاء اللوجيك بالكامل من الـ Service في سطر واحد ونظيف 🌟
        $booking = $this->bookingService->rescheduleBooking($id, $userId, $request->validated());

        // 🌟 2. السحر الموحد: إطلاق نظام الإشعارات الشامل لإعادة الجدولة 🌟
        // السطر هاد هس يعت فايربيز (Push)، ويخزن بالداتابيز (Inbox)، ويبعت الـ SMS تلقائياً بالخلفية
        try {
            $newStart = \Carbon\Carbon::parse($request->new_start_time);
            
            $user->notify(new \App\Notifications\BookingNotification(
                __('sms.booking_rescheduled_title'), // عنوان الإشعار (مثلاً: تم تعديل موعد الحجز 🔄)
                __('sms.booking_rescheduled_message', [
                    'booking_ref' => $booking->booking_ref,
                    'date'        => $request->new_date,
                    'time'        => $newStart->format('h:i A'),
                ]), 
                $booking->id, // الـ ID للتوجه لشاشة التفاصيل بالموبايل
                [
                    'booking_ref'   => $booking->booking_ref,
                    'booking_date'  => $request->new_date,
                    'booking_time'  => $newStart->format('h:i A'),
                    'workers_count' => $booking->workers()->count()
                ]
            ));

        } catch (\Exception $notifEx) {
            // حماية للـ Flow؛ لو تعطل الفايربيز أو الـ SMS لا نخرب على العميل نجاح العملية
            \Illuminate\Support\Facades\Log::error("Failed to trigger system reschedule notification: " . $notifEx->getMessage());
        }

        // 3. إرجاع الاستجابة المستقرة والـ Resource الموحد
        return $this->successResponse(
            new BookingResource($booking->load(['service.rules', 'coupon', 'address', 'workers'])),
            __('messages.booking_rescheduled_successfully')
        );

    } catch (\Exception $e) {
        // معرفة نوع الكود الراجع أو إرجاع 500 للأخطاء غير المتوقعة بالـ Engine
        $statusCode = in_array($e->getCode(), [400, 403, 422]) ? $e->getCode() : 500;
        
        return response()->json([
            'success' => false,
            'message' => $statusCode === 500 ? 'حدث خطأ غير متوقع أثناء معالجة إعادة الجدولة.' : $e->getMessage(),
            'error'   => app()->environment('local') ? $e->getMessage() : null
        ], $statusCode);
    }
}
   
public function cancel(Request $request, $id)
{
    try {
        $userId = auth()->id();
        $user = auth()->user();

        // 1. استدعاء اللوجيك بالكامل من الـ Service في سطر واحد ونظيف 🌟
        $booking = $this->bookingService->cancelBooking($id, $userId);

        // 🌟 2. السحر الموحد: إطلاق نظام الإشعارات الشامل لإلغاء الحجز 🌟
        // السطر هاد هس يعت فايربيز (Push)، ويخزن بالداتابيز (Inbox)، ويبعت الـ SMS تلقائياً بالخلفية
        try {
            $user->notify(new \App\Notifications\BookingNotification(
                __('sms.booking_cancelled_title'), // عنوان الإشعار (مثلاً: تم إلغاء الحجز ❌)
                __('sms.booking_cancelled_message', [
                    'booking_ref' => $booking->booking_ref,
                ]), 
                $booking->id, // الـ ID للتوجه لشاشة تفاصيل الحجز الملغي في الموبايل
                [
                    'booking_ref'    => $booking->booking_ref,
                    'booking_date'   => $booking->booking_date,
                    'cancelled_at'   => now()->toIso8601String(),
                    'current_status' => 'Cancelled'
                ]
            ));

        } catch (\Exception $notifEx) {
            // حماية للـ Flow؛ لو تعطل الفايربيز أو الـ SMS لا نخرب على العميل نجاح عملية الإلغاء ورجوع الأموال
            \Illuminate\Support\Facades\Log::error("Failed to trigger system cancellation notification: " . $notifEx->getMessage());
        }

        // 3. إرجاع الاستجابة المستقرة والـ Resource الموحد
        return $this->successResponse(
            new BookingResource($booking->load(['service', 'coupon', 'address', 'workers'])),
            __('messages.booking_cancelled_successfully')
        );

    } catch (\Exception $e) {
        // تحديد كود الـ HTTP المناسب للـ Exception الراجع
        $statusCode = in_array($e->getCode(), [400, 403, 422]) ? $e->getCode() : 500;

        return response()->json([
            'success' => false,
            'message' => $statusCode === 500 ? 'حدث خطأ غير متوقع أثناء معالجة إلغاء الحجز.' : $e->getMessage(),
            'error'   => app()->environment('local') ? $e->getMessage() : null
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
        // تحديد الـ StatusCode بناءً على الخطأ الراجع
        $statusCode = in_array($e->getCode(), [400, 403]) ? $e->getCode() : 500;

        return response()->json([
            'success' => false,
            'message' => $statusCode === 500 ? 'حدث خطأ غير متوقع أثناء تقديم البلاغ.' : $e->getMessage(),
            'error'   => app()->environment('local') ? $e->getMessage() : null
        ], $statusCode);
    }
}
    public function review(ReviewBookingRequest $request, $id)
{
    try {
        $userId = auth()->id();

        // تمرير اللوجيك بالكامل للـ Service بـ سطر واحد نظيف 🌟
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
            'error'   => app()->environment('local') ? $e->getMessage() : null
        ], $statusCode);
    }
}


public function myReports()
{
    $user = auth()->user();
    $reports = Report::query()->where('customer_id',$user->id)
     ->get();

     return $this->successResponse(ReportsResource::collection($reports), __('messages.my_reports_retrieved_successfully'));
}

public function showReport(Report $report){

 return $this->successResponse(new ReportsResource($report), __('messages.report_details_retrieved_successfully'));

}




}
