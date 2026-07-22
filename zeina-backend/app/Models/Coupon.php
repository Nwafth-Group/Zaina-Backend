<?php

namespace App\Models;

use Database\Factories\CouponFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Coupon extends Model implements Auditable
{
    /** @use HasFactory<CouponFactory> */
    use HasFactory, \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'name', 'code', 'type', 'value',
        'usage_limit', 'user_usage_limit', 'used_count',
        'start_date', 'end_date', 'is_for_all_users', 'is_for_all_services',
        'is_for_all_cities', 'completed_bookings_count', 'allowed_visit_types', 'min_workers',
        'status',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'is_for_all_users' => 'boolean',
        'is_for_all_services' => 'boolean',
        'is_for_all_cities' => 'boolean',
        'allowed_visit_types' => 'array',
    ];

    public function services()
    {
        return $this->belongsToMany(Service::class, 'coupon_services')
            ->withPivot('allowed_visit_types', 'min_workers')
            ->withTimestamps();
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'coupon_users')
            ->withPivot('used_count')
            ->withTimestamps();
    }

    public function cities()
    {
        return $this->belongsToMany(City::class, 'coupon_cities')
            ->withTimestamps();
    }

    public function areas()
    {
        return $this->belongsToMany(Area::class, 'coupon_areas')
            ->withTimestamps();
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Check if a coupon is eligible for a customer, service, visit type, start/end times and address.
     * Returns null if eligible, or an error translation key if not.
     */
    public function checkEligibility($user, Service $service, string $visitType, string $startTime, string $endTime, ?CustomerAddress $address, int $workersCount = 1): ?string
    {
        // 1. Check Status
        if ($this->status !== 'active') {
            return 'coupon_inactive';
        }

        // 2. Check Dates
        $now = now();
        if ($this->start_date && $now->lt($this->start_date)) {
            return 'coupon_not_started';
        }
        if ($this->end_date && $now->gt($this->end_date)) {
            return 'coupon_expired';
        }

        // 3. Check Total Usage Limit
        if ($this->usage_limit !== null && $this->used_count >= $this->usage_limit) {
            return 'coupon_usage_limit_reached';
        }

        // 4. Check User-specific Usage Limit
        if ($user) {
            $userUsageCount = Booking::where('customer_id', $user->id)
                ->where('coupon_id', $this->id)
                ->where('status', '!=', 'cancelled')
                ->count();
            if ($this->user_usage_limit !== null && $userUsageCount >= $this->user_usage_limit) {
                return 'coupon_user_limit_reached';
            }
        }

        // 5. Check Audience (Specific Users)
        if (! $this->is_for_all_users) {
            if (! $user) {
                return 'coupon_unauthenticated';
            }
            $isUserEligible = $this->users()->where('users.id', $user->id)->exists();
            if (! $isUserEligible) {
                return 'coupon_not_for_user';
            }
        }

        // 6. Check Location Eligibility
        if (! $this->is_for_all_cities) {
            if (! $address) {
                return 'coupon_address_required';
            }

            // Check area restrictions if any are set
            $hasAreaRestrictions = $this->areas()->exists();
            if ($hasAreaRestrictions) {
                $isAreaEligible = $this->areas()->where('areas.id', $address->area_id)->exists();
                if (! $isAreaEligible) {
                    return 'coupon_location_ineligible';
                }
            } else {
                // Otherwise check city restriction
                $isCityEligible = $this->cities()->where('cities.id', $address->area->city_id)->exists();
                if (! $isCityEligible) {
                    return 'coupon_location_ineligible';
                }
            }
        }

        // 7. Check Completed Booking Count Condition (for any service, per user feedback)
        if ($this->completed_bookings_count > 0) {
            if (! $user) {
                return 'coupon_unauthenticated';
            }

            $completedCount = Booking::where('customer_id', $user->id)
                ->where('status', 'completed')
                ->count();

            if ($completedCount < $this->completed_bookings_count) {
                return 'coupon_insufficient_completed_bookings';
            }
        }

        // 8. Check Service and Service-Specific Rules (Visit Types, Minimum Workers)
        if (! $this->is_for_all_services) {
            $pivot = $this->services()->where('services.id', $service->id)->first()?->pivot;
            if (! $pivot) {
                return 'coupon_service_ineligible';
            }

            // Check Visit Type Eligibility
            $allowedVisitTypes = is_string($pivot->allowed_visit_types)
                ? json_decode($pivot->allowed_visit_types, true)
                : $pivot->allowed_visit_types;

            if (! empty($allowedVisitTypes)) {
                if (! in_array($visitType, $allowedVisitTypes)) {
                    return 'coupon_visit_type_ineligible';
                }
            }

            // Check Minimum Workers Constraint for this specific service
            if (isset($pivot->min_workers) && (int) $pivot->min_workers > 0) {
                if ($workersCount < (int) $pivot->min_workers) {
                    return 'coupon_min_workers_ineligible';
                }
            }

        } else {
            // General rules for Coupon (if valid for all services but has visit type or min_workers limitations)
            $allowedVisitTypes = $this->allowed_visit_types;
            if (! empty($allowedVisitTypes)) {
                // If Admin selected all services + a specific visit type, the service itself must support this visit type
                $serviceSupportsVisitType = false;
                $serviceTypes = $service->available_visit_types ?? [];
                foreach ($serviceTypes as $vt) {
                    $vtType = $vt['type'] ?? '';
                    if ($vtType === $visitType
                        || ($visitType === 'three_visits_per_week' && ($vtType === '3_days_per_week' || $vtType === '3_visits_per_week'))
                        || ($visitType === '3_days_per_week' && ($vtType === 'three_visits_per_week' || $vtType === '3_visits_per_week'))
                    ) {
                        $serviceSupportsVisitType = true;
                        break;
                    }
                }

                if (! $serviceSupportsVisitType || ! in_array($visitType, $allowedVisitTypes)) {
                    return 'coupon_visit_type_ineligible';
                }
            }

            // Check Minimum Workers Constraint for coupon (all services)
            if ($this->min_workers !== null && (int) $this->min_workers > 0) {
                if ($workersCount < (int) $this->min_workers) {
                    return 'coupon_min_workers_ineligible';
                }
            }
        }

        return null;
    }
}
