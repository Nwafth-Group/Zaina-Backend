<?php

namespace App\Http\Requests\Api\v1\cutomer;

use App\Http\Requests\Api\V1\CustomFormRequest;
use Illuminate\Foundation\Http\FormRequest;

class CustomerVerifyLoginRequest extends CustomFormRequest
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
            'mobile_number'=>[ 
                'required',
                // 'numeric',
            ],
            'otp'=>['required']
        ];
    }

    public function messages(): array
    {
        return [
            'mobile_number.required' => __('validation.login.mobile_number_required'),
            'mobile_number.numeric' => __('validation.login.mobile_number_numeric'),
            'otp.required' => __('validation.login.otp_required'),
            'otp.numeric' => __('validation.login.otp_numeric'),
        ];
    }
}
