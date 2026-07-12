<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAdRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title_en'  => ['required', 'string', 'max:255'],
            'title_ar'  => ['required', 'string', 'max:255'],
            'type'      => ['required', 'in:general,coupon'],
            'text_en'   => ['nullable', 'string'],
            'text_ar'   => ['nullable', 'string'],
            'link_type' => ['required', 'in:internal,external,none'],
            'link'      => ['nullable', 'string', 'max:255'],
            'status'    => ['nullable', 'in:active,inactive'],
            'image'     => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
        ];
    }
}
