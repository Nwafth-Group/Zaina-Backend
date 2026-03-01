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
        'max_changes_per_booking'
    ];

    public function service()
    {
        return $this->belongsTo(Service::class,'service_id');
    }
}
