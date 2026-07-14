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
            'service_name' => app()->getLocale() === 'ar'
                ? ($this->service->name_ar ?? ($this->booking->service->name_ar ?? null))
                : ($this->service->name_en ?? ($this->booking->service->name_en ?? null)),
            'history' => $this->histories ? $this->histories->map(function ($history) {
                return [
                    'id' => $history->id,
                    'old_status' => $history->old_status,
                    'new_status' => $history->new_status,
                    'note' => $history->note,
                    'created_at' => $history->created_at->toDateTimeString(),
                ];
            }) : [],
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
        ];
    }
}
