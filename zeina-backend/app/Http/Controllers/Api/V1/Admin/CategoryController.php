<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\v1\Admin\StoreCategoryRequest;
use App\Http\Requests\Api\v1\Admin\UpdateCategoryRequest;
use App\Http\Resources\Api\V1\Category\CategoryResourse;
use App\Models\Category;
use App\Service\MediaService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class CategoryController extends Controller
{ 
    use ApiResponseTrait;

    public function __construct(protected MediaService $mediaService){
     }
    /**
     * Display a listing of the resource.
     */
   public function index()
    { 
       
        // عرض الأقسام مع عدد الخدمات المرتبطة بها (لإظهار الـ Impact Summary لاحقاً)
        $categories = Category::withCount('services')->get();
        return $this->successResponse(CategoryResourse::collection($categories),__('messages.categories_retrieved'));
    }

    public function store(StoreCategoryRequest $request)
    {
        $data = $request->validated();
        $category = Category::create($data);
        

         if ($request->hasFile('image')) {
                // حذف الصورة القديمة إذا كانت موجودة
                // if ($category->media()->where('collection', 'customer/profile_photo')->exists()) {
                //     $oldMedia = $category->media()->where('collection','customer/profile_photo')->first();
                //     $this->mediaService->delete($oldMedia);
                // }
                $media = $this->mediaService->upload($request->file('image'), $category, 'category/icon','public');
            }

        return $this->successResponse(new CategoryResourse($category), __('messages.category_created'));
    }

    public function update(UpdateCategoryRequest $request, Category $category)
    {
       
        $data = $request->validated();
    
            if($request->hasFile('image')) {
                if ($category->media()->where('collection', 'category/icon')->exists()) {
                    $oldMedia = $category->media()->where('collection','category/icon')->first();
                    $this->mediaService->delete($oldMedia);
                }
                $media = $this->mediaService->upload($request->file('image'), $category, 'category/icon','public');
            }

        $category->update($data);
        return $this->successResponse(new CategoryResourse($category), __('messages.category_updated'));

    }

    /**
     * Deactivate / Safe Delete (Scenario 2)
     */
    public function destroy(Category $category, Request $request)
    {
        $servicesCount = $category->services()->count();
         
       
        // 1. إذا كان الريكويست فقط للتحقق من التأثير (Impact Summary)
        if ($request->has('check_impact')) {
            return $this->successResponse([
                 'linked_services_count' => $servicesCount,
                 'message' => $servicesCount > 0 ? "This category has {$servicesCount} linked services. Deactivating it requires a policy decision." : "Safe to deactivate."
            ], 'Impact summary retrieved successfully.');
        }

        // 2. إذا كان هناك خدمات مرتبطة، والأدمن لم يحدد سياسة التعامل (Cascade or Deactivate Only)
        if ($servicesCount > 0 && !$request->has('policy')) {
            return response()->json([
                'error' => 'Action required',
                'message' => "Category has {$servicesCount} active services. Please specify a policy: 'deactivate_only' or 'cascade_hide'."
            ], 400);
        }

        // 3. تطبيق السياسة المختارة
        if ($request->policy === 'cascade_hide') {
            $category->services()->update(['status' => 'inactive']); // إخفاء كل الخدمات
        }

        $category->status = 'inactive';
        $category->save(); // نغير الحالة بدلاً من الحذف النهائي (Safe Deactivate)
        $category->delete(); // Soft Delete
       
        return $this->successResponse(null, __('messages.category_deactivated'));
        
    }
}
