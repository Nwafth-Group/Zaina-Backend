<?php

namespace App\Http\Resources\Api\V1\Service;

use App\Http\Resources\Api\V1\Area\AreaResource;
use App\Http\Resources\Api\V1\City\CityResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceCoverageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return 
        [
           'id' => $this->id,
           'city' => new CityResource($this->whenLoaded('city')),
           'area' => new AreaResource($this->whenLoaded('area')),
           'status' => $this->status, 
        ];
    }
}
