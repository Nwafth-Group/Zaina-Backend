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
        'description_ar', 'description_en', 'status' , 'avg_rating' , 'features'
    ];

    protected $casts = [
        'features' => 'array',
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
    
    public function schedules()
    {
        return $this->hasMany(ServiceSchedule::class, 'service_id');
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
        ->orderBy('price_per_service_period', 'asc'); // 🌟 التعديل هنا: الترتيب حسب سعر الفترة تصاعدياً
}

public function getCustomerPrice()
{
    $user = auth('sanctum')->user(); 
    
    if ($user) {
        $defaultAddress = $user->addresses()->where('is_default', true)->with('area')->first();
       
        if ($defaultAddress && $defaultAddress->area) {
            $areaId = $defaultAddress->area_id;
            $cityId = $defaultAddress->area->city_id; 
            $now = now()->toDateString();

            $specificPrice = $this->priceRules()
                ->where(function ($query) use ($now) {
                    $query->whereNull('effective_start')->orWhere('effective_start', '<=', $now);
                })
                ->where(function ($query) use ($now) {
                    $query->whereNull('effective_end')->orWhere('effective_end', '>=', $now);
                })
                ->where(function ($query) use ($cityId, $areaId) {
                    $query->where(function ($q) use ($areaId){
                        $q->where('pricing_scope', 'area')->where('area_id', $areaId);
                    })
                    ->orWhere(function ($q) use ($cityId) {
                        $q->where('pricing_scope', 'city')->where('city_id', $cityId)->whereNull('area_id');
                    });
                })
                ->orderByRaw('area_id IS NULL ASC')
                ->first();

            if ($specificPrice) {
                // نخبر الأوبجيكت إنه سعر مخصص بناءً على منطقة العميل (سعر نهائي ثابث)
                $specificPrice->is_fallback = false; 
                return $specificPrice;
            }
        }
    }

    // إذا لم يسجل دخول أو لم يملك عنواناً افتراضياً، نرجع السعر الافتراضي العام للخدمة
    $fallbackPrice = $this->startingPrice;
    
    if ($fallbackPrice) {
         // نخبر الأوبجيكت إنه مجرد خطة بديلة (فيُعرض في التطبيق بكلمة "يبدأ من")
         $fallbackPrice->is_fallback = true;
    }

    return $fallbackPrice; 
}

   public function media(){
    return $this->morphMany(Media::class,'model');
   }

   public function workers(){
    return $this->belongsToMany(Worker::class, 'service_workers');
   }

   public function coupons(){
    return $this->belongsToMany(Coupon::class, 'coupon_services');
   }

   
}
