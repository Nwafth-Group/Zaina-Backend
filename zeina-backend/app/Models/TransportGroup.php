<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransportGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_date',
        'governorate',
        'service_zone_id',
        'area_name',
        'start_time',
        'departure_point_id',
        'departure_time',
        'driver_id',
        'vehicle_type',
        'vehicle_plate',
        'vehicle_capacity',
        'notes',
        'status',
    ];

    protected $casts = [
        'vehicle_capacity' => 'integer',
    ];

    public function serviceZone()
    {
        return $this->belongsTo(ServiceZone::class, 'service_zone_id');
    }

    public function departurePoint()
    {
        return $this->belongsTo(DeparturePoint::class, 'departure_point_id');
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    public function bookings()
    {
        return $this->belongsToMany(Booking::class, 'transport_group_bookings')
                    ->withPivot('dropoff_sequence')
                    ->withTimestamps();
    }

    public function groupWorkers()
    {
        return $this->hasMany(TransportGroupWorker::class, 'transport_group_id');
    }

    public function workers()
    {
        return $this->belongsToMany(Worker::class, 'transport_group_workers')
                    ->withPivot('booking_id', 'dropoff_sequence', 'status', 'notes')
                    ->withTimestamps();
    }
}
