<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    /** @use HasFactory<\Database\Factories\CityFactory> */
    use HasFactory;

    protected $fillable = [
        'govrenrate_id',
        'name_ar',
        'name_en',
        'code',
        'status',
    ];

    public function govrenrate()
    {
        return $this->belongsTo(Govrenrate::class, 'govrenrate_id');
    }

    public function areas()
    {
        return $this->hasMany(Area::class, 'city_id');
    }

    /**
     * Resolve the city's country through its governorate.
     *
     * Cities have no direct country_id (the chain is city -> govrenrate ->
     * country), so this accessor lets `$this->country` resolve everywhere
     * CityResource is rendered. Eager-load `govrenrate.country` to avoid N+1.
     */
    public function getCountryAttribute(): ?Country
    {
        return $this->govrenrate?->country;
    }
}
