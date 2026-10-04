<?php

namespace App\Models;

use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Service extends Model implements Auditable
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory, \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'category_id', 'type', 'worker_gender_options', 'pricing_type', 'is_team_based', 'name_ar', 'name_en',
        'description_ar', 'description_en', 'status', 'avg_rating', 'features',
        'service_code', 'available_visit_types', 'terms_ar',
        'terms_en', 'start_time', 'end_time',
        'default_duration', 'has_duration_range',
        'min_duration', 'max_duration', 'min_workers', 'max_workers', 'base_price',
        'min_children', 'max_children', 'covered_persons_per_worker',
        'points_redemption_enabled', 'points_redemption_method', 'points_required_fixed', 'points_conversion_rate',
    ];

    protected $casts = [
        'is_team_based' => 'boolean',
        'features' => 'array',
        'available_visit_types' => 'array',
        'worker_gender_options' => 'array',
        'has_duration_range' => 'boolean',
        'base_price' => 'decimal:2',
        'min_children' => 'integer',
        'max_children' => 'integer',
        'covered_persons_per_worker' => 'integer',
        'points_redemption_enabled' => 'boolean',
        'points_required_fixed' => 'integer',
        'points_conversion_rate' => 'integer',
    ];

    protected static function booted()
    {
        static::creating(function ($service) {
            if (empty($service->service_code)) {
                $latest = static::orderBy('id', 'desc')->first();
                $nextId = $latest ? $latest->id + 1 : 1;
                $service->service_code = 'SVC-'.date('Y').'-'.str_pad($nextId, 4, '0', STR_PAD_LEFT);
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

    public function rules()
    {
        return $this->hasOne(ServiceRule::class, 'service_id');
    }

    public function schedules()
    {
        return $this->hasMany(ServiceSchedule::class, 'service_id');
    }

    public function childAgeGroups()
    {
        return $this->hasMany(ServiceChildAgeGroup::class, 'service_id');
    }

    public function options()
    {
        return $this->hasMany(ServiceOption::class, 'service_id')->orderBy('sort_order', 'asc')->orderBy('id', 'asc');
    }

    public function media()
    {
        return $this->morphMany(Media::class, 'model');
    }

    public function workers()
    {
        return $this->belongsToMany(Worker::class, 'service_workers');
    }

    public function teams()
    {
        return $this->belongsToMany(Team::class, 'service_teams')->withTimestamps();
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

    public function serviceZones()
    {
        return $this->belongsToMany(ServiceZone::class, 'service_service_zones', 'service_id', 'service_zone_id');
    }

    public static function getTeamBasedCodes(): array
    {
        return [
            'SOFA_CLEANING',
            'WATER_TANK_CLEANING',
            'STAIRS_SURFACES_CLEANING',
            'AC_CLEANING',
            'CARPET_CLEANING',
            'CAR_WASH',
            'MATTRESS_CLEANING',
            'CURTAIN_CLEANING',
        ];
    }

    public function isTeamBased(): bool
    {
        if (!is_null($this->is_team_based)) {
            return (bool) $this->is_team_based;
        }

        $code = strtoupper($this->service_code ?? '');
        $teamCodes = array_map('strtoupper', self::getTeamBasedCodes());
        return in_array($code, $teamCodes, true);
    }
}

