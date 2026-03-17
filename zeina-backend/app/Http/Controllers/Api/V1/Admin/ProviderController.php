<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\MapProviderRequest;
use App\Http\Requests\Api\V1\Admin\StoreProviderRequest;
use App\Http\Resources\Api\V1\Provider\ProviderResource;
use App\Models\Provider;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProviderController extends Controller
{
    use ApiResponseTrait;
    /**
     * Display a listing of the resource.
     */
   public function index(Request $request)
    { 
        $query = Provider::query();
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('phone_number', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        return $this->successResponse(ProviderResource::collection($query->get()),__('messages.providers_fetch_success'));
       
    }

    /**
     * US-01: Create / Update Profile
     */
    public function store(StoreProviderRequest $request)
    {  
        $provider = Provider::create($request->validated());
        return $this->successResponse(new ProviderResource($provider),__('messages.provider_saved_suuccess'),201);
    }

    public function update(StoreProviderRequest $request, Provider $provider)
    {
        $provider->update($request->validated());
        return $this->successResponse(new ProviderResource($provider),__('messages.provider_update_suuccess'));
    }

    /**
     * US-01: Activate / Deactivate (Scenario 4)
     */
    public function toggleStatus(Provider $provider)
    {
        $provider->status = $provider->status === 'active' ? 'inactive' : 'active';
        $provider->save();

        $msg = $provider->status === 'inactive' ? __('messages.provider_deactivated') : __('messages.provider_activated');
        return $this->successResponse(null,$msg);
     
    }

    /**
     * US-02: Map Provider to Services & Coverages
     */
    public function mapProvider(MapProviderRequest $request, Provider $provider)
    { 
      
        if ($provider->status === 'inactive') {
            return $this->errorResponse(__('messages.provider_not_active'),202);
            }
        try {
            DB::transaction(function () use ($request, $provider) {
                // 1. مزامنة الخدمات (يمسح القديم ويضيف الجديد)
                $provider->services()->sync($request->service_ids);

                // 2. تحديث التغطية الجغرافية
                $provider->areas()->delete(); // مسح التغطية القديمة
                
                $coveragesData = array_map(function($coverage) {
                    return [
                        'city_id' => $coverage['city_id'],
                        'area_id' => $coverage['area_id'] ?? null,
                        'created_by' => auth()->id(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }, $request->coverages);

                $provider->areas()->createMany($coveragesData);
            });

            return $this->successResponse(new ProviderResource($provider->load(['services', 'areas'])),__('messages.provider_and_coverge_success'));

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to map provider');
            // return response()->json(['message' => 'Failed to map provider', 'error' => $e->getMessage()], 500);
        }
    }
}
