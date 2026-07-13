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

    public function reviewable()
    {
        return $this->morphTo();
    }
}
