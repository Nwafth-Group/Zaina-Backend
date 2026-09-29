<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Http\Requests\Api\V1\CustomFormRequest;

class UpdateDriverRequest extends CustomFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('driver') ?? $this->route('id');
        return [
            'name'   => ['required', 'string', 'max:255'],
            'mobile' => ['required', 'string', 'max:30', 'unique:drivers,mobile,' . $id],
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
