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
        $lang = app()->getLocale();
        $serviceName = $lang == 'ar' ? $this->service->name_ar : $this->service->name_en;

        // حساب المدة لتطابق التصميم (مثال: 3 Hours)
        $hours = floor($this->duration_minutes / 60);
        $durationText = $hours > 0 ? "{$hours} Hours" : "{$this->duration_minutes} Mins";

        // جلب صورة الخدمة (الأيقونة أو الغلاف حسب المتوفر)
        $media = $this->service->media->where('collection', 'service/icon')->first();
            //   ?? $this->service->media->where('collection', 'service/cover')->first();

        return [
            'id' => $this->id,
            'booking_ref' => $this->booking_ref,
            'status' => $this->status, // Upcoming, inService, completed, cancelled
            // بيانات الخدمة الأساسية
            'service_name' => $serviceName,
            'image' => $media ? (new MediaResource($media))->getFileUrl() : null,
            'price' => (float) $this->total_amount,
            'currency' => 'EGP',
            'duration' => $durationText,
            
            // 🌟 توقيتات وتواريخ مجهزة للـ UI 🌟
            // للمربع الزهري في شاشة الـ List
            'date_day' => Carbon::parse($this->booking_date)->format('d'), // "10"
            'date_month' => Carbon::parse($this->booking_date)->format('M'), // "Nov"
            
            // لشاشة التفاصيل (Booking Details)
            'full_date' => Carbon::parse($this->booking_date)->format('d M, Y'), // "20 NOV, 2026"
            'time_slot' => Carbon::parse($this->start_time)->format('h:i A') . ' - ' . Carbon::parse($this->end_time)->format('h:i A'),
            
            // 🌟 العلاقات (تحمل فقط إذا طلبناها في الـ Show) 🌟
            'address' => new AddressResource($this->whenLoaded('address')),
            'worker' => new WorkerResource($this->whenLoaded('worker')),
            'price_summary' => [
                'subtotal' => (float) $this->price_subtotal,
                'tax' => (float) $this->tax_amount,
                'discount' => (float) $this->discount_amount,
                'total' => (float) $this->total_amount,
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
