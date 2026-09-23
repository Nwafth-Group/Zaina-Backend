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
        'scheduled_at', 'duration_minutes', 'number_of_workers', 'worker_gender',
        'number_of_children', 'number_of_persons', 'child_age_groups', 'selected_options',
        'price_subtotal', 'tax_amount', 'discount_amount', 'total_amount', 'coupon_id',
        'status', 'payment_status', 'customer_notes', 'start_time', 'end_time', 'booking_date', 'worker_id',
        'visit_type', 'visit_number', 'payment_transaction_id', 'currency_code', 'contract_id',
        'payment_method', 'paid_points', 'refund_amount', 'refund_points', 'refund_destination',
        'refund_status', 'cancelled_by', 'cancelled_at', 'cancellation_reason'
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'child_age_groups' => 'array',
        'selected_options' => 'array',
    ];

    protected static function boot()
    {
        return parent::boot();
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function visits()
    {
        return $this->hasMany(BookingVisit::class);
    }

    public function workers()
    {
        return $this->belongsToMany(Worker::class, 'booking_assignments', 'booking_id', 'worker_id')
                    ->wherePivot('status', 'active')
                    ->withTimestamps();
    }

    public function assignedWorkers()
    {
        return $this->workers();
    }

    public function address()
    {
        return $this->belongsTo(CustomerAddress::class, 'address_id');
    }

    public function provider()
    {
        return $this->belongsTo(Provider::class);
    }

    public function assignments()
    {
        return $this->hasMany(BookingAssignment::class);
    }

    public function histories()
    {
        return $this->hasMany(BookingHistory::class);
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }
}
