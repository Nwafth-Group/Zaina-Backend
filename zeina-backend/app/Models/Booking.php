<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    /** @use HasFactory<\Database\Factories\BookingFactory> */
    use HasFactory;
  protected $fillable = [
        'booking_ref', 'customer_id', 'service_id', 'address_id', 'provider_id',
        'scheduled_at', 'duration_minutes', 'number_of_workers',
        'price_subtotal', 'tax_amount', 'discount_amount', 'total_amount',
        'status', 'payment_status', 'customer_notes'
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function address()
    {
        return $this->belongsTo(CustomerAddress::class, 'address_id');
    }

    public function provider()
    {
        return $this->belongsTo(Provider::class);
    }

    // تاريخ التعيينات (لنعرف إذا تغير العامل)
    public function assignments()
    {
        return $this->hasMany(BookingAssignment::class);
    }

    public function histories()
    {
        return $this->hasMany(BookingHistory::class);
    }

}
