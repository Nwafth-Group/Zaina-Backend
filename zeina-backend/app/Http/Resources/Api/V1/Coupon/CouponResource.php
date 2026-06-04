<?php

namespace App\Http\Resources\Api\V1\Coupon;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CouponResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'type' => $this->type, // fixed, percentage
            'value' => (float) $this->value,
            'max_discount' => $this->max_discount ? (float) $this->max_discount : null,
            'min_booking_amount' => (float) $this->min_booking_amount,
            'is_for_all_services' => $this->is_for_all_services,
            
            // نرجع أسماء الخدمات المشمولة فقط إذا كان الكوبون مخصصاً لخدمات معينة ليعرف الكستمر أين يستخدمه
            'eligible_services' => $this->is_for_all_services ? [] : $this->services->map(function($service) {
                return [
                    'id' => $service->id,
                    'name' => app()->getLocale() === 'ar' ? $service->name_ar : $service->name_en
                ];
            }),
            'start_date' => $this->start_date->toDateTimeString(),
            'end_date' => $this->end_date->toDateTimeString(),
            'is_expired' => $this->end_date->isPast(),
        ];
    }
}
