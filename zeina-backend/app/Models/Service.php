<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    /** @use HasFactory<\Database\Factories\ServiceFactory> */
    use HasFactory;

   protected $fillable = [
        'category_id', 'type', 'name_ar', 'name_en', 
        'description_ar', 'description_en', 'status'
    ];

    public function category(){
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function duration(){
        return $this->hasOne(ServiceDuration::class,'service_id');
    }
    public function rules(){
        return $this->hasOne(ServiceRule::class , 'service_id');
    }

    public function coverages()
    {
        return $this->hasMany(ServiceCoverage::class,'service_id');
    }

    public function priceRules()
    {
        return $this->hasMany(ServicePrice::class , 'service_id');
    }


   public function media(){
    return $this->morphMany(Media::class,'model');
   }
}
