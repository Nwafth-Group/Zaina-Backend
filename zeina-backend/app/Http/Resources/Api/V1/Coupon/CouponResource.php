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

            // Count summaries for admin table
            'users_count' => $this->users_count ?? ($this->relationLoaded('users') ? $this->users->count() : 0),
            'cities_count' => $this->cities_count ?? ($this->relationLoaded('cities') ? $this->cities->count() : 0),
            'areas_count' => $this->areas_count ?? ($this->relationLoaded('areas') ? $this->areas->count() : 0),
            'services_count' => $this->services_count ?? ($this->relationLoaded('services') ? $this->services->count() : 0),

            // Audience
            'is_for_all_users' => (bool) $this->is_for_all_users,
            'users' => $this->when($this->relationLoaded('users'), function () {
                return $this->users->map(function ($u) {
                    return [
                        'id' => $u->id,
                        'name' => $u->first_name.' '.$u->last_name,
                        'email' => $u->email,
                    ];
                });
            }),

            // Locations
            'is_for_all_cities' => (bool) $this->is_for_all_cities,
            'cities' => $this->when($this->relationLoaded('cities'), function () {
                return $this->cities->map(function ($c) {
                    return [
                        'id' => $c->id,
                        'name' => app()->getLocale() === 'ar' ? $c->name_ar : $c->name_en,
                    ];
                });
            }),
            'areas' => $this->when($this->relationLoaded('areas'), function () {
                return $this->areas->map(function ($a) {
                    return [
                        'id' => $a->id,
                        'name' => app()->getLocale() === 'ar' ? $a->name_ar : $a->name_en,
                        'city_id' => $a->city_id,
                    ];
                });
            }),

            // Completed Booking count
            'completed_bookings_count' => $this->completed_bookings_count,

            // Services
            'is_for_all_services' => (bool) $this->is_for_all_services,
            'allowed_visit_types' => $this->allowed_visit_types,
            'allowed_shifts' => $this->allowed_shifts,

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
                            'allowed_shifts' => isset($service->pivot->allowed_shifts)
                                ? (is_string($service->pivot->allowed_shifts)
                                    ? json_decode($service->pivot->allowed_shifts, true)
                                    : $service->pivot->allowed_shifts)
                                : null,
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
