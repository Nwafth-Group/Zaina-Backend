<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceChildAgeGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_id', 'name_ar', 'name_en', 'age_from', 'age_to',
        'calculation_type', 'children_count', 'required_worker_count', 'status'
    ];

    protected $casts = [
        'age_from' => 'integer',
        'age_to' => 'integer',
        'children_count' => 'integer',
        'required_worker_count' => 'integer',
    ];

    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id');
    }
}
