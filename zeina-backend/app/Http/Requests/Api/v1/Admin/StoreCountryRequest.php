<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Http\Requests\Api\V1\CustomFormRequest;
use Illuminate\Foundation\Http\FormRequest;

class StoreCountryRequest extends CustomFormRequest
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
            'name_ar' => ['required', 'string', 'max:100'],
            'name_en' => ['required', 'string', 'max:100'],
            'currency_code' => ['nullable', 'string', 'max:10'],
            'phone_code' => ['nullable', 'string', 'max:10'],
            'code'    => ['required', 'string', 'max:20', 'unique:countries,code'], // الكود يجب أن يكون جديداً كلياً
            'status'  => ['nullable', 'in:active,inactive'],
        ];
       
    }

    public function messages()
    {
        return [
            'name_ar.required' => __('validation.store_country.name_ar_required'),
            'name_ar.string' => __('validation.store_country.name_ar_string'),
            'name_ar.max' => __('validation.store_country.name_ar_max'),
            'name_en.required' => __('validation.store_country.name_en_required'),
            'name_en.string' => __('validation.store_country.name_en_string'),
            'name_en.max' => __('validation.store_country.name_en_max'),
            'code.required' => __('validation.store_country.code_required'),
            'code.string' => __('validation.store_country.code_string'),
            'code.max' => __('validation.store_country.code_max'),
            'code.unique' => __('validation.store_country.code_unique'),
            // 'status.required' => __('validation.store_country.status_required'),
            'status.in' => __('validation.store_country.status_in'),
            'currency_code.string' => __('validation.store_country.currency_code_string'),
            'currency_code.max' => __('validation.store_country.currency_code_max'),
            'phone_code.string' => __('validation.store_country.phone_code_string'),
            'phone_code.max' => __('validation.store_country.phone_code_max'),
        ];
    }
}
