<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\GetAvailableWorker;
use App\Http\Requests\Api\V1\Customer\GetShiftsForDateRequest;
use App\Http\Resources\Api\V1\Service\ServiceResource;
use App\Http\Resources\Api\V1\Worker\WorkerResource;
use App\Models\Booking;
use App\Models\Category;
use App\Models\Service;
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
    // جلب الشفتات
   
    $schedules = $service->schedules()->where('day', $dayName)->where('is_active', true)->get();
    if ($schedules->isEmpty()) {
        return $this->errorResponse(__('messages.service_not_available_in_this_day'), 404);
    }

    // جلب الحد الأدنى لمدة الخدمة (افتراضي ساعة واحدة إذا لم يتم تحديده)
    $serviceDuration = $service->duration;
    $minDurationMinutes = ($serviceDuration->min_duration_hours ?? 1) * 60;

    // جلب كل حجوزات عمال هذه الخدمة في هذا اليوم لمرة واحدة (لتقليل استعلامات قاعدة البيانات)
    $workerIds = $service->workers()->where('status', 'active')->pluck('workers.id');
    $existingBookings = Booking::whereIn('worker_id', $workerIds)
        ->where('booking_date', $date->format('Y-m-d'))
        ->whereIn('status', ['Upcoming', 'inService'])
        ->get(['worker_id', 'start_time', 'end_time']);

    $shiftsData = [];
    foreach ($schedules as $schedule) {
        $shiftStart = Carbon::parse($schedule->start_time);
        $shiftEnd = Carbon::parse($schedule->end_time);
        
        $shiftName = $shiftStart->hour < 12 ? 'Morning Shift' : 'Evening Shift';
        $dropdownLabel = $shiftName . ' - ' . $shiftStart->format('h:i A') . ' - ' . $shiftEnd->format('h:i A');

        $timeSlots = [];
        $currentSlotStart = $shiftStart->copy();
        $stepMinutes = 60; // الخطوة كل ساعة (يمكنك جعلها 30 إذا أردت)

        // نمشي في الشفت طالما أن (الوقت الحالي + الحد الأدنى) لا يتجاوز نهاية الشفت
        while ($currentSlotStart->copy()->addMinutes($minDurationMinutes)->lte($shiftEnd)) {
            $slotStartStr = $currentSlotStart->format('H:i:s');
            $slotEndStr = $currentSlotStart->copy()->addMinutes($minDurationMinutes)->format('H:i:s');
            $isSlotAvailable = false;
            // هل يوجد عامل واحد على الأقل متاح؟
            foreach ($workerIds as $workerId) {
                // نبحث إذا كان هذا العامل لديه تضارب في هذا الوقت
                $hasConflict = $existingBookings->where('worker_id', $workerId)
                    ->where('start_time', '<', $slotEndStr)
                    ->where('end_time', '>', $slotStartStr)
                    ->isNotEmpty();

                // إذا وجدنا عاملاً بدون تضارب، إذن الوقت متاح، ونكسر حلقة العمال للوقت الحالي
                if (!$hasConflict) {
                    $isSlotAvailable = true;
                    break;
                }
            }

            // إذا الوقت متاح، نضيفه للمصفوفة
            if ($isSlotAvailable) {
                $timeSlots[] = $currentSlotStart->format('H:i');
            } 

            $currentSlotStart->addMinutes($stepMinutes);
        }

        // لا نرسل الشفت للموبايل إلا إذا كان فيه ساعات متاحة
        if (count($timeSlots) > 0) {
            $shiftsData[] = [
                'id' => $schedule->id,
                'dropdown_label' => $dropdownLabel,
                'start_time' => $shiftStart->format('H:i'),
                'end_time' => $shiftEnd->format('H:i'),
                'available_hours' => $timeSlots
            ];
        }
    }

    return $this->successResponse(['shifts' => $shiftsData], __('messages.shifts_retrieved'));
}


