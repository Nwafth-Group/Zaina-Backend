<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Coupon\CouponResource;
use App\Models\Booking;
use App\Models\Coupon;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    use ApiResponseTrait;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $now = now();

        $completedBookingsCount = 0;
        $defaultAddress = null;

        if ($user) {
            $completedBookingsCount = Booking::where('customer_id', $user->id)
                ->where('status', 'completed')
                ->count();

            $defaultAddress = $user->addresses()
                ->where('is_default', true)
                ->where('is_active', true)
                ->with('area')
                ->first();
        }

        // Build elegant Eloquent query to fetch available OR used coupons
        $query = Coupon::query();

        $query->where(function ($mainQ) use ($user, $now, $completedBookingsCount, $defaultAddress) {
            // available coupons (Option A)
            $mainQ->where(function ($availQ) use ($user, $now, $completedBookingsCount, $defaultAddress) {
                $availQ->where('status', 'active')
                    ->where('start_date', '<=', $now)
                    ->where('end_date', '>=', $now)
                    ->where('completed_bookings_count', '<=', $completedBookingsCount)
                    ->where(function ($userQ) use ($user) {
                        $userQ->where('is_for_all_users', true);
                        if ($user) {
                            $userQ->orWhereHas('users', function ($uQ) use ($user) {
                                $uQ->where('users.id', $user->id);
                            });
                        }
                    });

                // Location filtering
                if ($user && $defaultAddress && $defaultAddress->service_zone_id) {
                    $zoneId = $defaultAddress->service_zone_id;

                    $availQ->where(function ($locQ) use ($zoneId) {
                        $locQ->where('is_for_all_cities', true)
                            ->orWhere(function ($subQ) use ($zoneId) {
                                $subQ->where('is_for_all_cities', false)
                                    ->whereHas('serviceZones', function ($zQ) use ($zoneId) {
                                        $zQ->where('service_zones.id', $zoneId);
                                    });
                            });
                    });
                }
            });

            // used coupons (Option B)
            if ($user) {
                $mainQ->orWhereHas('bookings', function ($bQ) use ($user) {
                    $bQ->where('customer_id', $user->id)
                       ->where('status', '!=', 'cancelled');
                });
            }
        });

        $coupons = $query->with(['services', 'serviceZones'])->get();

        $coupons->each(function ($coupon) use ($user, $now, $completedBookingsCount) {
            $userUsageCount = 0;
            if ($user) {
                $userUsageCount = Booking::where('customer_id', $user->id)
                    ->where('coupon_id', $coupon->id)
                    ->where('status', '!=', 'cancelled')
                    ->count();
            }

            $isExpired = $coupon->end_date ? $coupon->end_date->isPast() : false;

            $isAvailable = $coupon->status === 'active' &&
                !$isExpired &&
                ($coupon->start_date === null || $coupon->start_date <= $now) &&
                ($coupon->usage_limit === null || $coupon->used_count < $coupon->usage_limit) &&
                ($coupon->user_usage_limit === null || $userUsageCount < $coupon->user_usage_limit) &&
                ($completedBookingsCount >= $coupon->completed_bookings_count);

            $coupon->is_used = $userUsageCount > 0;
            $coupon->is_available = $isAvailable;
            $coupon->user_usage_count = $userUsageCount;
        });

        return $this->successResponse(
            CouponResource::collection($coupons),
            __('messages.coupons_retrieved') ?? 'Available coupons retrieved successfully.'
        );
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $user = auth()->user();
        $now = now();

        $completedBookingsCount = 0;
        $defaultAddress = null;

        if ($user) {
            $completedBookingsCount = Booking::where('customer_id', $user->id)
                ->where('status', 'completed')
                ->count();

            $defaultAddress = $user->addresses()
                ->where('is_default', true)
                ->where('is_active', true)
                ->with('area')
                ->first();
        }

        $query = Coupon::where('id', $id);

        $query->where(function ($mainQ) use ($user, $now, $completedBookingsCount, $defaultAddress) {
            // available coupons (Option A)
            $mainQ->where(function ($availQ) use ($user, $now, $completedBookingsCount, $defaultAddress) {
                $availQ->where('status', 'active')
                    ->where('start_date', '<=', $now)
                    ->where('end_date', '>=', $now)
                    ->where('completed_bookings_count', '<=', $completedBookingsCount)
                    ->where(function ($userQ) use ($user) {
                        $userQ->where('is_for_all_users', true);
                        if ($user) {
                            $userQ->orWhereHas('users', function ($uQ) use ($user) {
                                $uQ->where('users.id', $user->id);
                            });
                        }
                    });

                // Location filtering
                if ($user && $defaultAddress && $defaultAddress->service_zone_id) {
                    $zoneId = $defaultAddress->service_zone_id;

                    $availQ->where(function ($locQ) use ($zoneId) {
                        $locQ->where('is_for_all_cities', true)
                            ->orWhere(function ($subQ) use ($zoneId) {
                                $subQ->where('is_for_all_cities', false)
                                    ->whereHas('serviceZones', function ($zQ) use ($zoneId) {
                                        $zQ->where('service_zones.id', $zoneId);
                                    });
                            });
                    });
                }
            });

            // used coupons (Option B)
            if ($user) {
                $mainQ->orWhereHas('bookings', function ($bQ) use ($user) {
                    $bQ->where('customer_id', $user->id)
                       ->where('status', '!=', 'cancelled');
                });
            }
        });

        $coupon = $query->with(['services', 'serviceZones'])->first();

        if (! $coupon) {
            return $this->errorResponse(
                __('messages.coupon_not_found_or_expired') ?? 'هذا الكوبون غير متاح أو منتهي الصلاحية لحسابك.',
                404
            );
        }

        $userUsageCount = 0;
        if ($user) {
            $userUsageCount = Booking::where('customer_id', $user->id)
                ->where('coupon_id', $coupon->id)
                ->where('status', '!=', 'cancelled')
                ->count();
        }

        $isExpired = $coupon->end_date ? $coupon->end_date->isPast() : false;

        $isAvailable = $coupon->status === 'active' &&
            !$isExpired &&
            ($coupon->start_date === null || $coupon->start_date <= $now) &&
            ($coupon->usage_limit === null || $coupon->used_count < $coupon->usage_limit) &&
            ($coupon->user_usage_limit === null || $userUsageCount < $coupon->user_usage_limit) &&
            ($completedBookingsCount >= $coupon->completed_bookings_count);

        $coupon->is_used = $userUsageCount > 0;
        $coupon->is_available = $isAvailable;
        $coupon->user_usage_count = $userUsageCount;

        return $this->successResponse(
            new CouponResource($coupon),
            __('messages.coupon_details_retrieved') ?? 'Coupon details retrieved successfully.'
        );
    }
}
