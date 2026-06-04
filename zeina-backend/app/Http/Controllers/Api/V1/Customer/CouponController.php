<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Coupon\CouponResource;
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
        $customerId = auth()->id();
        $now = now();

        // بناء الاستعلام الذكي
        $coupons = Coupon::where('status', 'active')
            ->where('start_date', '<=', $now)
            ->where('end_date', '>=', $now) // نضمن أن الكوبون فعال زمنياً ولم ينتهِ
            ->where(function ($query) use ($customerId) {
                // أ. الكوبون عام لكل الناس
                $query->where('is_for_all_users', true)
                // ب. أو الكوبون مخصص وامتياز لهذا الكستمر تحديداً عبر جدول الربط الوسيط
                      ->orWhere(function ($subQ) use ($customerId) {
                          $subQ->where('is_for_all_users', false)
                               ->whereHas('users', function ($uQ) use ($customerId) {
                                   $uQ->where('user_id', $customerId);
                               });
                      });
            })
            // إذا كان الكوبون لخدمات مخصصة، نسحب الخدمات معه فوراً لمنع الـ N+1
            ->with('services')
            ->get();

        return $this->successResponse(
            CouponResource::collection($coupons), 
            __('messages.coupons_retrieved') ?? 'Available coupons retrieved successfully.'
        );
    }

    /**
     * عرض تفاصيل كوبون محدد للكستمر
     */
    public function show($id)
    {
        $customerId = auth()->id();
        $now = now();

        // جلب الكوبون مع التأكد التام أن العميل يملك صلاحية رؤيته واستخدامه
        $coupon = Coupon::where('id', $id)
            ->where('status', 'active')
            ->where('start_date', '<=', $now)
            ->where('end_date', '>=', $now)
            ->where(function ($query) use ($customerId) {
                $query->where('is_for_all_users', true)
                      ->orWhereHas('users', function ($uQ) use ($customerId) {
                          $uQ->where('user_id', $customerId);
                      });
            })
            ->with('services')
            ->first();

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

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    
    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
