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
            'free_cancel_cutoff_hours' => $this->free_cancel_cutoff_hours, //
            'reschedule_cutoff_hours' => $this->reschedule_cutoff_hours, //
            // 'lead_time_min_hours' => $this->lead_time_min_hours,
            // 'lead_time_max_days' => $this->lead_time_max_days,
            // 'late_cancel_action' => $this->late_cancel_action,
            // 'late_cancel_fee_type' => $this->late_cancel_fee_type,
            // 'late_cancel_fee_value' => $this->late_cancel_fee_value,
            // 'no_show_policy' => $this->no_show_policy,
            // 'max_changes_per_booking' => $this->max_changes_per_booking,
            
            // New Config Rules
            'allow_cancel' => (bool) $this->allow_cancel, //
            'allow_reschedule' => (bool) $this->allow_reschedule, //
            'allow_same_day' => (bool) $this->allow_same_day, // 
            'max_advance_booking_days' => $this->max_advance_booking_days, // 
            'arrival_buffer_minutes' => (int) $this->arrival_buffer_minutes,
            'late_arrival_grace_minutes' => (int) $this->late_arrival_grace_minutes,
        ];
    }
}
