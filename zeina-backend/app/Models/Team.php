<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'name_ar',
        'name_en',
        'code',
        'status',
        'notes',
    ];

    public function workers()
    {
        return $this->hasMany(Worker::class);
    }

    public function members()
    {
        return $this->workers();
    }

    public function services()
    {
        return $this->belongsToMany(Service::class, 'service_teams')->withTimestamps();
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}
