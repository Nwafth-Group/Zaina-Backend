<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\v1\cutomer\StoreAdderssRequest;
use App\Http\Resources\Api\V1\Address\AddressResource;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    use ApiResponseTrait;
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = auth()->user();
       
        $addresses = $user->addresses()->with('area.city')->orderBy('is_default', 'desc')->get();
         
         return $this->successResponse( AddressResource::collection($addresses) ,__('messages.addresses_retrieved_successfully'));
    }

    /**
     * إضافة عنوان جديد (US-05)
     */
    public function store(StoreAdderssRequest $request)
    {
        $user = auth()->user();

        // BR-05: إذا أراد العميل جعل هذا العنوان هو الافتراضي، نلغي الافتراضي من باقي عناوينه
        if ($request->is_default) {
            $user->addresses()->update(['is_default' => false]);
        }

        // حفظ العنوان
        $address = $user->addresses()->create([
            'area_id'      => $request->area_id,
            'latitude'     => $request->latitude,
            'longitude'    => $request->longitude,
            'label'        => $request->label,
            'street'       => $request->street,
            'building'     => $request->building,
            'floor'        => $request->floor,
            'unit'         => $request->unit,
            'notes'        => $request->notes,
            'is_default'   => $request->is_default ?? false,
            // كقيمة مبدئية نعتبره متاح للتغطية (يمكن ربطها لاحقاً بخوارزمية التحقق من النطاق الجغرافي)
            'serviceable_status' => 'serviceable', 
        ]);

        return $this->successResponse(new AddressResource($address), __('messages.address_saved_successfully'));
    }

    public function destroy($id)
    {
        $user = auth()->user();
        $address = $user->addresses()->findOrFail($id);
        $address->delete();
        return $this->successResponse(null, __('messages.address_deleted_successfully'));
    }

    public function setDefault($id)
    {
        $user = auth()->user();
        $address = $user->addresses()->findOrFail($id);
        $user->addresses()->update(['is_default' => false]);
        $address->update(['is_default' => true]);

        return $this->successResponse(null, __('messages.address_set_as_default_successfully'));

    }
}
