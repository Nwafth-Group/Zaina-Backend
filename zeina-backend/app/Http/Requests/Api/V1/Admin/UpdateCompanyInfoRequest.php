<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyInfoRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'name_ar' => 'required|string|max:255',
            'name_en' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'website' => 'nullable|url|max:255',
            'facebook_link' => 'nullable|url|max:255',
            'instagram_link' => 'nullable|url|max:255',
            'twitter_link' => 'nullable|url|max:255',
            'snapchat_link' => 'nullable|url|max:255',
            'tiktok_link' => 'nullable|url|max:255',
            'linkedin_link' => 'nullable|url|max:255',
            'google_play_link' => 'nullable|url|max:255',
            'app_store_link' => 'nullable|url|max:255',
            'vat_percentage' => 'required|numeric|min:0|max:100',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,svg|max:2048',
        ];
    }
}
