<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Http\Requests\Api\V1\CustomFormRequest;
use Illuminate\Foundation\Http\FormRequest;

class StoreRoleRequest extends CustomFormRequest
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
        $roleId = $this->route('role') ??  null;

        
        return [
            'name'        => ['required', 'string', 'max:100', 'unique:roles,name,' . $roleId],
            'code'        => ['required', 'string', 'max:100', 'unique:roles,code,' . $roleId],
            'description' => ['nullable', 'string', 'max:200'],
            'status'      => ['required', 'in:active,inactive'],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*'=> ['exists:permissions,name'],
        ];
    }

    public function messages(){
        return [
            'name.required'=>__('validation.role.name_required'),
            'name.string'=>__('validation.role.name_string'),
            'name.max'=>__('validation.role.name_max'),
            'name.unique'=>__('validation.role.name_unique'),
            'code.required'=>__('validation.role.code_required'),
            'code.string'=>__('validation.role.code_string'),
            'code.max'=>__('validation.role.code_max'),
            'code.unique'=>__('validation.role.code_unique'),
            'description.string'=>__('validation.role.description_string'),
            'description.max'=>__('validation.role.description_max'),
            'status.required'=>__('validation.role.status_required'),
            'status.in'=>__('validation.role.status_in'),
            'permissions.required'=>__('validation.role.permissions_required'),
            'permissions.array'=>__('validation.role.permissions_array'),
            'permissions.min'=>__('validation.role.permissions_min'),
            'permissions.*.exists'=>__('validation.role.permissions_exists'),
        ];
    }
}
