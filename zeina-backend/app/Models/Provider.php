<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Provider extends Model
{
    /** @use HasFactory<\Database\Factories\ProviderFactory> */
    use HasFactory;

    protected $fillable = [
        'type', 'first_name', 'last_name', 'gender', 
        'phone_number', 'document_id', 'status', 
        'rating_average', 'total_jobs_completed'
    ];

    public function services()
    {
        return $this->belongsToMany(Service::class, 'provider_service',);
    }

    // المناطق التي يعمل بها
    public function areas()
    {
        return $this->belongsToMany(Area::class, 'area_provider');
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
