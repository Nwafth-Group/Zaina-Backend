<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreServicePriceRequest;
use App\Http\Resources\Api\V1\Service\ServicePriceResource;
use App\Models\ServiceCoverage;
use App\Models\ServicePrice;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class ServicePriceController extends Controller
{ 

use ApiResponseTrait;
    /**
     * Display a listing of the resource.
     */
  public function index(Request $request)
    { 

        $prices = ServicePrice::where('service_id', $request->service_id)
            ->with(['city', 'area'])
            ->get();

        return $this->successResponse(ServicePriceResource::collection($prices),__('messages.service_prices_retrieved_successfully'));
        // return response()->json(['data' => $prices]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreServicePriceRequest $request)
    {
         // التحقق من أن المنطقة/المدينة المختارة مشمولة فعلياً في التغطية (Coverage)
    
         $isCovered = ServiceCoverage::where('service_id', $request->service_id)
            ->where(function($q) use ($request) {
                if($request->pricing_scope == 'area') $q->where('area_id', $request->area_id);
                else $q->where('city_id', $request->city_id);
            })->exists();

        if (!$isCovered) {
            return $this->errorResponse(__('messages.cannot_setprice'),422);
        }

        // حفظ السعر (يمكن استخدام updateOrCreate لمنع التكرار في نفس النطاق)
        $price = ServicePrice::updateOrCreate(
            [
                'service_id'    => $request->service_id,
                'pricing_scope' => $request->pricing_scope,
                'city_id'       => $request->city_id,
                'area_id'       => $request->area_id,
            ],
            $request->validated()
        ); 

        return $this->successResponse(null,__('messages.service_price_save'),201);

        return response()->json(['message' => 'Pricing saved successfully.', 'data' => $price]);
    }

    /**
     * دالة السعر النهائي (Logic لشاشة العميل لاحقاً)
     * Total = BasePrice + (PricePerHour * Hours)
     */
    public function getEstimatedPrice(Request $request)
    {
        // $request->validate([
        //     'service_id' => 'required',
        //     'area_id'    => 'required',
        //     'hours'      => 'required|numeric'
        // ]); 


        // تطبيق قاعدة الأولوية: السعر المخصص للمنطقة يغلب سعر المدينة (Scenario 2)
        $priceRule = ServicePrice::where('service_id', $request->service_id)
            ->where('area_id', $request->area_id)
            ->first();

        if (!$priceRule) {
            // إذا لم نجد سعر للمنطقة، نبحث عن سعر المدينة التابعة لها هذه المنطقة
            $area = \App\Models\Area::find($request->area_id);
            $priceRule = ServicePrice::where('service_id', $request->service_id)
                ->where('pricing_scope', 'city')
                ->where('city_id', $area->city_id)
                ->first();
        }

        if (!$priceRule) {
            return  $this->errorResponse(__('messages.pricing_not_availble'),404);
        }

        $total = $priceRule->base_price + ($priceRule->price_per_hour * $request->hours);
           
        return $this->successResponse([
             'base_price' => $priceRule->base_price,
            'price_per_hour' => $priceRule->price_per_hour,
            'total_estimated' => $total,
            'currency' => $priceRule->currency
        ],__('messages.pricingestimated_retrive_success'));
       
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
