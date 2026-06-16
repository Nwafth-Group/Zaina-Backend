<?php

namespace App\Http\Resources\Api\V1\Service;

use App\Http\Resources\Api\V1\Area\AreaResource;
use App\Http\Resources\Api\V1\City\CityResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServicePriceResource extends JsonResource
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
            'city_id' => $this->city_id,
            'area_id' => $this->area_id, // null => city-wide price (scope is implicit)
            'city' => new CityResource($this->whenLoaded('city')),
            'area' => $this->whenLoaded('area', fn () => $this->area ? new AreaResource($this->area) : null),
            'price_per_service_period' => $this->price_per_service_period,
            'currency' => $this->currency,
            'effective_start' => $this->effective_start ? $this->effective_start->toDateString() : null,
            'effective_end' => $this->effective_end ? $this->effective_end->toDateString() : null,
        ];
    }
}
