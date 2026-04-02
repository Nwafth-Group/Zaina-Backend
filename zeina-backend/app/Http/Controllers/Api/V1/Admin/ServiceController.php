<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreServiceRequest;
use App\Http\Resources\Api\V1\Service\ServiceResource;
use App\Models\Service;
use App\Service\MediaService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ServiceController extends Controller
{
    use ApiResponseTrait;
    /**
     * Display a listing of the resource.
     */

    public function __construct(public MediaService $mediaService)
    {
    }


   public function index()
    {

        $services = Service::with(['category', 'duration', 'rules', 'coverages', 'coverages.city', 'coverages.area'])->get();
        return $this->successResponse(ServiceResource::collection($services),__('messages.services_retrieved_successfully'));
        return response()->json(['data' => $services]);
    }

    public function store(StoreServiceRequest $request)
    {
        try {

            // نستخدم الترانزكشن عشان لو ضرب إيرور في الـ Rules، ما يحفظ الـ Service ويكون ناقص
            $service = DB::transaction(function () use ($request) {
                
                // 1. حفظ الخدمة الأساسية
                $service = Service::create($request->only([
                    'category_id', 'type', 'name_ar', 'name_en', 
                    'description_ar', 'description_en', 'status'
                ]));

                // 2. حفظ المدد الزمنية
                $service->duration()->create($request->input('duration'));

                // 3. حفظ القواعد (Rules)
                $service->rules()->create($request->input('rules'));

                // 4. حفظ التغطية الجغرافية (Coverages)
                // coverages عبارة عن مصفوفة فيها أكثر من مدينة/منطقة
                $service->coverages()->createMany($request->input('coverages'));
                 if ($request->hasFile('image')) {
                        $media = $this->mediaService->upload($request->file('image'), $service, 'service/icon', 'public');
                    }

                return $service;
            });
            // إرجاع الخدمة مع كل تفاصيلها بعد الحفظ
              
            return $this->successResponse(new ServiceResource($service->load(['category', 'duration', 'rules', 'coverages', 'coverages.city', 'coverages.area'])),__('messages.service_created_successfully'));

        } catch (\Exception $e) {
              Log::error('Error creating service: ' . $e->getMessage());
            return $this->errorResponse(__('messages.failed_to_create_service' ,['error' => $e->getMessage()]));
    }
    }

    // دالة لتغيير حالة الخدمة (Deactivate safely)
    public function toggleStatus(Service $service, Request $request)
    {
        // Scenario 5: Deactivate service safely
        $service->status = $service->status === 'active' ? 'inactive' : 'active';
        $service->save();

        return $this->successResponse(null,__('messages.service_toggled_successfully', ['status' => $service->status]));
    }
}
