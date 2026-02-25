<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerAddress extends Model
{
    /** @use HasFactory<\Database\Factories\CustomerAddressFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'area_id',
        'label',
        'street',
        'building',
        'floor',
        'unit',
        'notes',
        'is_default',
        'is_active',
    ];

    public function user(){
        return $this->belongsTo(User::class, 'user_id');
    }

    public function area(){
        return $this->belongsTo(Area::class, 'area_id');
    }

    public function getCityAttribute() {
        return $this->area->city;
    }

    public function city()
{
    return $this->hasOneThrough(City::class, Area::class, 'id', 'id', 'area_id', 'city_id');
}
}
