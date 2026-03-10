<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreAreaRequest;
use App\Http\Requests\Api\V1\Admin\UpdateAreaRequest;
use App\Http\Resources\Api\V1\Area\AreaResource;
use App\Models\Area;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class AreaController extends Controller
{
    use ApiResponseTrait;
    /**
     * Display a listing of the resource.
     */
  public function index(Request $request)
    {
        $query = Area::with('city.country');

        if ($request->city_id) {
            $query->where('city_id', $request->city_id);
        }

        return $this->successResponse(AreaResource::collection($query->paginate(15)),__('messages.areas_fetch_success'));
    }

    public function store(StoreAreaRequest $request)
    {   
       
        $area = Area::create($request->validated());
        return $this->successResponse(AreaResource::make($area), __('messages.area_created_successfully'));
    }

    public function update(UpdateAreaRequest $request , Area $area){
        $area = $area->update($request->validated());
        return $this->successResponse(null,__('messages.area_update_success'));
    }

    public function destroy(Area $area)
    {
        // هنا قد نمنع الحذف مستقبلاً إذا كان هناك عناوين عملاء مرتبطة بهذه المنطقة
        // if ($area->addresses()->count() > 0) { ... }

        $area->delete();
        return $this->successResponse(null, __('messages.area_deleted_successfully') );
    }

    public function toggleStatus(Area $area)
    {
        // إذا كانت المنطقة تحتوي على عناوين عملاء، لا نسمح بتغيير الحالة إلى "inactive"
        if ($area->addresses()->count() > 0 && $area->status === 'active') {
            return $this->errorResponse(__('messages.cannot_toggle_area_status_with_addresses'), 400);
        }

        $area->status = $area->status === 'active' ? 'inactive' : 'active';
        $area->save();

        return $this->successResponse(null, __('messages.area_status_changed'));
    }
}
