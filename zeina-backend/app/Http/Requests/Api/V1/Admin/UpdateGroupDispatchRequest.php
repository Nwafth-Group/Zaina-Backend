<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Http\Requests\Api\V1\CustomFormRequest;

class UpdateGroupDispatchRequest extends CustomFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'departure_point_id' => ['required', 'exists:departure_points,id'],
            'departure_time'     => ['required', 'string'],
            'driver_id'          => ['required', 'exists:drivers,id'],
            'vehicle_type'       => ['nullable', 'string', 'max:255'],
            'vehicle_plate'      => ['nullable', 'string', 'max:50'],
            'vehicle_capacity'   => ['nullable', 'integer', 'min:1'],
            'notes'              => ['nullable', 'string'],
            'booking_ids'        => ['nullable', 'array'],
            'booking_ids.*'      => ['exists:bookings,id'],
        ];
    }
}
