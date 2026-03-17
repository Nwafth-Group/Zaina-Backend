<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Http\Requests\Api\V1\CustomFormRequest;
use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends CustomFormRequest
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
        return [
            'name_ar' => ['required', 'string', 'max:100', 'unique:categories,name_ar'],
            'name_en' => ['required', 'string', 'max:100', 'unique:categories,name_en'],
            'image'    => ['nullable', 'image', 'mimes:jpeg,png,jpg,svg', 'max:2048'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
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
            'sort_order.integer' => __('validation.create_category.sort_order_integer'),
            'sort_order.min' => __('validation.create_category.sort_order_min'),
            'status.in' => __('validation.create_category.status_in'),
        ];
    }
}
