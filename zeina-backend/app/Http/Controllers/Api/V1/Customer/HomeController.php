<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Category\CategoryResourse;
use App\Http\Resources\Api\V1\Media\MediaResource;
use App\Http\Resources\Api\V1\Service\ServiceResource;
use App\Models\Ad;
use App\Models\Booking;
use App\Models\Category;
use App\Models\Service;
use App\Traits\ApiResponseTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    use ApiResponseTrait; 
    /**
     * Display a listing of the resource.
     */
   public function index(Request $request)
    { 
       
        // 1. الإعلانات (Banners)
        $now = now()->toDateString();
        $banners = Ad::where('status', 'active')
            ->where(function ($query) use ($now) {
                $query->whereNull('start_date')
                      ->orWhere(function ($q) use ($now) {
                          $q->where('start_date', '<=', $now)
                            ->where('end_date', '>=', $now);
                      });
            })
            ->with(['media'])
            ->get();

        // 2. الأقسام الرئيسية فقط (Main Categories) - Limit 4
        $categories = Category::with('media') // لجلب الأيقونات
            ->whereNull('parent_id') // الأقسام الرئيسية فقط
            ->where('status', 'active')
            ->orderBy('sort_order', 'asc')
            ->limit(4)
            ->get();

            $currentDate = now()->toDateString();
        $currentTime = now()->toTimeString();

            
            // 3. الموعد القادم (Upcoming Appointment)
            $upcomingBooking = null;
            $user = auth()->user();
            
            $booking = Booking::with(['service.media'])
            ->where('customer_id', $user->id)
            // 🌟 تحديث حالات الحجز بناءً على الـ Enum الجديد 🌟
            ->whereIn('status', ['Upcoming', 'inService']) 
            // 🌟 الفلترة باستخدام التاريخ والوقت الجديد 🌟
            ->where(function ($query) use ($currentDate, $currentTime) {
                $query->where('booking_date', '>', $currentDate) // الأيام القادمة
                      ->orWhere(function ($q) use ($currentDate, $currentTime) {
                          $q->where('booking_date', '=', $currentDate) // أو اليوم
                            ->where('start_time', '>=', $currentTime); // بس الوقت لسا ما إجا
                      });
            })
            ->orderBy('booking_date', 'asc') // الترتيب حسب التاريخ الأقرب
            ->orderBy('start_time', 'asc')   // ثم الوقت الأقرب
            ->first();
            
        
            if ($booking) {
            // حساب المدة
            $hours = floor($booking->duration_minutes / 60);
            $durationText = $hours > 0 ? "{$hours} Hours" : "{$booking->duration_minutes} Mins";
            $mediaService = $booking->service->media()->where('collection', 'service/icon')->first();
            
            $upcomingBooking = [
                'id' => $booking->id,
                'booking_ref' => $booking->booking_ref,
                'service_name_ar' => $booking->service->name_ar,
                'service_name_en' => $booking->service->name_en,
                'image' => $mediaService ? (new MediaResource($mediaService))->getFileUrl() : null ,
                'price' => $booking->total_amount,
                'currency' => 'EGP',
                'status' => $booking->status,
                'duration_text' => $durationText,
                
                // 🌟 تعديل قراءة التاريخ والوقت بناءً على الحقول الجديدة 🌟
                'date_day' => Carbon::parse($booking->booking_date)->format('d'), // من حقل التاريخ
                'date_month' => Carbon::parse($booking->booking_date)->format('M'), // من حقل التاريخ
                'time' => Carbon::parse($booking->start_time)->format('h:i A'), // من حقل الوقت
            ];
        }
        
        $popularServices = Service::with(['category:id,name_ar,name_en', 'startingPrice', 'media'])
            ->where('status', 'active')
            ->orderBy('avg_rating', 'desc')
            ->limit(5)
            ->get();

        // 5. تجميع البيانات وإرجاعها
        return $this->successResponse([
            'banners' => $banners,
            'categories' => CategoryResourse::collection($categories),
            'upcoming_appointment' => $upcomingBooking,
            'popular_services' => ServiceResource::collection($popularServices),
        ], 'Home page data fetched successfully.');
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
