<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Service extends Model implements Auditable
{
    /** @use HasFactory<\Database\Factories\ServiceFactory> */
    use HasFactory, \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'category_id', 'type', 'worker_gender_options', 'pricing_type', 'name_ar', 'name_en',
        'description_ar', 'description_en', 'status', 'avg_rating', 'features',
        'service_code', 'available_visit_types', 'discount_value', 'terms_ar',
        'terms_en', 'acceptance_required', 'start_time', 'end_time',
        'default_duration', 'duration_unit', 'has_duration_range',
        'min_duration', 'max_duration', 'min_workers', 'max_workers', 'base_price',
        'min_children', 'max_children',
    ];

    protected $casts = [
        'features' => 'array',
        'available_visit_types' => 'array',
        'worker_gender_options' => 'array',
        'acceptance_required' => 'boolean',
        'has_duration_range' => 'boolean',
        'discount_value' => 'decimal:2',
        'base_price' => 'decimal:2',
        'min_children' => 'integer',
        'max_children' => 'integer',
    ];

    protected static function booted()
    {
        static::creating(function ($service) {
            if (empty($service->service_code)) {
                $latest = static::orderBy('id', 'desc')->first();
                $nextId = $latest ? $latest->id + 1 : 1;
                $service->service_code = 'SVC-' . date('Y') . '-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
            }
        });

        static::updating(function ($service) {
            if ($service->isDirty('service_code')) {
                $service->service_code = $service->getOriginal('service_code');
            }
        });
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function duration()
    {
        return $this->hasOne(ServiceDuration::class, 'service_id');
    }

    public function rules()
    {
        return $this->hasOne(ServiceRule::class, 'service_id');
    }

    public function coverages()
    {
        return $this->hasMany(ServiceCoverage::class, 'service_id');
    }

    public function priceRules()
    {
        return $this->hasMany(ServicePrice::class, 'service_id');
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
            // جلب العنوان الافتراضي للكستمر مع تفاصيل المنطقة والمدينة المربوطة به
            $defaultAddress = $user->addresses()->where('is_default', true)->with('area')->first();

            if ($defaultAddress && $defaultAddress->area) {
                $areaId = $defaultAddress->area_id;
                $cityId = $defaultAddress->area->city_id;
                $now = now()->toDateString();

                // 🌟 الفحص الهرمي الذكي الجديد بدون حقل pricing_scope 🌟
                $specificPrice = $this->priceRules() // أو اسم العلاقة عندك للأسعار مثل prices()
                    ->where('city_id', $cityId) // تطابق المدينة إجباري
                    ->where(function ($query) use ($areaId) {
                        // السعر يطابق المنطقة تحديداً، أو السعر العام للمدينة (حيث المنطقة null)
                        $query->where('area_id', $areaId)
                            ->orWhereNull('area_id');
                    })
                    ->where(function ($query) use ($now) {
                        $query->whereNull('effective_start')->orWhere('effective_start', '<=', $now);
                    })
                    ->where(function ($query) use ($now) {
                        $query->whereNull('effective_end')->orWhere('effective_end', '>=', $now);
                    })
                    // الترتيب السحري: إذا وجد سعر للمنطقة يظهر أولاً، وإلا يرجع سعر المدينة كـ Fallback
                    ->orderByRaw('area_id IS NULL ASC')
                    ->first();

                if ($specificPrice) {
                    // العميل لديه سعر مخصص وموثق جغرافياً لمنطقته الحالية (سعر نهائي ثابت بالـ UI)
                    $specificPrice->is_fallback = false;

                    return $specificPrice;
                }
            }
        }

        // إذا لم يسجل دخول أو لم يملك عنواناً افتراضياً، نرجع السعر العام المتاح للخدمة كـ Fallback
        $fallbackPrice = $this->startingPrice;

        if ($fallbackPrice) {
            // نخبر الأوبجيكت والفرونت إند إنه مجرد خطة بديلة (فيُعرض بالـ UI بكلمة "يبدأ من")
            $fallbackPrice->is_fallback = true;
        }

        return $fallbackPrice;
    }

    public function childAgeGroups()
    {
        return $this->hasMany(ServiceChildAgeGroup::class, 'service_id');
    }

    public function media()
    {
        return $this->morphMany(Media::class, 'model');
    }

    public function workers()
    {
        return $this->belongsToMany(Worker::class, 'service_workers');
    }

    public function coupons()
    {
        return $this->belongsToMany(Coupon::class, 'coupon_services');
    }

    public function reviews()
    {
        return $this->morphMany(Review::class, 'reviewable');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class, 'service_id');
    }
}
