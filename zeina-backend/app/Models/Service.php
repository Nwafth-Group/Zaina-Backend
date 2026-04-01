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
        'description_ar', 'description_en', 'status' , 'avg_rating'
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

    public function startingPrice()
    {
        $now = now()->toDateString();

        return $this->hasOne(ServicePrice::class, 'service_id')
            // التأكد إن التسعيرة فعالة اليوم (مش منتهية الصلاحية ومش لسا ما بدأت)
            ->where(function ($query) use ($now) {
                $query->whereNull('effective_start')->orWhere('effective_start', '<=', $now);
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('effective_end')->orWhere('effective_end', '>=', $now);
            })
            ->orderBy('base_price', 'asc'); 
    }


   public function media(){
    return $this->morphMany(Media::class,'model');
   }
}
