<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\CustomerAddressRequest;
use App\Http\Resources\Api\V1\Address\AddressResource;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller
{
    use ApiResponseTrait;
    /**
     * Display a listing of the resource.
     */
   public function index()
    {
        // استخدام with لجلب تفاصيل المنطقة لتكون جاهزة للعرض
        $addresses = auth()->user()->addresses()->with('area.city')->latest()->get();
        
        return $this->successResponse(AddressResource::collection($addresses), __('messages.addresses_fetched_successfully'));
    }

    // إضافة عنوان جديد
    public function store(CustomerAddressRequest $request)
    {
        $user = auth()->user();
        
        // إذا كان هذا أول عنوان للعميل، نجعله افتراضياً إجبارياً
        $isFirstAddress = $user->addresses()->count() === 0;
        $isDefault = $isFirstAddress ? true : ($request->is_default ?? false);

        DB::beginTransaction();
        try {
            if ($isDefault) {
                $user->addresses()->update(['is_default' => false]);
            }

            $address = $user->addresses()->create(array_merge(
                $request->validated(), 
                ['is_default' => $isDefault]
            ));

            DB::commit();
            return $this->successResponse($address->load('area'), __('messages.address_added_successfully'), 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse(__('messages.error_occurred'), 500);
        }
    }

    // تعديل عنوان
    public function update(CustomerAddressRequest $request, $id)
    {
        $user = auth()->user();
        $address = $user->addresses()->findOrFail($id);

        $isDefault = $request->is_default ?? false;

        DB::beginTransaction();
        try {
            if ($isDefault && !$address->is_default) {
                $user->addresses()->update(['is_default' => false]);
            }

            // لا نسمح بإلغاء الافتراضي إذا كان هو العنوان الافتراضي الوحيد
            if (!$isDefault && $address->is_default && $user->addresses()->count() > 1) {
                 return $this->errorResponse(__('messages.cannot_remove_default'), 400);
            }

            $address->update(array_merge(
                $request->validated(), 
                ['is_default' => $isDefault]
            ));

            DB::commit();
            return $this->successResponse($address->load('area'), __('messages.address_updated_successfully'));
            
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse(__('messages.error_occurred'), 500);
        }
    }

    // حذف عنوان
    public function destroy($id)
    {
        $user = auth()->user();
        $address = $user->addresses()->findOrFail($id);

        if ($address->is_default && $user->addresses()->count() > 1) {
            return $this->errorResponse(__('messages.cannot_delete_default_address'), 400);
        }
        $address->delete();
       
        return $this->successResponse(null, __('messages.address_deleted_successfully'));
    }

    // تعيين كعنوان افتراضي (Endpoint منفصل لزر "Set as default")
    public function setDefault($id)
    {
        $user = auth()->user();
        $address = $user->addresses()->findOrFail($id);

        DB::beginTransaction();
        try {
            $user->addresses()->update(['is_default' => false]);
            $address->update(['is_default' => true]);

            DB::commit();
            return $this->successResponse(null, __('messages.address_set_as_default'));
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse(__('messages.error_occurred'), 500);
        }
    }


}
