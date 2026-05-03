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
    public function govrenrate(){
        return $this->belongsTo(Govrenrate::class, 'govrenrate_id');
    }
    public function areas(){
        return $this->hasMany(Area::class, 'city_id');
    } 




}
