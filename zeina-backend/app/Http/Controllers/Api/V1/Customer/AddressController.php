<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\CustomerAddressRequest;
use App\Http\Resources\Api\V1\Address\AddressResource;
use App\Models\ServiceZone;
use App\Service\Admin\ServiceZoneService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller
{
    use ApiResponseTrait;

    public function __construct(public ServiceZoneService $zoneService) {}

    public function index()
    {
        $addresses = auth()->user()->addresses()->with(['area.city', 'serviceZone'])->latest()->get();
        return $this->successResponse(AddressResource::collection($addresses), __('messages.addresses_fetched_successfully'));
    }

    public function store(CustomerAddressRequest $request)
    {
        $user = auth()->user();
        $isFirstAddress = $user->addresses()->count() === 0;
        $isDefault = $isFirstAddress ? true : ($request->is_default ?? false);

        $validated = $request->validated();
        $lat = (float) $validated['latitude'];
        $lng = (float) $validated['longitude'];

        // Automatic spatial Geo-fencing zone lookup
        $matchedZone = $this->zoneService->findZoneForPoint($lat, $lng);
        if ($matchedZone) {
            $validated['service_zone_id'] = $matchedZone->id;
            $validated['governorate'] = $validated['governorate'] ?? $matchedZone->governorate;
            $validated['city'] = $validated['city'] ?? $matchedZone->city;
            $validated['serviceable_status'] = 'serviceable';
        } else {
            $validated['service_zone_id'] = null;
            $validated['serviceable_status'] = 'not_serviceable';
        }

        DB::beginTransaction();
        try {
            if ($isDefault) {
                $user->addresses()->update(['is_default' => false]);
            }

            $address = $user->addresses()->create(array_merge(
                $validated, 
                ['is_default' => $isDefault]
            ));

            DB::commit();
            $address->load(['area', 'serviceZone']);
            return $this->successResponse(new AddressResource($address), __('messages.address_added_successfully'), 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            \Illuminate\Support\Facades\Log::error('Address Store Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $msg = config('app.debug') ? $e->getMessage() : __('messages.error_occurred');
            return $this->errorResponse($msg, 500);
        }
    }

    public function update(CustomerAddressRequest $request, $id)
    {
        $user = auth()->user();
        $address = $user->addresses()->findOrFail($id);
        $isDefault = $request->is_default ?? false;

        $validated = $request->validated();
        $lat = (float) $validated['latitude'];
        $lng = (float) $validated['longitude'];

        $matchedZone = $this->zoneService->findZoneForPoint($lat, $lng);
        if ($matchedZone) {
            $validated['service_zone_id'] = $matchedZone->id;
            $validated['governorate'] = $validated['governorate'] ?? $matchedZone->governorate;
            $validated['city'] = $validated['city'] ?? $matchedZone->city;
            $validated['serviceable_status'] = 'serviceable';
        } else {
            $validated['service_zone_id'] = null;
            $validated['serviceable_status'] = 'not_serviceable';
        }

        DB::beginTransaction();
        try {
            if ($isDefault && !$address->is_default) {
                $user->addresses()->update(['is_default' => false]);
            }

            if (!$isDefault && $address->is_default && $user->addresses()->count() > 1) {
                 return $this->errorResponse(__('messages.cannot_remove_default'), 400);
            }

            $address->update(array_merge(
                $validated, 
                ['is_default' => $isDefault]
            ));

            DB::commit();
            $address->load(['area', 'serviceZone']);
            return $this->successResponse(new AddressResource($address), __('messages.address_updated_successfully'));
            
        } catch (\Exception $e) {
            DB::rollBack();
            \Illuminate\Support\Facades\Log::error('Address Update Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $msg = config('app.debug') ? $e->getMessage() : __('messages.error_occurred');
            return $this->errorResponse($msg, 500);
        }
    }

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
