<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Http\Requests\Api\V1\CustomFormRequest;
use Illuminate\Foundation\Http\FormRequest;

class SendOtpRequest extends CustomFormRequest
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
            'mobile_number' => ['required' ,'string' , 'max:15']
        ];
    }

    public function messages(): array {
        return [
            'mobile_number.required' => __('validation.sendotp.mobile_number_required'),
           
        ];
    }
}
