<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AssignWorkerDispatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_date' => 'required|date',
            'start_time' => 'required|string',
            'booking_id' => 'required|integer|exists:bookings,id',
            'worker_id' => 'required|integer|exists:workers,id',
            'driver_id' => 'required|integer|exists:drivers,id',
            'departure_point_id' => 'nullable|integer|exists:departure_points,id',
            'departure_time' => 'required|string',
            'notes' => 'nullable|string|max:500',
        ];
    }
}
