<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreCountryRequest;
use App\Http\Requests\Api\V1\Admin\UpdateCountryRequest;
use App\Http\Resources\Api\V1\Contry\CountryResource;
use App\Models\Country;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class CountryController extends Controller
{
     use ApiResponseTrait;
    
  public function index()
    {
        // عرض كل الدول مع عدد المدن الموجودة في كل دولة
        $countries = Country::withCount('cities')->get();
        return $this->successResponse(CountryResource::collection($countries), __('messages.countries_retrieved_successfully'));
       
    }

    /**
     * Store a newly created resource in storage.
     */
 public function store(StoreCountryRequest $request)
    {
        
        $country = Country::create($request->validated());
        return $this->successResponse(new CountryResource($country), __('messages.country_created_successfully'));
    }

    public function update(UpdateCountryRequest $request, Country $country)
    {
        $country->update($request->validated());
        
        return $this->successResponse(new CountryResource($country), __('messages.country_updated_successfully'));
    }

    /**
     * Update the specified resource in storage.
     */
   

    /**
     * Remove the specified resource from storage.
     */
   public function destroy(Country $country)
    {
        // منع الحذف إذا كان هناك مدن مرتبطة (BR-03)
        if ($country->cities()->count() > 0) {
            return $this->errorResponse(__('messages.cannot_delete_country_with_cities'), 403);
        }

        $country->delete();
        return $this->successResponse(null, __('messages.country_deleted_successfully'));

    }

    public function toggleStatus(Country $country)
    {
        $country->status = $country->status === 'active' ? 'inactive' : 'active';
        $country->save();

        return $this->successResponse(new CountryResource($country), __('messages.country_status_toggled_successfully'));
    }
}
