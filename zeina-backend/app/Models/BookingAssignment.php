<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingAssignment extends Model
{
   protected $fillable = [
        'booking_id', 'worker_id', 'status', 'assigned_at'
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    
    // الموظف (الـ Operations) الذي قام بهذا التعيين
    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
