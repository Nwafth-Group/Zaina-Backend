<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreServiceRequest;
use App\Http\Requests\Api\V1\Admin\UpdateServiceRequest;
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
        DB::beginTransaction();
        try {
            // 1. حفظ البيانات الأساسية للخدمة
            $service = Service::create([
                'category_id'    => $request->category_id,
                'type'           => $request->type,
                'name_ar'        => $request->name_ar,
                'name_en'        => $request->name_en,
                'description_ar' => $request->description_ar,
                'description_en' => $request->description_en,
                'status'         => $request->status,
                'features'       => $request->features, // Laravel سيقوم بتحويلها لـ JSON تلقائياً لوجود الـ Cast
            ]);

            // 2. حفظ المدد الزمنية (Durations)
            if ($request->has('durations')) {
                $service->duration()->create($request->durations);
            }

            if ($request->has('workers')) {
                $service->workers()->sync($request->workers);
            }

            // 3. حفظ قواعد الخدمة (Rules)
            if ($request->has('rules')) {
                $service->rules()->create($request->rules);
            }

            // 4. حفظ التغطية الجغرافية (Coverages)
            if ($request->has('coverages')) {
                $service->coverages()->createMany($request->coverages);
            }

            // 5. حفظ التسعيرات (Prices)
           if ($request->has('prices')) {
            $pricesData = [];
            
            foreach ($request->prices as $price) {
                if ($price['pricing_scope'] === 'area' && isset($price['area_id'])) {
                    // بنجيب المدينة تلقائياً من المنطقة عشان نمنع أي تلاعب
                    $area = \App\Models\Area::find($price['area_id']);
                    $price['city_id'] = $area ? $area->city_id : null; 
                }
                $pricesData[] = $price;
             }

            $service->priceRules()->createMany($pricesData);
             }

            // 6. رفع الصور باستخدام MediaService الخاص بك
            if ($request->hasFile('image')) {
                $this->mediaService->upload($request->file('image'), $service, 'service/icon', 'public');
            }

            // if ($request->hasFile('cover')) {
            //     $this->mediaService->upload($request->file('cover'), $service, 'service/cover', 'public');
            // }

            DB::commit();
             
            return $this->successResponse(new ServiceResource($service->load(['duration', 'rules', 'coverages', 'priceRules', 'media'])),__('messages.service_created_successfully'),201);
            // return response()->json([
            //     'success' => true,
            //     'message' => __('messages.service_created_successfully'),
            //     'data'    => $service->load(['duration', 'rules', 'coverages', 'priceRules', 'media'])
            // ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => __('messages.error_occurred'),
                'error'   => $e->getMessage() // يمكن إخفاؤها في بيئة الإنتاج
            ], 500);
        }
    }

    public function update(UpdateServiceRequest $request, $id)
    { 
        $service = Service::findOrFail($id);
        DB::beginTransaction();
        try {
            // 1. تحديث البيانات الأساسية
            $service->update($request->only([
                'category_id', 'type', 'name_ar', 'name_en', 
                'description_ar', 'description_en', 'status', 'features'
            ]));

            // 2. تحديث المدد الزمنية (updateOrCreate عشان لو ما كانت موجودة قبل هيك تتكَرْت)
            if ($request->has('durations')) {
                $service->duration()->updateOrCreate(
                    ['service_id' => $service->id],
                    $request->durations
                );
            }

            // 3. تحديث قواعد الخدمة
            if ($request->has('rules')) {
                $service->rules()->updateOrCreate(
                    ['service_id' => $service->id],
                    $request->rules
                );
            }

            // 4. تحديث التغطية الجغرافية (الأسلوب الأفضل هو مسح القديم وإدخال الجديد)
            if ($request->has('coverages')) {
                $service->coverages()->delete();
                $service->coverages()->createMany($request->coverages);
            }

            // 5. تحديث التسعيرات
            if ($request->has('prices')) {
                $service->priceRules()->delete();
                $service->priceRules()->createMany($request->prices);
            }

             // تحديث العمال المرتبطين بالخدمة
             if ($request->has('workers')) {
                $service->workers()->sync($request->workers);
            }
            // 6. تحديث الصور (أيقونة وغلاف)
            if ($request->hasFile('image')) {
                // البحث عن الأيقونة القديمة وحذفها
                $oldIcon = $service->media()->where('collection', 'service/icon')->first();
                if ($oldIcon) {
                    $this->mediaService->delete($oldIcon);
                }
                // رفع الجديدة
                $this->mediaService->upload($request->file('image'), $service, 'service/icon', 'public');
            }
            DB::commit();
            return $this->successResponse(new ServiceResource($service->fresh(['duration', 'rules', 'coverages', 'priceRules', 'media'])),__('messages.service_updated_successfully'));

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => __('messages.error_occurred'),
                'error'   => $e->getMessage()
            ], 500);
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
