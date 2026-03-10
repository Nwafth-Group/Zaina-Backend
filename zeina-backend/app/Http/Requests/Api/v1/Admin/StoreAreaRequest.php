<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreAreaRequest extends FormRequest
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
            'city_id' => ['required', 'exists:cities,id'],
            'name_ar' => ['required', 'string', 'max:100'],
            'name_en' => ['required', 'string', 'max:100'],
            'code'    => ['required', 'string', 'max:20', 'unique:areas,code'], // الكود يجب أن يكون جديداً كلياً
            'status'  => ['nullable', 'in:active,inactive'],
        ];
    }

    public function messages()
    {
        return [
            'city_id.required' => __('validation.store_area.city_id_required'),
            'city_id.exists' => __('validation.store_area.city_id_exists'),
            'name_ar.required' => __('validation.store_area.name_ar_required'),
            'name_ar.string' => __('validation.store_area.name_ar_string'),
            'name_ar.max' => __('validation.store_area.name_ar_max'),
            'name_en.required' => __('validation.store_area.name_en_required'),
            'name_en.string' => __('validation.store_area.name_en_string'),
            'name_en.max' => __('validation.store_area.name_en_max'),
            'code.required' => __('validation.store_area.code_required'),
            'code.string' => __('validation.store_area.code_string'),
            'code.max' => __('validation.store_area.code_max'),
            'code.unique' => __('validation.store_area.code_unique'),
            // 'status.required' => __('validation.store_area.status_required'),
            'status.in' => __('validation.store_area.status_in'),
        ];
    }
}
