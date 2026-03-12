<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServicePrice extends Model
{
    /** @use HasFactory<\Database\Factories\ServicePriceFactory> */
    use HasFactory;

    protected $fillable = [
        'service_id', 'pricing_scope', 'city_id', 'area_id', 
        'base_price', 'price_per_hour', 'currency', 
        'effective_start', 'effective_end'
    ];

    protected $casts = [
        'effective_start' => 'date',
        'effective_end' => 'date',
        'base_price' => 'decimal:2',
        'price_per_hour' => 'decimal:2',
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
