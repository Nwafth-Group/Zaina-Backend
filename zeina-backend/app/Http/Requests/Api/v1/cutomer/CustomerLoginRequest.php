<?php

namespace App\Http\Requests\Api\v1\cutomer;

use App\Http\Requests\Api\V1\CustomFormRequest;
use Illuminate\Foundation\Http\FormRequest;

class CustomerLoginRequest extends CustomFormRequest
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
            'mobile_number' => [
                'required',
                'numeric',
                'exists:users,phone_number'
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'mobile_number.required' => __('validation.login.mobile_number_required'),
            'mobile_number.numeric' => __('validation.login.mobile_number_numeric'),
            'mobile_number.exists' => __('validation.login.invalid_mobile'),
        ];
    }
}
