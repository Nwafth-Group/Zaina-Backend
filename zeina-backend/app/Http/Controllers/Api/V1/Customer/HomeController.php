<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\Category;
use App\Models\Service;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    use ApiResponseTrait; 
    /**
     * Display a listing of the resource.
     */
   public function index(Request $request)
    {
        // 1. جلب الإعلانات (Banners) - مطابقة لأول جزء في الشاشة
        $now = now()->toDateString();
        $ads = Ad::where('status', 'active')
            ->where(function ($query) use ($now) {
                $query->whereNull('start_date')
                      ->orWhere(function ($q) use ($now) {
                          $q->where('start_date', '<=', $now)
                            ->where('end_date', '>=', $now);
                      });
            })
            ->get();

        $categories = Category::where('status', 'active')
            ->orderBy('sort_order', 'asc') // الترتيب حسب الأهمية للإدارة
            ->limit(4) // نعرض 4 فقط في الرئيسية عشان ما نخرب شكل الـ UI
            ->get();

        // 3. جلب الخدمات الأكثر طلباً (Popular Services) - مطابقة لآخر جزء
        $popularServices = Service::where('status', 'active')
            // ->where('is_popular', true) // إذا ضفت الحقل
            ->with([
                'category',
                'startingPrice'
            ])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->select('id','category_id', 'type', 'name_ar', 'name_en', /*'image', 'rating_average'*/)
            ->get();

        // 4. تجميع البيانات
        $homeData = [
            'banners'          => $ads,
            'categories'       => $categories,
            'popular_services' => $popularServices,
        ];

        return $this->successResponse($homeData, __('messages.home_date_retrieve'));
    }
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

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
