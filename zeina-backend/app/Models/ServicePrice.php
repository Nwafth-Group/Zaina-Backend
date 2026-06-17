<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServicePrice extends Model
{
    /** @use HasFactory<\Database\Factories\ServicePriceFactory> */
    use HasFactory;

    protected $fillable = [
        'service_id', 'city_id', 'area_id',
        'price_per_service_period', 'currency',
        'effective_start', 'effective_end',
    ];

    protected $casts = [
        'effective_start' => 'date',
        'effective_end' => 'date',
        'price_per_service_period' => 'decimal:2',
    ];

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function area()
    {
        return $this->belongsTo(Area::class);
    }
}
