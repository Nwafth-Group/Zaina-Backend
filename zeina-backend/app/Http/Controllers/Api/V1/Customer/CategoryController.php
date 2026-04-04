<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Category\CategoryResourse;
use App\Models\Category;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    use ApiResponseTrait;
   
    public function getMainCategories()
    {
        $categories = Category::whereNull('parent_id')
            ->where('status', 'active')
            ->orderBy('sort_order', 'asc')
            ->paginate(10);

            return $this->successResponse(CategoryResourse::collection($categories),__('messages.categories_retrieved'));
    }

    public function getSubcategories(Category $category)
    {
        $subcategories = Category::where('parent_id', $category->id)
            ->where('status', 'active')
            ->orderBy('sort_order', 'asc')
            ->paginate(10);

        return $this->successResponse(CategoryResourse::collection($subcategories),__('messages.subcategories_retrieved'));  
    }

}
