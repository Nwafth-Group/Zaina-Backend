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
        $countsQuery = Coupon::query();
        if ($request->filled('search')) {
            $search = $request->input('search');
            $countsQuery->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $counts = [
            'all' => (clone $countsQuery)->count(),
            'active' => (clone $countsQuery)->where('status', 'active')->count(),
            'inactive' => (clone $countsQuery)->where('status', 'inactive')->count(),
            'fixed' => (clone $countsQuery)->where('type', 'fixed')->count(),
            'percentage' => (clone $countsQuery)->where('type', 'percentage')->count(),
        ];

        $query = Coupon::withCount(['users', 'serviceZones', 'services']);

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

        return $this->successResponse([
            'items' => CouponResource::collection($coupons),
            'counts' => $counts,
            'meta' => [
                'current_page' => $coupons->currentPage(),
                'last_page' => $coupons->lastPage(),
                'per_page' => $coupons->perPage(),
                'total' => $coupons->total(),
            ],
        ], __('messages.coupons_retrieved') ?? 'Coupons retrieved successfully.');
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
                'completed_bookings_count', 'allowed_visit_types', 'min_workers', 'status',
            ]));

            // 2. Sync Users (Audience)
            if (! $request->is_for_all_users && $request->has('users')) {
                $coupon->users()->sync($request->users);
            }

            // 3. Sync Service Zones
            if (! $request->is_for_all_cities) {
                if ($request->has('service_zone_ids')) {
                    $coupon->serviceZones()->sync($request->service_zone_ids);
                }
            }

            // 4. Sync Services
            if (! $request->is_for_all_services && $request->has('services')) {
                $syncData = [];
                foreach ($request->services as $svc) {
                    $syncData[$svc['id']] = [
                        'allowed_visit_types' => isset($svc['allowed_visit_types']) ? json_encode($svc['allowed_visit_types']) : null,
                        'min_workers' => isset($svc['min_workers']) && $svc['min_workers'] !== '' ? (int) $svc['min_workers'] : null,
                    ];
                }
                $coupon->services()->sync($syncData);
            }

            DB::commit();

            // Load relationships for return resource
            $coupon->load(['users', 'services', 'serviceZones']);

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
        $coupon = Coupon::with(['users', 'services', 'serviceZones'])->findOrFail($id);

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

        // 1. Check if expired
        $isExpired = ($coupon->end_date && $coupon->end_date->isPast()) || 
                     ($coupon->usage_limit !== null && $coupon->used_count >= $coupon->usage_limit);

        if ($isExpired) {
            return $this->errorResponse(__('messages.coupon_expired_cannot_modify') ?? 'This coupon has expired and cannot be modified.', 422);
        }

        // 2. Check if used
        $isUsed = $coupon->used_count > 0;
        if ($isUsed) {
            // Check if end_date is reduced
            if ($request->has('end_date')) {
                $newEndDate = \Carbon\Carbon::parse($request->input('end_date'));
                if ($coupon->end_date && $newEndDate->lt($coupon->end_date)) {
                    return $this->errorResponse(__('messages.coupon_cannot_reduce_duration') ?? 'Cannot reduce the duration of a used coupon.', 422);
                }
            }

            // Check if usage_limit is reduced below current used count
            if ($request->has('usage_limit')) {
                $newUsageLimit = $request->input('usage_limit');
                if ($newUsageLimit !== null && $newUsageLimit < $coupon->used_count) {
                    return $this->errorResponse(__('messages.coupon_usage_limit_below_used') ?? 'Usage limit cannot be less than the current usage count.', 422);
                }
            }

            // Check for any changed forbidden fields
            $forbiddenFields = [
                'code', 'type', 'value', 'is_for_all_users', 
                'is_for_all_services', 'is_for_all_cities', 
                'completed_bookings_count', 'allowed_visit_types', 'min_workers'
            ];
            
            foreach ($forbiddenFields as $field) {
                if ($request->has($field)) {
                    $oldVal = $coupon->{$field};
                    $newVal = $request->input($field);
                    
                    if ($field === 'is_for_all_users' || $field === 'is_for_all_services' || $field === 'is_for_all_cities') {
                        $oldVal = (bool)$oldVal;
                        $newVal = (bool)$newVal;
                    }
                    if ($field === 'value') {
                        $oldVal = (float)$oldVal;
                        $newVal = (float)$newVal;
                    }
                    if ($field === 'allowed_visit_types' && is_array($newVal)) {
                        $oldVal = $oldVal ?? [];
                        sort($oldVal);
                        sort($newVal);
                    }

                    if ($oldVal != $newVal) {
                        return $this->errorResponse(
                            __('messages.coupon_used_cannot_change_conditions') ?? 'Cannot modify conditions/rules of a used coupon.', 
                            422
                        );
                    }
                }
            }

            // Check relations
            if ($request->has('users')) {
                $newUsers = $request->input('users', []);
                $oldUsers = $coupon->users()->pluck('users.id')->toArray();
                sort($newUsers);
                sort($oldUsers);
                if ($newUsers != $oldUsers) {
                    return $this->errorResponse(__('messages.coupon_used_cannot_change_audience') ?? 'Cannot change target users for a used coupon.', 422);
                }
            }

            if ($request->has('service_zone_ids')) {
                $newZones = $request->input('service_zone_ids', []);
                $oldZones = $coupon->serviceZones()->pluck('service_zones.id')->toArray();
                sort($newZones);
                sort($oldZones);
                if ($newZones != $oldZones) {
                    return $this->errorResponse(__('messages.coupon_used_cannot_change_zones') ?? 'Cannot change service zones for a used coupon.', 422);
                }
            }

            if ($request->has('services')) {
                $newServices = $request->input('services', []);
                $oldServices = $coupon->services()->get()->map(function($s) {
                    $allowed = is_string($s->pivot->allowed_visit_types) 
                        ? json_decode($s->pivot->allowed_visit_types, true) 
                        : $s->pivot->allowed_visit_types;
                    return [
                        'id' => $s->id,
                        'allowed_visit_types' => $allowed ?? [],
                        'min_workers' => $s->pivot->min_workers ? (int) $s->pivot->min_workers : null,
                    ];
                })->toArray();

                $newSvcMap = [];
                foreach ($newServices as $ns) {
                    $types = $ns['allowed_visit_types'] ?? [];
                    sort($types);
                    $newSvcMap[$ns['id']] = [
                        'types' => $types,
                        'min_workers' => isset($ns['min_workers']) && $ns['min_workers'] !== '' ? (int) $ns['min_workers'] : null,
                    ];
                }
                
                $oldSvcMap = [];
                foreach ($oldServices as $os) {
                    $types = $os['allowed_visit_types'] ?? [];
                    sort($types);
                    $oldSvcMap[$os['id']] = [
                        'types' => $types,
                        'min_workers' => $os['min_workers'],
                    ];
                }

                ksort($newSvcMap);
                ksort($oldSvcMap);

                if ($newSvcMap != $oldSvcMap) {
                    return $this->errorResponse(__('messages.coupon_used_cannot_change_services') ?? 'Cannot change services for a used coupon.', 422);
                }
            }
        }

        DB::beginTransaction();
        try {
            $isForAllUsers = $request->boolean('is_for_all_users');
            $isForAllCities = $request->boolean('is_for_all_cities');
            $isForAllServices = $request->boolean('is_for_all_services');

            // 1. Update Coupon
            $coupon->update([
                'name' => $request->input('name'),
                'code' => $request->input('code'),
                'type' => $request->input('type'),
                'value' => $request->input('value'),
                'usage_limit' => $request->input('usage_limit'),
                'user_usage_limit' => $request->input('user_usage_limit'),
                'start_date' => $request->input('start_date'),
                'end_date' => $request->input('end_date'),
                'is_for_all_users' => $isForAllUsers,
                'is_for_all_cities' => $isForAllCities,
                'is_for_all_services' => $isForAllServices,
                'completed_bookings_count' => $request->input('completed_bookings_count', 0),
                'allowed_visit_types' => $request->input('allowed_visit_types'),
                'min_workers' => $request->input('min_workers'),
                'status' => $request->input('status'),
            ]);

            // 2. Sync Users (Audience)
            if ($isForAllUsers) {
                $coupon->users()->detach();
            } elseif ($request->has('users')) {
                $coupon->users()->sync($request->users);
            }

            // 3. Sync Service Zones
            if ($isForAllCities) {
                $coupon->serviceZones()->detach();
            } else {
                if ($request->has('service_zone_ids')) {
                    $coupon->serviceZones()->sync($request->service_zone_ids);
                }
            }

            // 4. Sync Services
            if ($isForAllServices) {
                $coupon->services()->detach();
            } elseif ($request->has('services')) {
                $syncData = [];
                foreach ($request->services as $svc) {
                    $syncData[$svc['id']] = [
                        'allowed_visit_types' => isset($svc['allowed_visit_types']) ? json_encode($svc['allowed_visit_types']) : null,
                        'min_workers' => isset($svc['min_workers']) && $svc['min_workers'] !== '' ? (int) $svc['min_workers'] : null,
                    ];
                }
                $coupon->services()->sync($syncData);
            }

            DB::commit();

            // Load relationships for return resource
            $coupon->load(['users', 'services', 'serviceZones']);

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

        $isExpired = ($coupon->end_date && $coupon->end_date->isPast()) || 
                     ($coupon->usage_limit !== null && $coupon->used_count >= $coupon->usage_limit);

        if ($isExpired) {
            return $this->errorResponse(__('messages.coupon_expired_cannot_delete') ?? 'Expired coupons cannot be deleted.', 422);
        }

        if ($coupon->used_count > 0) {
            return $this->errorResponse(__('messages.coupon_used_cannot_delete') ?? 'Used coupons cannot be deleted.', 422);
        }

        $coupon->delete();

        return $this->successResponse(
            null,
            __('messages.coupon_deleted_successfully') ?? 'Coupon deleted successfully.'
        );
    }
}
