<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UnassignWorkerDispatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'booking_id' => 'required|integer|exists:bookings,id',
            'worker_id' => 'required|integer|exists:workers,id',
            'transport_group_id' => 'nullable|integer|exists:transport_groups,id',
        ];
    }
}
