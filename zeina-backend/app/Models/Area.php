<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Area extends Model
{
    /** @use HasFactory<\Database\Factories\AreaFactory> */
    use HasFactory;

    protected $fillable = [
        'city_id',
        'name_ar',
        'name_en',
        'is_active',
    ]; 

    public function city(){
        return $this->belongsTo(City::class, 'city_id');
    } 

    public function addresses(){
        return $this->hasMany(CustomerAddress::class, 'area_id');
    }

}
