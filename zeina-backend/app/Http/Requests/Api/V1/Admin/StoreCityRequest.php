<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Http\Requests\Api\V1\CustomFormRequest;
use Illuminate\Validation\Rule;

class StoreCityRequest extends CustomFormRequest
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
        $cityId = $this->route('city') ? $this->route('city')->id : null;

        return [
            'govrenrate_id' => ['required', 'exists:govrenrates,id'],
            'name_ar' => ['required', 'string', 'max:100'],
            'name_en' => ['required', 'string', 'max:100'],
            // الكود يجب أن يكون فريداً داخل نفس المحافظة فقط
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('cities', 'code')->where(function ($query) {
                    return $query->where('govrenrate_id', $this->govrenrate_id);
                })->ignore($cityId),
            ],
            'status' => ['sometimes', 'in:active,inactive'],
        ];
    }

    public function messages()
    {
        return [
            'govrenrate_id.required' => __('validation.store_citry.country_id_required'),
            'govrenrate_id.exists' => __('validation.store_citry.country_id_exists'),
            'name_ar.required' => __('validation.store_citry.name_ar_required'),
            'name_ar.string' => __('validation.store_citry.name_ar_string'),
            'name_ar.max' => __('validation.store_citry.name_ar_max'),
            'name_en.required' => __('validation.store_citry.name_en_required'),
            'name_en.string' => __('validation.store_citry.name_en_string'),
            'name_en.max' => __('validation.store_citry.name_en_max'),
            'code.required' => __('validation.store_citry.code_required'),
            'code.string' => __('validation.store_citry.code_string'),
            'code.max' => __('validation.store_citry.code_max'),
            'code.unique' => __('validation.store_citry.code_unique'),
            'status.required' => __('validation.store_citry.status_required'),
            'status.in' => __('validation.store_citry.status_in'),
        ];
    }
}