public function getAvailableDurations(Request $request, Service $service)
{
    $request->validate([
        'date' => 'required|date_format:Y-m-d',
        'start_time' => 'required|date_format:H:i',
    ],
    [
        'data.required' => __('validation.getAvaliableDurations.date_required'),
        'date.date_format' => __('validation.getAvaliableDurations.date_format'),
        'start_time.required' => __('validation.getAvaliableDurations.start_time_required'),
        'start_time.date_format' => __('validation.getAvaliableDurations.start_time_format'),
    ]);
$date = Carbon::parse($request->date);
    $startTimeStr = Carbon::parse($request->start_time)->format('H:i:s');
    $dayName = strtolower($date->format('l'));

    // 🌟 التعديل 1: جلب الشفت الخاص بهذا اليوم لمعرفة متى ينتهي الدوام 🌟
    $schedule = $service->schedules()
        ->where('day', $dayName)
        ->where('is_active', true)
        ->where('start_time', '<=', $startTimeStr)
        ->where('end_time', '>', $startTimeStr)
        ->first();

    // حماية إضافية لو أرسل الموبايل وقت خارج الشفتات
    if (!$schedule) {
        return response()->json(['success' => false, 'message' => 'لا يوجد شفت فعال يغطي وقت البداية المختار.'], 400);
    }

    $shiftEndStr = Carbon::parse($schedule->end_time)->format('H:i:s');

    $serviceDuration = $service->duration;
    // نفترض أن allowed_durations مخزنة كـ Array في قاعدة البيانات مثلاً [2, 3, 4, 6, 8]
    $allowedDurationsHours = $serviceDuration->allowed_durations ?? [1]; 

    $workerIds = $service->workers()->where('status', 'active')->pluck('workers.id');
    $existingBookings = Booking::whereIn('worker_id', $workerIds)
        ->where('booking_date', $date->format('Y-m-d'))
        ->whereIn('status', ['Upcoming', 'inService'])
        ->get(['worker_id', 'start_time', 'end_time']);

    $validDurations = [];

    // نفحص كل مدة مسموحة (مثلاً 2 ساعة، 3 ساعات)
    foreach ($allowedDurationsHours as $durationHours) {
        $durationMinutes = $durationHours * 60;
        $endTimeStr = Carbon::parse($startTimeStr)->addMinutes($durationMinutes)->format('H:i:s');
        
        // 🌟 التعديل 2: حماية تمنع المدة من تجاوز وقت نهاية الشفت 🌟
        // إذا كان وقت الانتهاء يتجاوز نهاية الشفت، إذن هذه المدة غير صالحة
        if ($endTimeStr > $shiftEndStr) {
            continue; // نعمل Skip لهاد اللوب وننتقل للمدة اللي بعدها
        }

        $isDurationAvailable = false;

        foreach ($workerIds as $workerId) {
            $hasConflict = $existingBookings->where('worker_id', $workerId)
                ->where('start_time', '<', $endTimeStr)
                ->where('end_time', '>', $startTimeStr)
                ->isNotEmpty();

            if (!$hasConflict) {
                $isDurationAvailable = true;
                break;
            }
        }

        // إذا كان هناك عامل يقدر يغطي هذه المدة بالكامل، نرجعها للعميل
        if ($isDurationAvailable) {
            $validDurations[] = [
                'hours' => $durationHours,
                'minutes' => $durationMinutes,
                'label' => $durationHours .' ساعات',
                'label-hour' => \Carbon\Carbon::parse($startTimeStr)->format('h:i A') . ' - ' . \Carbon\Carbon::parse($endTimeStr)->format('h:i A')
            ];
        }
    }

    return $this->successResponse(['durations' => $validDurations], 'تم جلب المدد بنجاح');
}

  public function getAvailableWorkers(Request $request, Service $service)
{
    $request->validate([
        'date' => 'required|date_format:Y-m-d',
        'start_time' => 'required|date_format:H:i',
        'duration' => 'required|integer' // تأتي بالدقائق (مثلاً 120 لساعتين)
    ],
    [
        'date.required' => __('validation.getAvailableWorkers.date_required'),
        'date.date_format' => __('validation.getAvailableWorkers.date_date_format'),
        'start_time.required' => __('validation.getAvailableWorkers.start_time_required'),
        'start_time.date_format' => __('validation.getAvailableWorkers.start_time_date_format'),
        'duration.required' => __('validation.getAvailableWorkers.duration_minutes_required'),
        'duration.integer' => __('validation.getAvailableWorkers.duration_minutes_integer'),
    ]);

    $date = $request->date;
    $startStr = Carbon::parse($request->start_time)->format('H:i:s');
    $endStr = Carbon::parse($request->start_time)->addMinutes((int) $request->duration)->format('H:i:s');

    // جلب العمال المتاحين في هذه الفترة بالتحديد
    $availableWorkers = $service->workers()->where('status', 'active')
        ->whereDoesntHave('bookings', function ($q) use ($date, $startStr, $endStr) {
            $q->where('booking_date', $date)
              ->whereIn('status', ['Upcoming', 'inService'])
              ->where(function ($timeQ) use ($startStr, $endStr) {
                  $timeQ->where('start_time', '<', $endStr)
                        ->where('end_time', '>', $startStr);
              });
        })->get();

    // فصل العمال مفضلين وغير مفضلين
    $userId = auth()->id();
    $preferredIds = Booking::where('customer_id', $userId)
        ->where('service_id', $service->id)
        ->where('status', 'completed')
        ->pluck('worker_id')->toArray();

    $preferred = $availableWorkers->whereIn('id', $preferredIds)->values();
    $others = $availableWorkers->whereNotIn('id', $preferredIds)->values();

    return $this->successResponse([
        'preferred_workers' => WorkerResource::collection($preferred),
        'other_workers' => WorkerResource::collection($others),
    ], __('messages.providers_fetch_success'));
}

}
