<?php

namespace App\Http\Resources\Api\V1\DeparturePoint;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeparturePointResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'address' => $this->address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
