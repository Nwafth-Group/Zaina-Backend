<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Http\Requests\Api\V1\CustomFormRequest;

class UpdateEmployeeRequest extends CustomFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation()
    {
        $roles = $this->input('roles', []);
        $type = 'operation';
        if (in_array('Super Admin', $roles, true)) {
            $type = 'super_admin';
        } elseif (in_array('Driver', $roles, true)) {
            $type = 'driver';
        }
        $this->merge([
            'type' => $type,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
   public function rules(): array
    {
        $userId = $this->route('employee') ? $this->route('employee')->id : null;
    
        return [
            'first_name'   => 'required|string|max:100',
            'last_name'    => 'required|string|max:100',
            'phone_number' => 'required|string|unique:users,phone_number,' . $userId,
            'email'        => 'required|email|unique:users,email,' . $userId,
            'password'     => 'nullable|string|min:6', // جعله اختياري عند التعديل
            'type'         => 'required|in:operation,super_admin,driver',
            'job_title'    => 'nullable|string|max:100',
            'gender'       => 'nullable|in:Male,Female',
            'status'               => 'required|in:active,inactive,suspended',
            'monthly_points_limit' => 'nullable|integer|min:0',
            'roles'                => 'required|array|min:1',
            'roles.*'      => 'exists:roles,name',
            'image'        => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ];
    }
}
