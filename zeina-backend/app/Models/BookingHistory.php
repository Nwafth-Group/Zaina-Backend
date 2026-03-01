<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingHistory extends Model
{
    protected $fillable = [
        'booking_id', 'old_status', 'new_status', 'note', 'created_by'
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    // الشخص الذي غير الحالة (موظف، أو العميل نفسه لو ألغى الحجز)
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
