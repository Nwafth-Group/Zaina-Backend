<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRequest extends FormRequest
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
        return [
            'first_name'   => 'required|string|max:100',
            'last_name'    => 'required|string|max:100',
            'phone_number' => 'required|string|unique:users,phone_number',
            'email'        => 'required|email|unique:users,email',
            'password'     => 'required|string', // مطلوب عند إنشاء موظف لوحة تحكم
            'type'         => 'required|in:operation,super_admin,driver', // استثناء customer
            'job_title'    => 'nullable|string|max:100',
            'gender'       => 'nullable|in:Male,Female',
            'status'       => 'required|in:active,inactive,suspended',
            'roles'        => 'required|array|min:1', // BR-02: يجب إعطاء دور واحد على الأقل
            'roles.*'      => 'exists:roles,name', // التأكد أن الدور موجود في قاعدة البيانات
            'image'        => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ];
    }

   public function messages(): array
    {
        return [
            // First Name
            'first_name.required'   => __('validation.storeEmployee.first_name_required'),
            'first_name.string'     => __('validation.storeEmployee.first_name_string'),
            'first_name.max'        => __('validation.storeEmployee.first_name_max'),

            // Last Name
            'last_name.required'    => __('validation.storeEmployee.last_name_required'),
            'last_name.string'      => __('validation.storeEmployee.last_name_string'),
            'last_name.max'         => __('validation.storeEmployee.last_name_max'),

            // Phone Number
            'phone_number.required' => __('validation.storeEmployee.phone_number_required'),
            'phone_number.unique'   => __('validation.storeEmployee.phone_already_exists'),

            // Email
            'email.required'        => __('validation.storeEmployee.email_required'),
            'email.email'           => __('validation.storeEmployee.email_invalid'),
            'email.unique'          => __('validation.storeEmployee.email_already_exists'),

            // Password
            'password.required'     => __('validation.storeEmployee.password_required'),
            'password.min'          => __('validation.storeEmployee.password_min'),

            // Type
            'type.required'         => __('validation.storeEmployee.type_required'),
            'type.in'               => __('validation.storeEmployee.type_invalid'),

            // Job Title
            'job_title.max'         => __('validation.storeEmployee.job_title_max'),

            // Gender
            'gender.in'             => __('validation.storeEmployee.gender_invalid'),

            // Status
            'status.required'       => __('validation.storeEmployee.status_required'),
            'status.in'             => __('validation.storeEmployee.status_invalid'),

            // Roles
            'roles.required'        => __('validation.storeEmployee.roles_required'),
            'roles.array'           => __('validation.storeEmployee.roles_array'),
            'roles.min'             => __('validation.storeEmployee.roles_min'),
            'roles.*.exists'        => __('validation.storeEmployee.roles_exists'),
        ];
    }
}
