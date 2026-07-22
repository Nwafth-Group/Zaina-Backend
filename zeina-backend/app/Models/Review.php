<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    /** @use HasFactory<\Database\Factories\ReviewFactory> */
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'customer_id',
        'reviewable_type',
        'reviewable_id',
        'rating',
        'rating_percentage',
        'comment',
        'status',
        'action_by',
        'action_at',
    ];

    protected $casts = [
        'action_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::saving(function ($review) {
            $review->rating_percentage = ($review->rating / 5) * 100;
        });
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function actionBy()
    {
        return $this->belongsTo(User::class, 'action_by');
    }

    public function reviewable()
    {
        return $this->morphTo();
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }
}
