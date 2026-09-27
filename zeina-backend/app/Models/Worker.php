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
        'phone_number',
        'id_number',
        'address',
        'gender',
        'team_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'avg_rating' => 'float',
        ];
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function services()
    {
        return $this->belongsToMany(Service::class, 'service_workers');
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
        return $this->morphMany(Media::class, 'model');
    }

    public function reviews()
    {
        return $this->morphMany(Review::class, 'reviewable');
    }

    public function absences()
    {
        return $this->hasMany(WorkerAbsence::class);
    }
}
