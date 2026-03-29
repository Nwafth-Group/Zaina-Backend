<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    /** @use HasFactory<\Database\Factories\CountryFactory> */
    use HasFactory;

    protected $fillable = [
        'name_ar',
        'name_en',
        'code',
        'currency_code',
        'phone_code',
        'flag',
        'status',
    ];

    protected function flag(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? asset('storage/' . $value) : null,
        );
    }

    public function cities(){
        return $this->hasMany(City::class, 'country_id');
    }
    
}
