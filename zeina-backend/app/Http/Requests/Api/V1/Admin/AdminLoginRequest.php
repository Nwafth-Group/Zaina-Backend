<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Http\Requests\Api\V1\CustomFormRequest;

class AdminLoginRequest extends CustomFormRequest
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
           'email'    => ['required','email','exists:users,email'],
           'password' => ['required','string'] ,
        ];
    }
    public function messages(): array
    {
        return [
            'email.required'  => __('validation.adminLogin.email_required'),
            'email.email'     => __('validation.adminLogin.email_invalid'),
            'email.exists'    => __('validation.adminLogin.email_not_found'),
            'password.required' => __('validation.adminLogin.password_required'),
        ];
    }
}
