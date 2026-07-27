<?php

namespace App\Http\Resources\Api\V1\Address;

use App\Http\Resources\Api\V1\ServiceZone\ServiceZoneResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AddressResource extends JsonResource
{
    public function toArray(Request $request): array
    {  
        return [
            'id' => $this->id,
            'service_zone_id' => $this->service_zone_id,
            'service_zone' => $this->serviceZone ? new ServiceZoneResource($this->serviceZone) : null,
            'service_zone_name' => $this->serviceZone?->name,
            'latitude' => (float)$this->latitude,
            'longitude' => (float)$this->longitude,
            'formatted_address' => $this->formatted_address ?? $this->street,
            'city' => $this->serviceZone?->city ?? $this->city,
            'governorate' => $this->serviceZone?->governorate ?? $this->governorate,
            'country' => $this->country,
            'label' => $this->label,
            'street' => $this->street,
            'building_name' => $this->building_name,
            'building_no' => $this->building_no,
            'floor' => $this->floor,
            'floor_no' => $this->floor,
            'apartment_no' => $this->apartment_no,
            'contact_name' => $this->contact_name,
            'phone_number' => $this->phone_number,
            'secondary_phone' => $this->secondary_phone,
            'notes' => $this->notes,
            'is_default' => (bool)$this->is_default,
            'is_serviceable' => (bool)($this->service_zone_id || $this->serviceable_status === 'serviceable'),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
