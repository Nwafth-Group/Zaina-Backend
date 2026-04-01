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

class SearchController extends Controller
{
    use ApiResponseTrait; 
   public function index(Request $request)
    {
        $keyword = $request->input('search');

        if (empty($keyword)) {
            // نجمع أشهر الأقسام والخدمات لنصنع منها (Tags)
            $popularCategories = Category::where('status', 'active')->whereNull('parent_id')
                ->orderBy('sort_order')->limit(5)->pluck('name_en')->toArray();
                
            $popularServices = Service::where('status', 'active')
                // ->where('is_popular', true) // إذا كان لديك هذا الحقل
                ->inRandomOrder()->limit(5)->pluck('name_en')->toArray();

            // دمجهم في مصفوفة واحدة للاقتراحات
            $suggestions = array_unique(array_merge($popularCategories, $popularServices));

            return $this->successResponse([
                'is_suggestions' => true, // عشان الموبايل يعرف إن هاي شاشة الاقتراحات
                'suggestions' => array_values($suggestions),
                'services' => []
            ],'Suggestions fetched successfully.');
        }

        // ==========================================
        // الحالة الثانية: المستخدم كتب كلمة للبحث (Search Results)
        // ==========================================
        $services = Service::where('status', 'active')
            ->where(function ($query) use ($keyword) {
                // البحث في اسم الخدمة
                $query->where('name_ar', 'LIKE', "%{$keyword}%")
                      ->orWhere('name_en', 'LIKE', "%{$keyword}%")
                      // أو البحث في اسم القسم التابعة له الخدمة
                      ->orWhereHas('category', function ($q) use ($keyword) {
                          $q->where('name_ar', 'LIKE', "%{$keyword}%")
                            ->orWhere('name_en', 'LIKE', "%{$keyword}%");
                      });
            })
            // Eager Loading لمنع مشكلة N+1
            ->with(['category:id,name_ar,name_en', 'startingPrice', 'media']) 
            ->paginate(15); // استخدمنا Paginate عشان لو النتائج كثيرة الموبايل يقدر يعمل Scroll

        return $this->successResponse([
            'is_suggestions' => false,
            'suggestions' => [],
            'services' => ServiceResource::collection($services)->response()->getData(true) // ارجاع الداتا مع الـ Pagination
        ], 'Search results fetched successfully.');
    }

    public function filter(Request $request)
    { 
        // نبدأ الـ Query الأساسي (نجلب الخدمات الفعالة فقط)
        $query = Service::where('status', 'active');

        // 1. فلتر الأقسام (Categories - Array)
 
        if ($request->has('categories') && is_array($request->categories)) {
            // بنبحث في الـ category_id أو حتى الـ parent_id لو احتجت
            $query->whereIn('category_id', $request->categories);
        }

        // 2. فلتر التقييم (Rating)
        if ($request->filled('min_rating')) {
            $query->where('avg_rating', '>=', $request->min_rating);
        }

        // 3. فلتر الموقع (Location: City & Area)
        // بنستخدم علاقة التغطية الجغرافية اللي عملناها بجدول service_coverages
        if ($request->filled('city_id')) {
            $query->whereHas('coverages', function ($q) use ($request) {
                $q->where('city_id', $request->city_id);
                // إذا اختار منطقة معينة جوا المدينة
                if ($request->filled('area_id')) {
                    $q->where('area_id', $request->area_id);
                }
            });
        }

        // 4. فلتر السعر (Price Range)
        // بنفلتر جوا علاقة service_prices
      // 4. فلتر السعر (Price Range)
        if ($request->filled('min_price') && $request->filled('max_price')) {
            $now = now()->toDateString();
            $query->whereHas('priceRules', function ($q) use ($request, $now) {
                // أ. التأكد إن التسعيرة فعالة في تاريخ اليوم (نفس منطق startingPrice)
                $q->where(function ($subQ) use ($now) {
                    $subQ->whereNull('effective_start')->orWhere('effective_start', '<=', $now);
                })->where(function ($subQ) use ($now) {
                    $subQ->whereNull('effective_end')->orWhere('effective_end', '>=', $now);
                });

                // إذا اليوزر اختار مدينة في الفلتر، تأكد إن السعر تابع لهي المدينة أو عام (null)
                if ($request->filled('city_id')) {
                    $q->where(function ($subQ) use ($request) {
                        $subQ->where('city_id', $request->city_id)
                             ->orWhereNull('city_id');
                    });
                }
                // ج. فحص نطاق السعر (بما إن عندك نوعين من التسعير، بنفحص الاثنين)
                $q->where(function ($subQ) use ($request) {
                    $subQ->whereBetween('base_price', [$request->min_price, $request->max_price])
                         ->orWhereBetween('price_per_hour', [$request->min_price, $request->max_price]);
                });
            });
        }

        // 5. فلتر التوفر (Availability) -> سيتم برمجته لاحقاً بعد بناء جداول الحجوزات
        // if ($request->filled('date') && $request->filled('time')) {
        //     // TODO: Filter based on Provider Availability
        // }
        // if ($request->filled('date') && $request->filled('time_start') && $request->filled('duration')) {
        
        // $requestedStart = Carbon::parse($request->date . ' ' . $request->time_start);
        // $requestedEnd = $requestedStart->copy()->addMinutes($request->duration);

        // $query->whereDoesntHave('bookings', function ($q) use ($requestedStart, $requestedEnd) {
        //     $q->where('status', '!=', 'cancelled') // نتجاهل الحجوزات الملغية
        //       ->where(function ($subQ) use ($requestedStart, $requestedEnd) {
        //           // معادلة التداخل: (بداية الحجز قبل نهاية الطلب) وَ (نهاية الحجز بعد بداية الطلب)
        //           $subQ->where('scheduled_at', '<', $requestedEnd)
        //                ->where('scheduled_end', '>', $requestedStart);
        //       });
        // });
        // }

        // جلب البيانات مع الـ Eager Loading لمنع الـ N+1
        $services = $query->with(['category:id,name_ar,name_en', 'startingPrice', 'media'])
                          ->paginate(15); // Pagination ضروري جداً

        // ماذا نرجع للموبايل؟ 
        // نرجع نفس الـ ServiceResource اللي عملناه للبحث بالضبط!
        return $this->successResponse([
            'services' => ServiceResource::collection($services)->response()->getData(true)
        ], 'Filtered services retrieved successfully.');
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
