<?php

namespace App\Http\Requests\Api\v1\cutomer;

use Illuminate\Foundation\Http\FormRequest;

class CompleteProfileRequest extends FormRequest
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
            'email'   => 'nullable|email|max:100|unique:users,email,'.auth()->id(),
            'id_number' => 'required|string|max:50|unique:users,id_number,'.auth()->id(),
            'image'   => 'nullable|image|mimes:jpeg,png,jpg|max:2048', // 2MB max
            'gender'  => 'nullable|in:Male,Female',
          
        ];
    }

    public function messages(): array
    {
        return [
            'email.email' => __('validation.profile.email_email'),
            'email.max' => __('validation.profile.email_max'),
            'email.unique' => __('validation.profile.email_unique'),
            'id_number.required' => __('validation.profile.id_number_required'),
            'id_number.string' => __('validation.profile.id_number_string'),
            'id_number.max' => __('validation.profile.id_number_max'),
            'id_number.unique' => __('validation.profile.id_number_unique'),
            'image.image' => __('validation.profile.image_image'),
            'image.mimes' => __('validation.profile.image_mimes'),
            'image.max' => __('validation.profile.image_max'),
            'gender.required' => __('validation.profile.gender_required'),
            'gender.in' => __('validation.profile.gender_in'),
        ];

    }
}
