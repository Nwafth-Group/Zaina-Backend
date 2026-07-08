<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Coupon\CouponResource;
use App\Models\Coupon;
use App\Models\Booking;
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

        // Build elegant Eloquent query
        $query = Coupon::where('status', 'active')
            ->where('start_date', '<=', $now)
            ->where('end_date', '>=', $now)
            ->where('completed_bookings_count', '<=', $completedBookingsCount)
            ->where(function ($q) use ($user) {
                $q->where('is_for_all_users', true);
                if ($user) {
                    $q->orWhereHas('users', function ($uQ) use ($user) {
                        $uQ->where('users.id', $user->id);
                    });
                }
            });

        // Location filtering based on user default address
        if ($user && $defaultAddress) {
            $areaId = $defaultAddress->area_id;
            $cityId = $defaultAddress->area ? $defaultAddress->area->city_id : null;

            $query->where(function ($q) use ($cityId, $areaId) {
                $q->where('is_for_all_cities', true)
                  ->orWhere(function ($subQ) use ($cityId, $areaId) {
                      $subQ->where('is_for_all_cities', false)
                           ->where(function ($locQ) use ($cityId, $areaId) {
                               if ($areaId) {
                                   $locQ->whereHas('areas', function ($aQ) use ($areaId) {
                                       $aQ->where('areas.id', $areaId);
                                   });
                               }
                               if ($cityId) {
                                   $locQ->orWhere(function ($citySubQ) use ($cityId) {
                                       $citySubQ->whereHas('cities', function ($cQ) use ($cityId) {
                                           $cQ->where('cities.id', $cityId);
                                       })->whereDoesntHave('areas');
                                   });
                               }
                           });
                  });
            });
        }

        $coupons = $query->with(['services', 'cities', 'areas'])->get();

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

        $query = Coupon::where('id', $id)
            ->where('status', 'active')
            ->where('start_date', '<=', $now)
            ->where('end_date', '>=', $now)
            ->where('completed_bookings_count', '<=', $completedBookingsCount)
            ->where(function ($q) use ($user) {
                $q->where('is_for_all_users', true);
                if ($user) {
                    $q->orWhereHas('users', function ($uQ) use ($user) {
                        $uQ->where('users.id', $user->id);
                    });
                }
            });

        if ($user && $defaultAddress) {
            $areaId = $defaultAddress->area_id;
            $cityId = $defaultAddress->area ? $defaultAddress->area->city_id : null;

            $query->where(function ($q) use ($cityId, $areaId) {
                $q->where('is_for_all_cities', true)
                  ->orWhere(function ($subQ) use ($cityId, $areaId) {
                      $subQ->where('is_for_all_cities', false)
                           ->where(function ($locQ) use ($cityId, $areaId) {
                               if ($areaId) {
                                   $locQ->whereHas('areas', function ($aQ) use ($areaId) {
                                       $aQ->where('areas.id', $areaId);
                                   });
                               }
                               if ($cityId) {
                                   $locQ->orWhere(function ($citySubQ) use ($cityId) {
                                       $citySubQ->whereHas('cities', function ($cQ) use ($cityId) {
                                           $cQ->where('cities.id', $cityId);
                                       })->whereDoesntHave('areas');
                                   });
                               }
                           });
                  });
            });
        }

        $coupon = $query->with(['services', 'cities', 'areas'])->first();

        if (!$coupon) {
            return $this->errorResponse(
                __('messages.coupon_not_found_or_expired') ?? 'هذا الكوبون غير متاح أو منتهي الصلاحية لحسابك.', 
                404
            );
        }

        return $this->successResponse(
            new CouponResource($coupon), 
            __('messages.coupon_details_retrieved') ?? 'Coupon details retrieved successfully.'
        );
    }
}
