<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Category\CategoryResourse;
use App\Http\Resources\Api\V1\Service\ServiceResource;
use App\Models\Category;
use App\Models\Service;
use App\Traits\ApiResponseTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class SearchController extends Controller
{
    use ApiResponseTrait; 

public function index(Request $request)
{
    $keyword = $request->input('search');
    
    // 🌟 1. تحديد حقل الاسم ديناميكياً بناءً على لغة الـ API الحالية (ar أو en)
    $locale = App::getLocale(); // سيعود بـ 'ar' أو 'en' تلقائياً حسب الـ Accept-Language
    $nameField = 'name_' . $locale; 

    if (empty($keyword)) {
        // نجمع أشهر الأقسام والخدمات لنصنع منها (Tags) باللغة الحالية للمستخدم
        $popularCategories = Category::where('status', 'active')
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->limit(5)
            ->pluck($nameField) // 🌟 سحب الحقل المترجم ديناميكياً
            ->toArray();
            
        $popularServices = Service::where('status', 'active')
            ->inRandomOrder()
            ->limit(5)
            ->pluck($nameField) // 🌟 سحب الحقل المترجم ديناميكياً
            ->toArray();

        // دمجهم في مصفوفة واحدة للاقتراحات
        $suggestions = array_unique(array_merge($popularCategories, $popularServices));

        return $this->successResponse([
            'is_suggestions' => true,
            'suggestions' => array_values($suggestions),
            'services' => []
        ], __('messages.suggestions_fetched') ?? 'Suggestions fetched successfully.');
    }

    // ==========================================
    // الحالة الثانية: المستخدم كتب كلمة للبحث (Search Results)
    // ==========================================
    $services = Service::where('status', 'active')
        ->where(function ($query) use ($keyword) {
            // البحث الذكي في الاسمين العربي والإنجليزي معاً لضمان ظهور النتائج بأي لغة يكتبها المستخدم
            $query->where('name_ar', 'LIKE', "%{$keyword}%")
                  ->orWhere('name_en', 'LIKE', "%{$keyword}%")
                  
                  // أو البحث في اسم القسم التابعة له الخدمة
                  ->orWhereHas('category', function ($q) use ($keyword) {
                      $q->where('name_ar', 'LIKE', "%{$keyword}%")
                        ->orWhere('name_en', 'LIKE', "%{$keyword}%");
                  });
        })
        // 🌟 تعديل الـ Eager Loading: جلب الحقول مع الحفاظ على العلاقات كاملة للـ Resource
        ->with(['category', 'media']) 
        ->paginate($request->input('per_page', 15));

    return $this->successResponse([
        'is_suggestions' => false,
        'suggestions' => [],
        // الـ ServiceResource الذي قمنا بتطويره سابقاً سيتولى قراءة الأسعار واللغات والتنسيق الموحد
        'services' => ServiceResource::collection($services)->response()->getData(true)
    ], __('messages.search_results_fetched') ?? 'Search results fetched successfully.');
}
   public function filter(Request $request)
{ 
    // نبدأ الـ Query الأساسي للخدمات
    $query = Service::where('status', 'active');

    // 1. فلتر الأقسام المتعددة (Categories - Array)
    if ($request->has('categories') && is_array($request->categories)) {
        $query->whereIn('category_id', $request->categories);
    }

    // 2. فلتر التقييم (Rating)
    if ($request->filled('min_rating')) {
        $query->where('avg_rating', '>=', $request->min_rating);
    }

    // 3. فلتر التغطية الجغرافية (Geo-Fenced Service Zone)
    if ($request->filled('service_zone_id')) {
        $query->whereHas('serviceZones', function ($q) use ($request) {
            $q->where('service_zones.id', $request->service_zone_id)
              ->where('service_zones.status', 'active');
        });
    }

    // 4. فلتر السعر (Price Range)
    if ($request->filled('min_price') && $request->filled('max_price')) {
        $query->whereBetween('base_price', [$request->min_price, $request->max_price]);
    }

    // 🌟 5. فلتر التوفر والمواعيد الذكي (Availability Filter) 🌟
    // 🌟 5. فلتر التوفر والمواعيد الذكي (Availability Filter المتطابق مع الفيجما) 🌟
    if ($request->filled('date') && $request->filled('start_time') && $request->filled('end_time')) {
        
        $bookingDate  = $request->input('date');       // مثال: 2026-05-12
        $startTimeStr = $request->input('start_time'); // مثال: 10:00
        $endTimeStr   = $request->input('end_time');   // مثال: 13:00   
        $dayName = strtolower(Carbon::parse($bookingDate)->format('l')); // اسم اليوم (saturday, sunday...)

        // أ. فحص شفتات دوام الخدمة: يجب أن يقع الشفت المختار بالكامل داخل دوام الخدمة الرسمي
        $query->whereHas('schedules', function ($q) use ($dayName, $startTimeStr, $endTimeStr) {
            $q->where('day', $dayName)
              ->where('is_active', true)
              ->where('start_time', '<=', $startTimeStr) // شفت الخدمة يبدأ قبل أو مع بداية حجز العميل
              ->where('end_time', '>=', $endTimeStr);    // شفت الخدمة ينتهي بعد أو مع نهاية حجز العميل
        });

        // ب. فحص تضاربات العمال: نضمن وجود عامل يغطي المنطقة وليس لديه حجز يتداخل مع هذه الفترة
        $query->whereHas('workers', function ($workerQ) use ($request, $bookingDate, $startTimeStr, $endTimeStr) {
            // الفحص الجغرافي للمنطقة الجغرافية
            if ($request->filled('service_zone_id')) {
                $workerQ->whereHas('serviceZones', function($zoneQ) use ($request) {
                    $zoneQ->where('service_zones.id', $request->service_zone_id);
                });
            }

            // فحص التداخل الزمني (Overlap Check) لحجوزات العامل النشطة
            // العامل يكون متاحاً إذا لم يكن لديه أي حجز يتقاطع مع الفترة [start_time - end_time]
            $workerQ->whereDoesntHave('bookings', function ($bookingQ) use ($bookingDate, $startTimeStr, $endTimeStr) {
                $bookingQ->whereIn('status', ['Upcoming', 'inService', 'completed']) 
                         ->where('booking_date', $bookingDate)
                         ->where(function ($overlapQ) use ($startTimeStr, $endTimeStr) {
                             // قاعدة التداخل الرياضية للمواعيد: (StartA < EndB) AND (EndA > StartB)
                             $overlapQ->where('start_time', '<', $endTimeStr)
                                      ->where('end_time', '>', $startTimeStr);
                         });
            });
        });
    }

    // جلب البيانات مع الـ Eager Loading والـ Localization الموحد
    $services = $query->with(['category', 'media'])->paginate($request->input('per_page', 15));

    return $this->successResponse([
        'services' => ServiceResource::collection($services)->response()->getData(true)
    ], __('messages.filtered_services_retrieved') ?? 'Filtered services retrieved successfully.');
}



    /**
     * Store a newly created resource in storage.
     */
    // public function store(Request $request)
    // {
    //     //
    // }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
