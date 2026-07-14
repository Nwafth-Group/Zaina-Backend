<?php

namespace App\Http\Resources\Api\V1\Reports;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportsResource extends JsonResource
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
            'booking_id' => $this->booking_id,
            'booking_ref' => $this->booking->booking_ref ?? null,
            'customer_id' => $this->customer_id,
            'reason' => $this->reason,
            'description' => $this->description,
            'status' => $this->status,
            'admin_notes' => $this->admin_notes ? array_values(array_filter(array_map('trim', explode("\n", $this->admin_notes)))) : [],
            'service_name' => app()->getLocale() === 'ar'
                ? ($this->service->name_ar ?? ($this->booking->service->name_ar ?? null))
                : ($this->service->name_en ?? ($this->booking->service->name_en ?? null)),
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
        ];
    }
}
