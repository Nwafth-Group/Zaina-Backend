<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    /** @use HasFactory<\Database\Factories\BookingFactory> */
    use HasFactory;

    protected $fillable = [
        'booking_ref', 'customer_id', 'service_id', 'address_id',
        'scheduled_at', 'duration_minutes', 'number_of_workers', 'worker_gender',
        'number_of_children', 'number_of_persons', 'child_age_groups', 'selected_options',
        'price_subtotal', 'tax_amount', 'discount_amount', 'total_amount', 'coupon_id',
        'status', 'payment_status', 'customer_notes', 'start_time', 'end_time', 'booking_date', 'worker_id', 'team_id',
        'actual_start_time', 'actual_end_time', 'started_by', 'completed_by', 'is_at_risk', 'at_risk_reason',
        'visit_type', 'visit_number', 'payment_transaction_id', 'currency_code', 'contract_id',
        'payment_method', 'paid_points', 'refund_amount', 'refund_points', 'refund_destination',
        'refund_status', 'cancelled_by', 'cancelled_at', 'cancellation_reason',
        'customer_reschedule_count', 'rescheduled_at'
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'rescheduled_at' => 'datetime',
        'customer_reschedule_count' => 'integer',
        'is_at_risk' => 'boolean',
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

    public function worker()
    {
        return $this->belongsTo(Worker::class, 'worker_id');
    }

    public function assignedWorkers()
    {
        return $this->workers();
    }

    public function address()
    {
        return $this->belongsTo(CustomerAddress::class, 'address_id');
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

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function transportGroups()
    {
        return $this->belongsToMany(TransportGroup::class, 'transport_group_bookings', 'booking_id', 'transport_group_id')
                    ->withPivot('dropoff_sequence')
                    ->withTimestamps();
    }

    public function starter()
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function completer()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function scopeForDate($query, string $date)
    {
        return $query->where(function ($q) use ($date) {
            $q->whereHas('visits', function ($vq) use ($date) {
                $vq->whereDate('visit_date', $date);
            })->orWhere(function ($sub) use ($date) {
                $sub->doesntHave('visits')
                    ->whereDate('booking_date', $date);
            });
        });
    }

    public function scopeForDateBetween($query, string $startDate, string $endDate)
    {
        return $query->where(function ($q) use ($startDate, $endDate) {
            $q->whereHas('visits', function ($vq) use ($startDate, $endDate) {
                $vq->whereBetween('visit_date', [$startDate, $endDate]);
            })->orWhere(function ($sub) use ($startDate, $endDate) {
                $sub->doesntHave('visits')
                    ->whereBetween('booking_date', [$startDate, $endDate]);
            });
        });
    }
}

