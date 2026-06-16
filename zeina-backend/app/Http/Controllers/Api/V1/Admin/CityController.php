<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreCityRequest;
use App\Http\Resources\Api\V1\City\CityResource;
use App\Models\City;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class CityController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request)
    {
        // جلب المدن مع دولتهم، وإمكانية الفلترة
        $query = City::with('govrenrate.country');

        if ($request->country_name) {
            $query->whereHas('govrenrate.country', function ($q) use ($request) {
                $q->where('name_en', 'like', "%{$request->country_name}%");
                $q->orWhere('name_ar', 'like', "%{$request->country_name}%");
            });
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        return $this->successResponse(CityResource::collection($query->get()), __('messages.cities_retrieved_successfully'));
    }

    public function store(StoreCityRequest $request)
    {
        $city = City::create($request->validated());

        return $this->successResponse(new CityResource($city), __('messages.city_created_successfully'));
    }

    public function update(StoreCityRequest $request, City $city)
    {
        // Scenario 3: إذا حاول الإدمن تغيير دولة المدينة وفيها مناطق، نمنعه!
        if ($city->country_id != $request->country_id && $city->areas()->count() > 0) {
            return $this->errorResponse(__('messages.cannot_move_city_to_another_country'));
        }

        $city->update($request->validated());

        return $this->successResponse(new CityResource($city), __('messages.city_updated_successfully'));
    }

    public function destroy(City $city)
    {
        // Scenario 4 & BR-03: منع الحذف إذا كان هناك بيانات مرتبطة (مناطق)
        if ($city->areas()->count() > 0) {
            return $this->errorResponse(__('messages.cannot_delete_city_with_areas'), 403);
        }

        $city->delete();

        return $this->successResponse(null, __('messages.city_deleted_successfully'));
    }

    // دالة مساعدة لتغيير الحالة (Deactivate/Activate)
    public function toggleStatus(City $city)
    {
        $city->status = $city->status === 'active' ? 'inactive' : 'active';
        $city->save();

        return $this->successResponse(null, __('messages.city_status_changed'));
    }
}
