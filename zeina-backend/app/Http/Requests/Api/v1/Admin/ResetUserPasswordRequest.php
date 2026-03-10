<?php

namespace App\Http\Requests\Api\v1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ResetUserPasswordRequest extends FormRequest
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
                'password' => 'required|string|min:8|confirmed'
        ];
    }

        public function messages(){
            return [
                'password.required' => __('validation.reset_password.password_required'),
                'password.string' => __('validation.reset_password.password_string'),
                'password.min' => __('validation.reset_password.password_min'),
                'password.confirmed' => __('validation.reset_password.password_confirmed'),
            ];
        }
}
