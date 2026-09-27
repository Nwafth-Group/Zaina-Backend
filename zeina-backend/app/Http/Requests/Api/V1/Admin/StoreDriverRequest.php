<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreDriverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'   => ['required', 'string', 'max:255'],
            'mobile' => ['required', 'string', 'max:30', 'unique:drivers,mobile'],
            'email'  => ['nullable', 'email', 'max:255'],
            'gender'           => ['required', 'in:male,female'],
            'vehicle_type'     => ['nullable', 'string', 'max:255'],
            'vehicle_plate'    => ['nullable', 'string', 'max:50'],
            'vehicle_capacity' => ['nullable', 'integer', 'min:1', 'max:50'],
            'status'           => ['required', 'in:active,inactive'],
            'image'            => ['nullable', 'image', 'max:5120'],
        ];
    }
}
