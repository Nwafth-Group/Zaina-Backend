<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
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
           'status' => 'sometimes|in:active,inactive',
            'roles'  => 'sometimes|array',
            'roles.*'=> 'exists:roles,name',
        ];
    }

     public function messages(){
        return [
            'status.in'=> __('validation.update_user.status_invalid'),
            'roles.array'=> __('validation.update_user.roles_array'),
            'roles.*.exists'=> __('validation.update_user.roles_exists'),
        ];
     }
}
