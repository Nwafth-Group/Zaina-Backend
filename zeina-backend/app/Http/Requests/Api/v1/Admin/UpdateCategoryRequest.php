<?php

namespace App\Http\Requests\Api\v1\Admin;

use App\Http\Requests\Api\V1\CustomFormRequest;

use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends CustomFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $category = $this->route('category');
        $categoryId = $category ? (is_object($category) ? $category->id : $category) : null;

        return [
            'name_ar' => ['required', 'string', 'max:100', Rule::unique('categories', 'name_ar')->ignore($categoryId)],
            'name_en' => ['required', 'string', 'max:100', Rule::unique('categories', 'name_en')->ignore($categoryId)],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'image'    => ['nullable', 'image', 'mimes:jpeg,png,jpg,svg', 'max:2048'],
            'status'  => ['nullable', 'in:active,inactive'],
        ];
    }

    public function messages()
    {
        return [
            'name_ar.required' => __('validation.create_category.name_ar_required'),
            'name_ar.string' => __('validation.create_category.name_ar_string'),
            'name_ar.max' => __('validation.create_category.name_ar_max'),
            'name_ar.unique' => __('validation.create_category.name_ar_unique'),
            'name_en.required' => __('validation.create_category.name_en_required'),
            'name_en.string' => __('validation.create_category.name_en_string'),
            'name_en.max' => __('validation.create_category.name_en_max'),
            'name_en.unique' => __('validation.create_category.name_en_unique'),
            'image.image' => __('validation.create_category.image_image'),
            'image.mimes' => __('validation.create_category.image_mimes'),
            'image.max' => __('validation.create_category.image_max'),
            'status.in' => __('validation.create_category.status_in'),
        ];
    }
}
