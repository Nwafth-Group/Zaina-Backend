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
        'actual_start_time',
        'actual_end_time',
        'status',
        'cancelled_at',
        'cancellation_reason',
        'started_by',
        'completed_by',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function starter()
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function completer()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
