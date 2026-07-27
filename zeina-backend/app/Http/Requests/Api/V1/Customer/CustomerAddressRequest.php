<?php

namespace App\Http\Requests\Api\V1\Customer;

use Illuminate\Foundation\Http\FormRequest;

class CustomerAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_zone_id'   => ['nullable', 'exists:service_zones,id'],
            'latitude'          => ['required', 'numeric'],
            'longitude'         => ['required', 'numeric'],
            'formatted_address' => ['nullable', 'string', 'max:500'],
            'city'              => ['nullable', 'string', 'max:255'],
            'governorate'       => ['nullable', 'string', 'max:255'],
            'country'           => ['nullable', 'string', 'max:255'],
            'label'             => ['nullable', 'string', 'max:50'],
            'street'            => ['nullable', 'string', 'max:255'],
            'building_name'     => ['nullable', 'string', 'max:255'],
            'building_no'       => ['nullable', 'string', 'max:50'],
            'floor'             => ['nullable', 'string', 'max:50'],
            'apartment_no'      => ['nullable', 'string', 'max:50'],
            'contact_name'      => ['required', 'string', 'max:255'],
            'phone_number'      => ['required', 'string', 'max:20'],
            'secondary_phone'   => ['nullable', 'string', 'max:20'],
            'notes'             => ['nullable', 'string'],
            'is_default'        => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'latitude.required'     => __('validation.address.location_required'),
            'longitude.required'    => __('validation.address.location_required'),
            'contact_name.required' => __('validation.address.contact_name_required'),
            'phone_number.required' => __('validation.address.phone_required'),
        ];
    }
}
