<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCountryRequest extends FormRequest
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
        // سحب الدولة التي يتم التعديل عليها من الرابط (Route)
        $country = $this->route('country');
        $countryId = $country ? (is_object($country) ? $country->id : $country) : null;

        return [
            'name_ar' => ['required', 'string', 'max:100'],
            'name_en' => ['required', 'string', 'max:100'],
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('countries', 'code')->ignore($countryId),
            ],
            'status' => ['nullable', 'in:active,inactive'],
            'currency_code' => ['nullable', 'string', 'max:10'],
            'phone_code' => ['nullable', 'string', 'max:10'],
            'flag' => ['nullable', 'image', 'mimes:jpeg,png,jpg,svg,webp', 'max:1024'],
        ];
    }
}
