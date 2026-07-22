<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class RewardTasks extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'title_ar', 'title_en', 'subtitle_ar', 'subtitle_en', 'points',
        'action_text_ar', 'action_text_en', 'action_type', 'action_link', 'status',
        'rule_category', 'service_id', 'required_booking_count', 'frequency_type',
        'eligible_services', 'calculation_method', 'points_expiration_months',
    ];

    protected $casts = [
        'eligible_services' => 'array',
        'status' => 'boolean',
    ];

    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id');
    }
}
