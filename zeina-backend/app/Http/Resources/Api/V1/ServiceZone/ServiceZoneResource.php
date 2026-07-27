<?php

namespace App\Http\Resources\Api\V1\ServiceZone;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceZoneResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'governorate' => $this->governorate,
            'city' => $this->city,
            'polygon_coordinates' => $this->polygon_coordinates,
            'status' => $this->status,
            'services' => $this->whenLoaded('services', function () {
                return $this->services->map(function ($service) {
                    return [
                        'id' => $service->id,
                        'name_ar' => $service->name_ar,
                        'name_en' => $service->name_en,
                    ];
                });
            }),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
