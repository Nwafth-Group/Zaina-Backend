<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\GetAvailableWorker;
use App\Http\Requests\Api\V1\Customer\GetShiftsForDateRequest;
use App\Http\Resources\Api\V1\Service\ServiceResource;
use App\Http\Resources\Api\V1\Worker\WorkerResource;
use App\Models\Booking;
use App\Models\BookingAssignment;
use App\Models\Category;
use App\Models\Service;
use App\Models\ServiceSchedule;
use App\Traits\ApiResponseTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    use ApiResponseTrait;
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // تقدر تمرر عدد العناصر بالصفحة من الـ Request أو تثبته (مثلا 10)
        $perPage = $request->input('per_page', 10);

        $services = Service::with([
                'category', 
                'startingPrice',
                'media'
            ])
            ->where('status', 'active') // تأكد إن الخدمة مفعلة
            ->paginate($perPage);


        return $this->successResponse(ServiceResource::collection($services),__('messages.services_retrieved'));
    }

    // 2. جلب الخدمات الخاصة بقسم معين (مهمة جداً عشان الصورة الثانية عندك)
    // لما يضغط على Cleaning يجيبله الـ Cleaning List
    public function getServicesByCategory(Category $category, Request $request)
    {
        $perPage = $request->input('per_page', 10);

        $services = Service::with(['category','startingPrice', 'media'])
            ->where('category_id', $category->id) // فلترة حسب القسم
            ->where('status', 'active')
            ->paginate($perPage);

            return $this->successResponse(ServiceResource::collection($services),__('messages.services_retrieved'));
    }

    public function show(Service $service)
    {
        if ($service->status !== 'active') {
            return $this->errorResponse(__('messages.service_not_available'), 404);
        }

        $service->load(['category', 'duration', 'rules', 'coverages', 'coverages.city', 'coverages.area', 'startingPrice', 'media' , 'schedules']);

        return $this->successResponse(new ServiceResource($service),__('messages.service_details_retrieved'));
    }


