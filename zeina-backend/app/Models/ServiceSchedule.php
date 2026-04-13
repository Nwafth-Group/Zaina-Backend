<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceSchedule extends Model
{
    /** @use HasFactory<\Database\Factories\ServiceScheduleFactory> */
    use HasFactory;
    protected $fillable = [
        'service_id', 'day', 'start_time', 'end_time', 'is_active' , 'schedule_date'
    ];

    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id');
    }
}
