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
            'pricing_scope' => $this->pricing_scope,
            'city' =>new CityResource($this->whenLoaded('city')),
            'area' => new AreaResource($this->whenLoaded('area')),
            'base_price' => $this->base_price,
            'price_per_hour' => $this->price_per_hour,
            'currency' => $this->currency,
            'effective_start' => $this->effective_start ? $this->effective_start->toDateString() : null,
            'effective_end' => $this->effective_end ? $this->effective_end->toDateString() : null,
        ];
    }
}
