<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreCategoryRequest;
use App\Http\Requests\Api\V1\Admin\UpdateCategoryRequest;
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
        // التعديل: جلب القسم مع أبيه (إن وجد) ومعرفة عدد الأقسام الفرعية والخدمات
        $categories = Category::with('parent','media')
            ->withCount(['services', 'subcategories'])
            ->get();
            
        return $this->successResponse(CategoryResourse::collection($categories), __('messages.categories_retrieved'));
    }

    public function store(StoreCategoryRequest $request)
    {
        $data = $request->validated();
        $category = Category::create($data);

        if ($request->hasFile('image')) {
            $media = $this->mediaService->upload($request->file('image'), $category, 'category/icon', 'public');
        }

        return $this->successResponse(new CategoryResourse($category->load('parent')), __('messages.category_created'));
    }

    public function update(UpdateCategoryRequest $request, Category $category)
    {
        $data = $request->validated();
    
        if($request->hasFile('image')) {
            if ($category->media()->where('collection', 'category/icon')->exists()) {
                $oldMedia = $category->media()->where('collection','category/icon')->first();
                $this->mediaService->delete($oldMedia);
            }
            $media = $this->mediaService->upload($request->file('image'), $category, 'category/icon', 'public');
        }

        $category->update($data);
        return $this->successResponse(new CategoryResourse($category->load('parent')), __('messages.category_updated'));
    }

    /**
     * Deactivate / Safe Delete (Scenario 2)
     */
    public function destroy(Category $category, Request $request)
    {
        // التعديل: فحصنا الخدمات المربوطة + الأقسام الفرعية المربوطة
        $servicesCount = $category->services()->count();
        $subcategoriesCount = $category->subcategories()->count();
        $totalImpact = $servicesCount + $subcategoriesCount;
         
        // 1. إذا كان الريكويست فقط للتحقق من التأثير (Impact Summary)
        if ($request->has('check_impact')) {
            return $this->successResponse([
                 'linked_services_count' => $servicesCount,
                 'linked_subcategories_count' => $subcategoriesCount,
                 'message' => $totalImpact > 0 
                    ? "This category has {$subcategoriesCount} subcategories and {$servicesCount} linked services. Deactivating it requires a policy decision." 
                    : "Safe to deactivate."
            ], 'Impact summary retrieved successfully.');
        }

        // 2. إذا كان هناك تأثير، والأدمن لم يحدد سياسة
        if ($totalImpact > 0 && !$request->has('policy')) {
            return response()->json([
                'error' => 'Action required',
                'message' => "Category has {$subcategoriesCount} active subcategories and {$servicesCount} active services. Please specify a policy: 'deactivate_only' or 'cascade_hide'."
            ], 400);
        }

        // 3. تطبيق السياسة المختارة
        if ($request->policy === 'cascade_hide') {
            $category->services()->update(['status' => 'inactive']); // إخفاء الخدمات
            $category->subcategories()->update(['status' => 'inactive']); // إخفاء الأقسام الفرعية أيضاً
        }

        $category->status = 'inactive';
        $category->save(); 
        $category->delete(); 
       
        return $this->successResponse(null, __('messages.category_deactivated'));
    }
}