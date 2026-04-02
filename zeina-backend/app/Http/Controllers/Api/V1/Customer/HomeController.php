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

            
            // 3. الموعد القادم (Upcoming Appointment)
            $upcomingBooking = null;
            $user = auth()->user();
            
            $booking = Booking::with(['service.media'])
            ->where('customer_id', $user->id)
            ->whereIn('status', ['new', 'confirmed', 'on_way']) // الحالات اللي لسا ما خلصت
            ->where('scheduled_at', '>=', now()) // المواعيد المستقبلية فقط
            ->orderBy('scheduled_at', 'asc') // أقرب موعد
            ->first();
            
        
            if ($booking) {
    
                // حساب المدة (ساعات ودقائق) لتطابق التصميم (مثال: 3 Hours)
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
                    'currency' => 'EGP', // أو من إعدادات النظام
                    'status' => $booking->status,
                    'duration_text' => $durationText,
                    // فصل التاريخ لتطابق الـ UI المربع الزهري على اليمين
                    'date_day' => Carbon::parse($booking->scheduled_at)->format('d'), // مثال: 10
                    'date_month' => Carbon::parse($booking->scheduled_at)->format('M'), // مثال: Nov
                    'time' => Carbon::parse($booking->scheduled_at)->format('h:i A'), // مثال: 10:00 AM
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
