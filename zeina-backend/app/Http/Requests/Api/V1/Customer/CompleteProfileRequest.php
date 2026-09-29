<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Http\Requests\Api\V1\CustomFormRequest;

class CompleteProfileRequest extends CustomFormRequest
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
        $userId = auth()->id();
        return [
            'first_name'      => ['required', 'string', 'max:50'],
            'last_name'       => ['required', 'string', 'max:50'],
            'phone_number'    => ['required', 'string', 'max:20', 'unique:users,phone_number,' . $userId],
            'id_number'       => ['nullable', 'string', 'max:50', 'unique:users,id_number,' . $userId],
            // 'phone_code'      => ['required', 'string', 'max:5'],
            // 'code'           => ['required', 'string', 'max:10'],
            'email'           => ['required', 'email', 'max:255', 'unique:users,email,' . $userId],
            'gender'          => ['required', 'in:Male,Female'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:4096'],
            'referral_code'   => ['nullable', 'string', 'exists:users,referral_code'],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required'   => __('validation.complete_profile.first_name_required'),
            'last_name.required'    => __('validation.complete_profile.last_name_required'),
            'phone_number.required' => __('validation.complete_profile.phone_required'),
            'phone_number.unique'   => __('validation.complete_profile.phone_unique'),
            'id_number.unique'      => __('validation.complete_profile.id_number_unique'),
            'id_number.max'         => __('validation.complete_profile.id_number_max'),
            'id_number.string'      => __('validation.complete_profile.id_number_string'),
            // 'id_number.required'    => __('validation.complete_profile.id_number_required'),
            'email.required'        => __('validation.complete_profile.email_required'),
            'email.email'           => __('validation.complete_profile.email_invalid'),
            'email.unique'          => __('validation.complete_profile.email_unique'),
            'gender.required'       => __('validation.complete_profile.gender_required'),
            'gender.in'             => __('validation.complete_profile.gender_invalid'),
            'image.image' => __('validation.complete_profile.image_invalid'),
            'image.mimes' => __('validation.complete_profile.image_mimes'),
            'image.max'   => __('validation.complete_profile.image_max'),
        ];

    }
}
