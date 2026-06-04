<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    /** @use HasFactory<\Database\Factories\CouponFactory> */
    use HasFactory;


    protected $fillable = [
        'code', 'type', 'value', 'max_discount', 'min_booking_amount',
        'usage_limit', 'user_usage_limit', 'used_count',
        'start_date', 'end_date', 'is_for_all_users', 'is_for_all_services', 'status','coupon_id'
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'is_for_all_users' => 'boolean',
        'is_for_all_services' => 'boolean',
    ];

    public function services(){
        return $this->belongsToMany(Service::class, 'coupon_services');
    }

    public function users(){
        return $this->belongsToMany(User::class, 'coupon_users');
    }

}
