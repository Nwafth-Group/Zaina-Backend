<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Http\Requests\Api\V1\CustomFormRequest;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends CustomFormRequest
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
           'phone_number' => ['required','string','min:9','max:15']
        ];
    }
    public function messages(): array
    {
        return [
            'phone_number.required' => __('validation.login.phone_required'),
            'phone_number.string'   => __('validation.login.phone_string'),
            'phone_number.min'      => __('validation.login.phone_min'),
            'phone_number.max'      => __('validation.login.phone_max'),
        ];
    }
}
