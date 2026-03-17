<?php

namespace App\Http\Requests\Api\V1\Customer;

use Illuminate\Foundation\Http\FormRequest;

class StoreAdderssRequest extends FormRequest
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
            'area_id'      => ['required', 'exists:areas,id'],
            'latitude'     => ['required', 'numeric'],
            'longitude'    => ['required', 'numeric'],
            'label'        => ['required', 'string', 'max:100'],
            'street'       => ['required', 'string', 'max:200'],
            'building'     => ['nullable', 'string', 'max:100'],
            'floor'        => ['nullable', 'string', 'max:50'],
            'unit'         => ['nullable', 'string', 'max:50'],
            'notes'        => ['nullable', 'string', 'max:500'],
            'is_default'   => ['boolean']
        ];
    }

    public function messages(): array
    {
        return [
            'area_id.required' => __('validation.address.area_id_required'),
            'area_id.exists' => __('validation.address.area_id_exists'),
            'latitude.required' => __('validation.address.latitude_required'),
            'latitude.numeric' => __('validation.address.latitude_numeric'),
            'longitude.required' => __('validation.address.longitude_required'),
            'longitude.numeric' => __('validation.address.longitude_numeric'),
            'label.required' => __('validation.address.label_required'),
            'label.string' => __('validation.address.label_string'),
            'label.max' => __('validation.address.label_max'),
            'street.required' => __('validation.address.street_required'),
            'street.string' => __('validation.address.street_string'),
            'street.max' => __('validation.address.street_max'),
            'building.string' => __('validation.address.building_string'),
            'building.max' => __('validation.address.building_max'),
            'floor.string' => __('validation.address.floor_string'),
            'floor.max' => __('validation.address.floor_max'),
            'unit.string' => __('validation.address.unit_string'),
            'unit.max' => __('validation.address.unit_max'),
            'notes.string' => __('validation.address.notes_string'),
            'notes.max' => __('validation.address.notes_max'),
            'is_default.boolean' => __('validation.address.is_default_boolean'),
        ];
    }
}
