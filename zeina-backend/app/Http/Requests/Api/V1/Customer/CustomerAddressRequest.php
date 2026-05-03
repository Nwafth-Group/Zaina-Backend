<?php

namespace App\Http\Requests\Api\V1\Customer;

use Illuminate\Foundation\Http\FormRequest;

class CustomerAddressRequest extends FormRequest
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
            'area_id'         => 'required|exists:areas,id',
            'latitude'        => 'required|numeric',
            'longitude'       => 'required|numeric',
            'label'           => 'nullable|string|max:50',
            'street'          => 'required|string|max:255',
            'building_name'   => 'nullable|string|max:255',
            'building_no'     => 'nullable|string|max:50',
            'floor'           => 'nullable|string|max:50',
            'apartment_no'    => 'nullable|string|max:50',
            'contact_name'    => 'required|string|max:255',
            'phone_number'    => 'required|string|max:20',
            'secondary_phone' => 'nullable|string|max:20',
            'notes'           => 'nullable|string',
            'is_default'      => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'area_id.required'      => __('validation.address.area_required'),
            'area_id.exists'        => __('validation.address.area_invalid'),
            'latitude.required'     => __('validation.address.location_required'),
            'longitude.required'    => __('validation.address.location_required'),
            'street.required'       => __('validation.address.street_required'),
            'contact_name.required' => __('validation.address.contact_name_required'),
            'phone_number.required' => __('validation.address.phone_required'),
        ];
    }
}
