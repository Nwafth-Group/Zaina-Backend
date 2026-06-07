<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Worker extends Model
{
    /** @use HasFactory<\Database\Factories\WorkerFactory> */
    use HasFactory; 

    protected $fillable = [
        'name',
        'nationality',
        'avg_rating',
        'status',
    ];

    public function services()
    {
        return $this->belongsToMany(Service::class,'service_workers');
    } 

    public function assignments()
{
    // العامل لديه العديد من التخصيصات في الجدول الوسيط
    return $this->hasMany(BookingAssignment::class, 'worker_id');
}

   public function bookings()
    {
        return $this->belongsToMany(Booking::class, 'booking_assignments', 'worker_id', 'booking_id')
                    ->wherePivot('status', 'active') // فقط الحجزات النشطة
                    ->withTimestamps();
    }
    public function media()
    {
        return $this->morphMany(Media::class,'model');
    }

}
