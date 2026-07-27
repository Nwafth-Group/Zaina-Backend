<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreServiceZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'                      => ['required', 'string', 'max:255'],
            'governorate'               => ['nullable', 'string', 'max:255'],
            'city'                      => ['nullable', 'string', 'max:255'],
            'polygon_coordinates'       => ['required', 'array', 'min:3'],
            'polygon_coordinates.*.lat' => ['required', 'numeric'],
            'polygon_coordinates.*.lng' => ['required', 'numeric'],
            'status'                    => ['required', 'in:active,inactive'],
            'service_ids'               => ['nullable', 'array'],
            'service_ids.*'             => ['exists:services,id'],
        ];
    }
}
