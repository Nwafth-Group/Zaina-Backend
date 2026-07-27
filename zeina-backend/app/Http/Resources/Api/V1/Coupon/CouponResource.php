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
            'name' => $this->name,
            'code' => $this->code,
            'type' => $this->type, // fixed, percentage
            'value' => (float) $this->value,
            'usage_limit' => $this->usage_limit,
            'user_usage_limit' => $this->user_usage_limit,
            'used_count' => $this->used_count,
            'start_date' => $this->start_date ? $this->start_date->toDateTimeString() : null,
            'end_date' => $this->end_date ? $this->end_date->toDateTimeString() : null,
            'is_expired' => $this->end_date ? $this->end_date->isPast() : false,
            'status' => $this->status,
            'is_used' => $this->is_used ?? false,
            'is_available' => $this->is_available ?? false,
            'user_usage_count' => $this->user_usage_count ?? 0,

            // Count summaries for admin table
            'users_count' => $this->users_count ?? ($this->relationLoaded('users') ? $this->users->count() : 0),
            'cities_count' => $this->service_zones_count ?? ($this->relationLoaded('serviceZones') ? $this->serviceZones->count() : 0),
            'service_zones_count' => $this->service_zones_count ?? ($this->relationLoaded('serviceZones') ? $this->serviceZones->count() : 0),
            'service_zones' => $this->when($this->relationLoaded('serviceZones'), function () {
                return $this->serviceZones->map(function ($z) {
                    return [
                        'id' => $z->id,
                        'name' => $z->name,
                        'city' => $z->city,
                    ];
                });
            }),

            // Completed Booking count
            'completed_bookings_count' => $this->completed_bookings_count,
            'min_workers' => $this->min_workers ? (int) $this->min_workers : null,

            // Services
            'is_for_all_services' => (bool) $this->is_for_all_services,
            'allowed_visit_types' => $this->allowed_visit_types,

            'services' => $this->when($this->relationLoaded('services'), function () {
                return $this->services->map(function ($service) {
                    return [
                        'id' => $service->id,
                        'name' => app()->getLocale() === 'ar' ? $service->name_ar : $service->name_en,
                        'pivot' => [
                            'allowed_visit_types' => isset($service->pivot->allowed_visit_types)
                                ? (is_string($service->pivot->allowed_visit_types)
                                    ? json_decode($service->pivot->allowed_visit_types, true)
                                    : $service->pivot->allowed_visit_types)
                                : null,
                            'min_workers' => isset($service->pivot->min_workers) ? (int) $service->pivot->min_workers : null,
                        ],
                    ];
                });
            }),

            // Backward-compatible field for V1 Customer API
            'eligible_services' => $this->when($this->relationLoaded('services'), function () {
                return $this->is_for_all_services ? [] : $this->services->map(function ($service) {
                    return [
                        'id' => $service->id,
                        'name' => app()->getLocale() === 'ar' ? $service->name_ar : $service->name_en,
                    ];
                });
            }),
        ];
    }
}
