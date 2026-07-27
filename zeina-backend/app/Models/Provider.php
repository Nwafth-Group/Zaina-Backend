<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Provider extends Model
{
    /** @use HasFactory<\Database\Factories\ProviderFactory> */
    use HasFactory;

    protected $fillable = [
    'type',
    'fullname',
    'gender',
    'phone_number',
    'phone_code',
    'code',
    'status',
    'internal_notes',
    'rating_average',
    'created_by',
    'updated_by',
    ];

    protected static function booted()
    {
        static::creating(function ($model) { $model->created_by = auth()->id(); });
        static::updating(function ($model) { $model->updated_by = auth()->id(); });
    }

    public function services()
    {
        return $this->belongsToMany(Service::class,'provider_services')
        ->withPivot('is_enabled', 'created_by')
        ->withTimestamps();
    }

    public function serviceZones()
    {
        return $this->belongsToMany(ServiceZone::class, 'provider_service_zones');
    }

    // الحجوزات المعين عليها حالياً
    public function bookings()
    {
        return $this->hasMany(Booking::class, 'provider_id');
    }

    // تاريخ تعييناته (حتى لو تم استبداله)
    public function assignments()
    {
        return $this->hasMany(BookingAssignment::class);
    }
}
