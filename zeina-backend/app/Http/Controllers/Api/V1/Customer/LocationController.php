<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Area\AreaResource;
use App\Http\Resources\Api\V1\City\CityResource;
use App\Http\Resources\Api\V1\Contry\CountryResource;
use App\Http\Resources\Api\V1\Governrate\GovernrateResource;
use App\Models\Area;
use App\Models\City;
use App\Models\Country;
use App\Models\Govrenrate;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    use ApiResponseTrait;

    public function countries()
    {
        $countries = Country::where('status', 'active')->get();
        return $this->successResponse(CountryResource::collection($countries),__('messages.countries_fetch_success'));
    }

    // 2. جلب المحافظات التابعة لدولة معينة
    public function governorates(Request $request)
    {
        $request->validate(['country_id' => 'required|exists:countries,id']);
        $governorates = Govrenrate::where('country_id', $request->country_id)
                                   ->where('status', 'active')
                                   ->get();
                                   
        return $this->successResponse( GovernrateResource::collection($governorates), __('messages.governorates_fetch_success'));
    }

    // 3. جلب المدن التابعة لمحافظة معينة
    public function cities(Request $request)
    {
        $request->validate(['govrenrate_id' => 'required|exists:govrenrates,id']);
        
        $cities = City::where('govrenrate_id', $request->govrenrate_id)
                      ->where('status', 'active')
                      ->get(['id', 'name_ar', 'name_en']);
                      
        return $this->successResponse( CityResource::collection($cities), __('messages.cities_fetch_success'));
    }

    // 4. جلب المناطق التابعة لمدينة معينة
    public function areas(Request $request)
    {
        $request->validate(['city_id' => 'required|exists:cities,id']);
        $areas = Area::where('city_id', $request->city_id)
                     ->with(['city'])
                     ->where('status', 'active')
                     ->get();
                     
        return $this->successResponse(AreaResource::collection($areas), __('messages.areas_fetch_success'));
    }

    
}
