<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransportGroupWorker extends Model
{
    use HasFactory;

    protected $fillable = [
        'transport_group_id',
        'booking_id',
        'worker_id',
        'dropoff_sequence',
        'departure_point_id',
        'departure_time',
        'status',
        'notes',
    ];

    public function departurePoint()
    {
        return $this->belongsTo(DeparturePoint::class, 'departure_point_id');
    }

    public function transportGroup()
    {
        return $this->belongsTo(TransportGroup::class, 'transport_group_id');
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    public function worker()
    {
        return $this->belongsTo(Worker::class, 'worker_id');
    }
}
