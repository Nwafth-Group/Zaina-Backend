<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreCouponRequest;
use App\Http\Requests\Api\V1\Admin\UpdateCouponRequest;
use App\Http\Resources\Api\V1\Coupon\CouponResource;
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
    public function index(Request $request)
    {
        $query = Coupon::withCount(['users', 'cities', 'areas', 'services']);

        // 1. Search filter by code or name
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        // 2. Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // 3. Paginate
        $perPage = $request->input('per_page', 15);
        $coupons = $query->latest()->paginate($perPage);

        return $this->successResponse(
            CouponResource::collection($coupons),
            __('messages.coupons_retrieved') ?? 'Coupons retrieved successfully.'
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCouponRequest $request)
    {
        DB::beginTransaction();
        try {
            // 1. Create Coupon
            $coupon = Coupon::create($request->only([
                'name', 'code', 'type', 'value',
                'usage_limit', 'user_usage_limit', 'start_date', 'end_date',
                'is_for_all_users', 'is_for_all_services', 'is_for_all_cities',
                'completed_bookings_count', 'allowed_visit_types', 'allowed_shifts', 'status',
            ]));

            // 2. Sync Users (Audience)
            if (! $request->is_for_all_users && $request->has('users')) {
                $coupon->users()->sync($request->users);
            }

            // 3. Sync Cities & Areas
            if (! $request->is_for_all_cities) {
                if ($request->has('cities')) {
                    $coupon->cities()->sync($request->cities);
                }
                if ($request->has('areas')) {
                    $coupon->areas()->sync($request->areas);
                }
            }

            // 4. Sync Services
            if (! $request->is_for_all_services && $request->has('services')) {
                $syncData = [];
                foreach ($request->services as $svc) {
                    $syncData[$svc['id']] = [
                        'allowed_visit_types' => isset($svc['allowed_visit_types']) ? json_encode($svc['allowed_visit_types']) : null,
                        'allowed_shifts' => isset($svc['allowed_shifts']) ? json_encode($svc['allowed_shifts']) : null,
                    ];
                }
                $coupon->services()->sync($syncData);
            }

            DB::commit();

            // Load relationships for return resource
            $coupon->load(['users', 'services', 'cities', 'areas']);

            return $this->successResponse(
                new CouponResource($coupon),
                __('messages.coupon_created_successfully') ?? 'Coupon created successfully.',
                201
            );

        } catch (\Exception $e) {
            DB::rollBack();

            return $this->errorResponse('Error saving coupon: '.$e->getMessage(), 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $coupon = Coupon::with(['users', 'services', 'cities', 'areas'])->findOrFail($id);

        return $this->successResponse(
            new CouponResource($coupon),
            __('messages.coupon_details_retrieved') ?? 'Coupon details retrieved successfully.'
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCouponRequest $request, string $id)
    {
        $coupon = Coupon::findOrFail($id);

        DB::beginTransaction();
        try {
            // 1. Update Coupon
            $coupon->update($request->only([
                'name', 'code', 'type', 'value',
                'usage_limit', 'user_usage_limit', 'start_date', 'end_date',
                'is_for_all_users', 'is_for_all_services', 'is_for_all_cities',
                'completed_bookings_count', 'allowed_visit_types', 'allowed_shifts', 'status',
            ]));

            // 2. Sync Users (Audience)
            if ($request->is_for_all_users) {
                $coupon->users()->detach();
            } elseif ($request->has('users')) {
                $coupon->users()->sync($request->users);
            }

            // 3. Sync Cities & Areas
            if ($request->is_for_all_cities) {
                $coupon->cities()->detach();
                $coupon->areas()->detach();
            } else {
                if ($request->has('cities')) {
                    $coupon->cities()->sync($request->cities);
                }
                if ($request->has('areas')) {
                    $coupon->areas()->sync($request->areas);
                }
            }

            // 4. Sync Services
            if ($request->is_for_all_services) {
                $coupon->services()->detach();
            } elseif ($request->has('services')) {
                $syncData = [];
                foreach ($request->services as $svc) {
                    $syncData[$svc['id']] = [
                        'allowed_visit_types' => isset($svc['allowed_visit_types']) ? json_encode($svc['allowed_visit_types']) : null,
                        'allowed_shifts' => isset($svc['allowed_shifts']) ? json_encode($svc['allowed_shifts']) : null,
                    ];
                }
                $coupon->services()->sync($syncData);
            }

            DB::commit();

            // Load relationships for return resource
            $coupon->load(['users', 'services', 'cities', 'areas']);

            return $this->successResponse(
                new CouponResource($coupon),
                __('messages.coupon_updated_successfully') ?? 'Coupon updated successfully.'
            );

        } catch (\Exception $e) {
            DB::rollBack();

            return $this->errorResponse('Error updating coupon: '.$e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $coupon = Coupon::findOrFail($id);
        $coupon->delete();

        return $this->successResponse(
            null,
            __('messages.coupon_deleted_successfully') ?? 'Coupon deleted successfully.'
        );
    }
}
