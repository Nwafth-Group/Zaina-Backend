<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Http\Requests\Api\V1\CustomFormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends CustomFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $category = $this->route('category');
        $categoryId = $category ? (is_object($category) ? $category->id : $category) : null;

        return [
            // تأكدنا إن القسم ما بيقدر يكون أب لنفسه!
           'parent_id' => [
            'nullable', 
            'not_in:' . $categoryId, 
            Rule::exists('categories', 'id')->whereNull('parent_id') // تأكدنا إنه قسم رئيسي
        ],
            'name_ar'    => ['required', 'string', 'max:100', Rule::unique('categories', 'name_ar')->ignore($categoryId)],
            'name_en'    => ['required', 'string', 'max:100', Rule::unique('categories', 'name_en')->ignore($categoryId)],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'image'      => ['nullable', 'image', 'mimes:jpeg,png,jpg,svg,webp', 'max:2048'],
            'status'     => ['nullable', 'in:active,inactive'],
        ];
    }

  public function messages(): array
    {
        return [
            'parent_id.exists'   => __('validation.update_category.parent_id_exists'),
            'parent_id.not_in'   => __('validation.update_category.parent_id_not_in'),
            
            'name_ar.required'   => __('validation.update_category.name_ar_required'),
            'name_ar.string'     => __('validation.update_category.name_ar_string'),
            'name_ar.max'        => __('validation.update_category.name_ar_max'),
            'name_ar.unique'     => __('validation.update_category.name_ar_unique'),
            
            'name_en.required'   => __('validation.update_category.name_en_required'),
            'name_en.string'     => __('validation.update_category.name_en_string'),
            'name_en.max'        => __('validation.update_category.name_en_max'),
            'name_en.unique'     => __('validation.update_category.name_en_unique'),
            
            'image.image'        => __('validation.update_category.image_image'),
            'image.mimes'        => __('validation.update_category.image_mimes'),
            'image.max'          => __('validation.update_category.image_max'),
            
            'sort_order.integer' => __('validation.update_category.sort_order_integer'),
            'sort_order.min'     => __('validation.update_category.sort_order_min'),
            
            'status.in'          => __('validation.update_category.status_in'),
        ];
    }
}