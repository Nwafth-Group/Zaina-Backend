<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Contracts\Validation\Validator;
use App\Http\Requests\Api\V1\CustomFormRequest;
use Illuminate\Support\Facades\Hash;

class UpdatePasswordRequest extends CustomFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    /**
     * The current password must match the authenticated user's password.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $user = $this->user();
            if ($user && ! Hash::check((string) $this->input('current_password'), $user->password)) {
                $validator->errors()->add('current_password', __('messages.current_password_incorrect'));
            }
        });
    }
}
