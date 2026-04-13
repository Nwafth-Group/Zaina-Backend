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
    // 1. معرفة اليوم من التاريخ المبعوث
    $date = Carbon::parse($request->date);
    $dayName = strtolower($date->format('l')); // مثلاً: 'saturday'

    // 2. جلب الشفتات الخاصة بهذا اليوم من جدول service_schedules
    $schedules = $service->schedules()
        ->where('day', $dayName)
        ->where('is_active', true)
        ->get();

    $shiftsData = [];

    // 3. تجهيز الداتا لتطابق الـ Dropdown والـ Pills في التصميم
    foreach ($schedules as $schedule) {
        $start = Carbon::parse($schedule->start_time);
        $end = Carbon::parse($schedule->end_time);
        
        // تسمية الشفت بناءً على الوقت (اختياري عشان الـ Dropdown)
        $shiftName = $start->hour < 12 ? 'Morning Shift' : 'Evening Shift';
        $dropdownLabel = $shiftName . ' - ' . $start->format('h:i A') . ' - ' . $end->format('h:i A');

        // توليد الساعات (Pills) داخل هذا الشفت (كل ساعة موعد)
        $timeSlots = [];
        $currentSlot = $start->copy();
        
        // طول ما الوقت الحالي أقل من وقت نهاية الشفت، ضيف ساعة
        while ($currentSlot->lt($end)) {
            $timeSlots[] = $currentSlot->format('H:i'); // "09:00", "10:00"
            $currentSlot->addHour(); // أو حسب مدة الخدمة (Duration)
        }

        // إضافة الشفت للمصفوفة النهائية
        $shiftsData[] = [
            'id' => $schedule->id,
            'dropdown_label' => $dropdownLabel, // للنص اللي جوا الـ Select Time Slot
            'start_time' => $start->format('H:i'),
            'end_time' => $end->format('H:i'),
            'available_hours' => $timeSlots // الأزرار اللي تحت (09:00, 10:00...)
        ];
    }

    return $this->successResponse([
        'shifts' => $shiftsData
    ],__('messages.shifts_retrieved'));

    return response()->json([
        'status' => 200,
        'message' => 'تم جلب الشفتات بنجاح',
        'data' => $shiftsData
    ]);
}

   public function getAvailableWorkers(GetAvailableWorker $request, Service $service)
{
    $date = $request->date;
    $start = Carbon::parse($request->start_time)->format('H:i:s');
    
    $end = Carbon::parse($request->start_time)->addMinutes((int) $request->duration)->format('H:i:s');

    // 1. جلب العمال المتاحين (الذين ليس لديهم تعارض)
    $availableWorkers = $service->workers()->where('status', 'active')
        ->whereDoesntHave('bookings', function ($q) use ($date, $start, $end) {
            $q->where('booking_date', $date)
              ->whereIn('status', ['Upcoming', 'inService'])
              // التضارب الزمني
              ->where(function ($timeQ) use ($start, $end) {
                  $timeQ->where('start_time', '<', $end)
                        ->where('end_time', '>', $start);
              });
        })->get();

    // 2. فصل العمال (مفضلين وغير مفضلين)
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
