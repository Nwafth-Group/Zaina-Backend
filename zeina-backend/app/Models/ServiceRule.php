<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceRule extends Model
{
    /** @use HasFactory<\Database\Factories\ServiceRuleFactory> */
    use HasFactory;

    protected $fillable = [
        'service_id', 'free_cancel_cutoff_hours', 'reschedule_cutoff_hours',
        'lead_time_min_hours', 'lead_time_max_days', 'late_cancel_action',
        'late_cancel_fee_type', 'late_cancel_fee_value', 'no_show_policy',
        'max_changes_per_booking', 'allow_cancel', 'allow_reschedule',
        'allow_same_day', 'max_advance_booking_days',
        'arrival_buffer_minutes', 'late_arrival_grace_minutes'
    ];

    protected $casts = [
        'allow_cancel' => 'boolean',
        'allow_reschedule' => 'boolean',
        'allow_same_day' => 'boolean',
        'arrival_buffer_minutes' => 'integer',
        'late_arrival_grace_minutes' => 'integer',
    ];

    public function service()
    {
        return $this->belongsTo(Service::class,'service_id');
    }
}
