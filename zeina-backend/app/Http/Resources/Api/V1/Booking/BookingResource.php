<?php

namespace App\Http\Resources\Api\V1\Booking;

use App\Http\Resources\Api\V1\Address\AddressResource;
use App\Http\Resources\Api\V1\Media\MediaResource;
use App\Http\Resources\Api\V1\Worker\WorkerResource;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {  
        $this->load(['service.rules', 'coupon']); // تأكد من تحميل قواعد الخدمة والكوبون لتوفير المعلومات اللازمة في الـ UI
        $lang = app()->getLocale();
        $serviceName = $lang == 'ar' ? $this->service->name_ar : $this->service->name_en;

        // حساب المدة لتطابق التصميم (مثال: 3 Hours)
        $hours = floor($this->duration_minutes / 60);
        $durationText = $hours > 0 ? "{$hours} Hours" : "{$this->duration_minutes} Mins";

        // جلب صورة الخدمة (الأيقونة أو الغلاف حسب المتوفر)
        $media = $this->service->media->where('collection', 'service/icon')->first();

        return [
            'id' => $this->id,
            'booking_ref' => $this->booking_ref,
            'status' => $this->status, // Upcoming, inService, completed, cancelled
            
            // بيانات الخدمة الأساسية
            'service_name' => $serviceName,
            'image' => $media ? (new MediaResource($media))->getFileUrl() : null,
            'price' => (float) $this->total_amount, // السعر الصافي بعد الخصم
            'currency' => 'EGP',
            'duration' => $durationText,

            
            // 🌟 توقيتات وتواريخ مجهزة للـ UI 🌟
            'date_day' => Carbon::parse($this->booking_date)->format('d'), 
            'date_month' => Carbon::parse($this->booking_date)->format('M'), 
            
            'full_date' => Carbon::parse($this->booking_date)->format('d M, Y'), 
            'time_slot' => Carbon::parse($this->start_time)->format('h:i A') . ' - ' . Carbon::parse($this->end_time)->format('h:i A'),
            
            // 🌟 العلاقات 🌟
            'address' => new AddressResource($this->whenLoaded('address')),
            'workers' => WorkerResource::collection($this->whenLoaded('workers')),
            
            // 🌟 تحديث المصفوفة المالية لتشمل الكوبون والخصم بالتفصيل 🌟
            'price_summary' => [
                'subtotal' => (float) $this->price_subtotal, // السعر الأساسي قبل الخصم
                'tax' => (float) $this->tax_amount,
                'has_coupon' => !is_null($this->coupon_id), // يخبر الفرونت إند بوجود كوبون لتعديل الـ UI
                'coupon_id' => $this->coupon_id, // ID الكوبون المطبق (إن وجد)
                'coupon_code' => $this->relationLoaded('coupon') && $this->coupon ? $this->coupon->code : null, // كود الكوبون المطبق
                'discount' => (float) $this->discount_amount, // قيمة الخصم المقتطعة
                'total' => (float) $this->total_amount, // السعر النهائي والأساسي الجديد الي صار بعد الخصم
            ],

            // الملاحظات الهامة بناءً على قواعد الخدمة
            'important_notes' => $this->whenLoaded('service', function () use ($lang) {
                $rules = $this->service->rules;
                if (!$rules) return [];
                
                return [
                    $lang == 'ar' ? 'يمكن إعادة الجدولة قبل ' . $rules->reschedule_cutoff_hours . ' ساعات' : 'Reschedule Can Be Before ' . $rules->reschedule_cutoff_hours . ' Hours',
                    $lang == 'ar' ? 'الإلغاء المجاني متاح قبل ' . $rules->free_cancel_cutoff_hours . ' ساعات' : 'Free Cancellation Before ' . $rules->free_cancel_cutoff_hours . ' Hours',
                ];
            }),
        ];
    }
}