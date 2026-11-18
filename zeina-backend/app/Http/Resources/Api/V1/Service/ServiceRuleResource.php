<?php

namespace App\Http\Resources\Api\V1\Service;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceRuleResource extends JsonResource
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
            'free_cancel_cutoff_hours' => $this->free_cancel_cutoff_hours,
            'reschedule_cutoff_hours' => $this->reschedule_cutoff_hours,

            // Config Rules
            'allow_cancel' => (bool) $this->allow_cancel, //
            'allow_reschedule' => (bool) $this->allow_reschedule, //
            'allow_same_day' => (bool) $this->allow_same_day, // 
            'same_day_lead_time_hours' => (int) ($this->same_day_lead_time_hours ?? 3),
            'same_day_cutoff_time' => $this->same_day_cutoff_time ?? '10:00:00',
            'max_advance_booking_days' => $this->max_advance_booking_days, // 
            'arrival_buffer_minutes' => (int) $this->arrival_buffer_minutes,
            'late_arrival_grace_minutes' => (int) $this->late_arrival_grace_minutes,
        ];
    }
}
