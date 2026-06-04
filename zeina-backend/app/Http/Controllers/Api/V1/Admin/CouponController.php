<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreCouponRequest;
use App\Models\Coupon;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CouponController extends Controller
{

use ApiResponseTrait;
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCouponRequest $request)
    {
        DB::beginTransaction();
    try {
        // 1. إنشاء الكوبون الرئيسي
        $coupon = Coupon::create($request->only([
            'code', 'type', 'value', 'max_discount', 'min_booking_amount',
            'start_date', 'end_date', 'is_for_all_users', 'is_for_all_services'
        ]));

        // 2. إذا كان مخصصاً لمستخدمين معينين، نربطهم بالجدول الوسيط
        if (!$request->is_for_all_users) {
            $coupon->users()->sync($request->users);
        }

        // 3. إذا كان مخصصاً لخدمات معينة، نربطها بالجدول الوسيط
        if (!$request->is_for_all_services) {
            $coupon->services()->sync($request->services);
        }

        DB::commit();
        return $this->successResponse($coupon,__('messages.coupon_created_successfully'));

    } catch (\Exception $e) {
        DB::rollBack();
        return $this->errorResponse('حدث خطأ أثناء حفظ الكوبون: ' . $e->getMessage(), 500);
    }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
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
