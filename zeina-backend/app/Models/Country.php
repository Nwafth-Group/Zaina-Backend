<?php

namespace App\Models;

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
        'status',
        'image',
    ];

    public function cities(){
        return $this->hasMany(City::class, 'country_id');
    }
    
}
