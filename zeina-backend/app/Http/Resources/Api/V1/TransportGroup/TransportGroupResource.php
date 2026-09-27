<?php

namespace App\Http\Resources\Api\V1\TransportGroup;

use App\Http\Resources\Api\V1\DeparturePoint\DeparturePointResource;
use App\Http\Resources\Api\V1\Driver\DriverResource;
use App\Http\Resources\Api\V1\ServiceZone\ServiceZoneResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransportGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'service_date' => $this->service_date,
            'governorate' => $this->governorate,
            'area_name' => $this->area_name,
            'start_time' => $this->start_time,
            'departure_time' => $this->departure_time,
            'notes' => $this->notes,
            'status' => $this->status,
            'service_zone' => new ServiceZoneResource($this->whenLoaded('serviceZone')),
            'departure_point' => new DeparturePointResource($this->whenLoaded('departurePoint')),
            'driver' => new DriverResource($this->whenLoaded('driver')),
            'vehicle_type' => $this->vehicle_type ?? $this->driver?->vehicle_type,
            'vehicle_plate' => $this->vehicle_plate ?? $this->driver?->vehicle_plate,
            'vehicle_capacity' => (int) ($this->vehicle_capacity ?? $this->driver?->vehicle_capacity ?? 5),
            'bookings_count' => $this->bookings_count ?? $this->bookings->count(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
