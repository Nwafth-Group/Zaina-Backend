<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\StoreBookingRequest;
use App\Http\Resources\Api\V1\Booking\BookingResource;
use App\Models\Booking;
use App\Models\BookingAssignment;
use App\Models\BookingHistory;
use App\Models\CustomerAddress;
use App\Models\ServicePrice;
use App\Traits\ApiResponseTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
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
        $booking->load(['service.media', 'service.rules', 'address.city', 'address.area', 'worker']);
        
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
        // 🌟 تحويل القيمة إلى رقم صحيح لحل مشكلة Carbon 🌟
        $durationMinutes = (int) $request->duration_minutes;

        $start = Carbon::parse($request->start_time);
        $end = $start->copy()->addMinutes($durationMinutes);

        // 1. الدبل تشيك: هل العامل لا يزال متاحاً؟
        $isWorkerBusy = Booking::where('worker_id', $request->worker_id)
            ->where('booking_date', $request->date)
            ->whereIn('status', ['Upcoming', 'inService'])
            ->where(function ($query) use ($start, $end) {
                $query->where('start_time', '<', $end->format('H:i:s'))
                      ->where('end_time', '>', $start->format('H:i:s'));
            })
            ->exists();

        if ($isWorkerBusy) {
            return response()->json(['success' => false, 'message' => __('messages.worker_busy') ?? 'عذراً، هذا العامل تم حجزه للتو. يرجى اختيار وقت أو عامل آخر.'], 422);
        }

        // 2. حساب الأسعار بناءً على عنوان العميل (المنطقة والمدينة)
        $address = CustomerAddress::with('area')->findOrFail($request->address_id);
        $areaId = $address->area_id;
        $cityId = $address->area->city_id;

        $today = now()->toDateString();

        // استعلام التسعير الذكي
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
            ->orderByRaw("FIELD(pricing_scope, 'area', 'city')") // أولوية لسعر المنطقة
            ->first();

        if (!$servicePrice) {
            return response()->json(['success' => false, 'message' => __('messages.service_not_available') ?? 'عذراً، هذه الخدمة غير متوفرة أو غير مسعرة في منطقتك حالياً.'], 400);
        }

        // حساب المجموع
        $hours = $durationMinutes / 60;
        $subtotal = ((float) $servicePrice->base_price) + (((float) $servicePrice->price_per_hour) * $hours);
        $tax = 0; // ممكن تعدله لاحقاً حسب نظام الضريبة عندك
        $total = $subtotal + $tax;

        DB::beginTransaction();
        try {
            // 3. إنشاء الحجز
            $booking = Booking::create([
                'booking_ref'      => 'HC-' . rand(100000, 999999),
                'customer_id'      => auth()->id(),
                'service_id'       => $request->service_id,
                'address_id'       => $request->address_id,
                'worker_id'        => $request->worker_id,
                'booking_date'     => $request->date,
                'start_time'       => $start->format('H:i:s'),
                'end_time'         => $end->format('H:i:s'),
                'duration_minutes' => $durationMinutes,
                'price_subtotal'   => $subtotal,
                'tax_amount'       => $tax,
                'discount_amount'  => 0,
                'total_amount'     => $total,
                'status'           => 'Upcoming',
                'payment_status'   => 'unpaid',
            ]);

            // 4. تسجيل التعيين
            BookingAssignment::create([
                'booking_id' => $booking->id,
                'worker_id'  => $request->worker_id,
                'status'     => 'active',
            ]);

            // 5. تسجيل التاريخ
            BookingHistory::create([
                'booking_id' => $booking->id,
                'new_status' => 'Upcoming',
                'note'       => 'تم إنشاء الحجز من قبل العميل',
                'created_by' => auth()->id(),
            ]);

            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => __('messages.booking_created_successfully') ?? 'تم إنشاء الحجز بنجاح',
                'data' => new BookingResource($booking)
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'حدث خطأ أثناء الحجز.', 'error' => $e->getMessage()], 500);
        }
    }
    /**
     * Display the specified resource.
     */
 

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