public function getShiftsForDate(GetShiftsForDateRequest $request, Service $service)
{
    $date = Carbon::parse($request->date);
    $dayName = strtolower($date->format('l'));
    
    // 1. جلب الشفتات والدوام الرسمي للخدمة في هذا اليوم
    $schedules = $service->schedules()->where('day', $dayName)->where('is_active', true)->get();
    
    // جلب طول الكتلة الثابتة للخدمة (بالدقائق)
    $periodMinutes = ($service->duration->service_period_hours ?? 2) * 60;

    // 2. جلب جميع عمال الخدمة النشطين
    $workerQuery = $service->workers()->where('status', 'active');    
    $workerIds = $workerQuery->pluck('workers.id');

    // 🌟 التعديل الجوهري: جلب الحجوزات القائمة للعمال من جدول الـ BookingAssignment الوسيط 🌟
    // نسحب الـ worker_id وأوقات الحجز لربطها في الفحص داخل الـ Loop
    $existingBookings = BookingAssignment::whereIn('worker_id', $workerIds)
        ->where('status', 'active') // التخصيص فعال وليس ملغياً
        ->whereHas('booking', function ($q) use ($date) {
            $q->where('booking_date', $date->format('Y-m-d'))
              ->whereIn('status', ['Upcoming', 'inService']); // الحجوزات النشطة فقط
        })
        ->with('booking:id,start_time,end_time') // شحن المواعيد لمنع الـ N+1 أثناء الـ Loop
        ->get()
        ->map(function ($assignment) {
            return [
                'worker_id'  => $assignment->worker_id,
                'start_time' => $assignment->booking->start_time,
                'end_time'   => $assignment->booking->end_time,
            ];
        });

    $shiftsData = [];
    $isToday = $date->isToday(); // فحص هل التاريخ المطلوب هو اليوم؟
    $now = now(); // الوقت الحالي الفعلي للسيرفر

    foreach ($schedules as $schedule) {
      
        $shiftStart = Carbon::parse($schedule->start_time);
        $shiftEnd = Carbon::parse($schedule->end_time);
        
        $dropdownLabel = "Shift: " . $shiftStart->format('h:i A') . ' - ' . $shiftEnd->format('h:i A');
        $fixedBlocks = [];
        $currentBlockStart = $shiftStart->copy();

        // القفز بمقدار الكتلة الثابتة 
        while ($currentBlockStart->copy()->addMinutes($periodMinutes)->lte($shiftEnd)) {
            $blockEnd = $currentBlockStart->copy()->addMinutes($periodMinutes);
            
            // إذا كان التاريخ هو اليوم، نتخطى الكتل التي مرت في الماضي
            if ($isToday) {
                $blockEndDateTime = Carbon::parse($date->format('Y-m-d') . ' ' . $blockEnd->format('H:i:s'));
                
                if ($blockEndDateTime->lte($now)) {
                    $currentBlockStart->addMinutes($periodMinutes);
                    continue;
                }
            }

            $startStr = $currentBlockStart->format('H:i:s');
            $endStr = $blockEnd->format('H:i:s');
            
            // فحص هل يوجد على الأقل عامل واحد متاح في هذه الكتلة بالكامل؟
            $isBlockAvailable = false;
            foreach ($workerIds as $workerId) {
                // الفحص يتم الآن بناءً على مصفوفة الحجوزات المستخرجة من جدول الـ Assignments
                $hasConflict = $existingBookings->where('worker_id', $workerId)
                    ->where('start_time', '<', $endStr)
                    ->where('end_time', '>', $startStr)
                    ->isNotEmpty();

                if (!$hasConflict) {
                    $isBlockAvailable = true;
                    break; // وجدنا عاملاً واحداً متاحاً على الأقل، إذن الكتلة صالحة للعرض بالـ UI
                }
            }

            if ($isBlockAvailable) {
                $fixedBlocks[] = [
                    'start_time' => $currentBlockStart->format('H:i'),
                    'end_time'   => $blockEnd->format('H:i'),
                    'label'      => $currentBlockStart->format('h:i A') . ' - ' . $blockEnd->format('h:i A'),
                ];
            }

            // الانتقال لبداية الكتلة التالية مباشرة لمنع التداخل
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
public function getAvailableWorkers(Request $request, Service $service)
{
    $request->validate([
        'date'          => 'required|date_format:Y-m-d',
        'start_time'    => 'required|date_format:H:i',
        'periods_count' => 'required|integer|min:1'
    ],
    [
        'date.required'          => __('validation.getAvailableWorkers.date_required'),
        'date.date_format'       => __('validation.getAvailableWorkers.date_format'),
        'start_time.required'    => __('validation.getAvailableWorkers.start_time_required'),
        'start_time.date_format' => __('validation.getAvailableWorkers.start_time_format'),
        'periods_count.required' => __('validation.getAvailableWorkers.periods_count_required'),
        'periods_count.integer'  => __('validation.getAvailableWorkers.periods_count_integer'),
        'periods_count.min'      => __('validation.getAvailableWorkers.periods_count_min'),
    ]);

    $periodHours = $service->duration->service_period_hours ?? 2;
    $totalMinutes = $request->periods_count * $periodHours * 60;

    $startStr = Carbon::parse($request->start_time)->format('H:i:s');
    $endStr = Carbon::parse($request->start_time)->addMinutes($totalMinutes)->format('H:i:s');

    // 🌟 1. التعديل الجوهري: جلب العمال النشطين الذين "ليس لديهم" تخصيص حجز (Assignment) نشط يتداخل مع هذه الفترة 🌟
    $availableWorkers = $service->workers()->where('status', 'active')
        ->whereDoesntHave('workers', function ($q) use ($request, $startStr, $endStr) {
            $q->where('status', 'active') // التخصيص فعال في جدول الـ booking_assignments
              ->whereHas('booking', function ($bookingQ) use ($request, $startStr, $endStr) {
                  $bookingQ->where('booking_date', $request->date)
                           ->whereIn('status', ['Upcoming', 'inService']) // الحجوزات القائمة فقط
                           ->where(function ($timeQ) use ($startStr, $endStr) {
                               $timeQ->where('start_time', '<', $endStr)
                                     ->where('end_time', '>', $startStr);
                           });
              });
        })->get();

    // 🌟 2. استعادة سحر العمال المفضلين (Preferred Workers) 🌟
    $userId = auth('sanctum')->id(); 

    // جلب معرفات العمال الذين أتموا حجوزات سابقة ناجحة لنفس هذا العميل وفي نفس هذه الخدمة
    // هنا نبحث أيضاً في جدول الـ assignments لضمان دقة جلب العمال التاريخية
    $preferredIds = BookingAssignment::whereHas('booking', function($q) use ($userId, $service) {
            $q->where('customer_id', $userId)
              ->where('service_id', $service->id)
              ->where('status', 'completed');
        })
        ->pluck('worker_id')
        ->unique()
        ->toArray();

    // فصل العمال المتاحين إلى مجموعتين (مفضلين وآخرين) بناءً على تاريخ تعاملهم مع العميل
    $preferred = $availableWorkers->whereIn('id', $preferredIds)->values();
    $others    = $availableWorkers->whereNotIn('id', $preferredIds)->values();

    return $this->successResponse([
        'preferred_workers' => WorkerResource::collection($preferred),
        'other_workers'     => WorkerResource::collection($others),
    ], __('messages.providers_fetch_success') ?? 'تم جلب مقدمي الخدمة المتاحين بنجاح.');
}




}
