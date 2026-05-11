<?php

namespace App\Http\Requests\Api\V1\Customer;

use Illuminate\Foundation\Http\FormRequest;

class SubmitVerifyAccountRequest extends FormRequest
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
           'id_front' => 'required|image|mimes:jpeg,png,jpg,webp,jfif|max:5120', // Max 5MB كما في التصميم
           'id_back'  => 'required|image|mimes:jpeg,png,jpg,webp,jfif|max:5120',
           'selfie'   => 'required|image|mimes:jpeg,png,jpg,webp,jfif|max:5120',
        ];
    }

    public function messages(){
        return [
            'id_front.required' => __('validation.valid_account.id_front_required'),
            'id_front.image' => __('validation.valid_account.id_front_image'),
            'id_front.mimes' => __('validation.valid_account.id_front_mimes'),
            'id_front.max' => __('validation.valid_account.id_front_max'),
            'id_back.required' => __('validation.valid_account.id_back_required'),
            'id_back.image' => __('validation.valid_account.id_back_image'),
            'id_back.mimes' => __('validation.valid_account.id_back_mimes'),
            'id_back.max' => __('validation.valid_account.id_back_max'),
            'selfie.required' => __('validation.valid_account.selfie_required'),
            'selfie.image' => __('validation.valid_account.selfie_image'),
            'selfie.mimes' => __('validation.valid_account.selfie_mimes'),
            'selfie.max' => __('validation.valid_account.selfie_max'),
        ];
    }
}
