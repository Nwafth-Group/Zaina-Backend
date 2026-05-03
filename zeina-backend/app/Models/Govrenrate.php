<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Govrenrate extends Model
{
    protected $fillable = [
        'country_id',
        'name_ar',
        'name_en',
        'status',
    ];

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function cities()
    {
        return $this->hasMany(City::class);
    }
}
