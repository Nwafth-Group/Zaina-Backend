<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingVisit extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'visit_date',
        'start_time',
        'end_time',
        'status',
        'cancelled_at',
        'cancellation_reason',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
