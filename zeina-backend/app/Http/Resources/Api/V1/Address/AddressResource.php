<?php

namespace App\Http\Resources\Api\V1\Address;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AddressResource extends JsonResource
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
            'area' => [
                'id' => $this->area->id,
                'name' => $this->area->name_en,
                'city' => [
                    'id' => $this->area->city->id,
                    'name' => $this->area->city->name_en,
                ],
            ],
            'label' => $this->label,
            'street' => $this->street,
            'building' => $this->building,
            'floor' => $this->floor,
            'unit' => $this->unit,
            'notes' => $this->notes,
            'is_default' => $this->is_default,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
             'serviceable_status' => $this->serviceable_status,
             'created_at' => $this->created_at->toDateTimeString(),
             'updated_at' => $this->updated_at->toDateTimeString(),
        ];
    }
}
