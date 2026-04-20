<?php

namespace App\Http\Requests\Api\V1\Customer;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
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
            'service_id' => 'required|exists:services,id',
            'worker_id'  => 'required|exists:workers,id',
            'address_id' => 'required|exists:customer_addresses,id',
            'date'       => 'required|date_format:Y-m-d',
            'start_time' => 'required|date_format:H:i',
            'duration_minutes' => 'required|integer|min:60',
        ];
    }

    public function messages(): array
    {
        return [
            'service_id.required' => __('validation.create_booking.service_id_required'),
            'service_id.exists' => __('validation.create_booking.service_id_exists'),
            'worker_id.required' => __('validation.create_booking.worker_id_required'),
            'worker_id.exists' => __('validation.create_booking.worker_id_exists'),
            'address_id.required' => __('validation.create_booking.address_id_required'),
            'address_id.exists' => __('validation.create_booking.address_id_exists'),
            'date.required' => __('validation.create_booking.date_required'),
            'date.date_format' => __('validation.create_booking.date_date_format'),
            'start_time.required' => __('validation.create_booking.start_time_required'),
            'start_time.date_format' => __('validation.create_booking.start_time_date_format'),
            'duration_minutes.required' => __('validation.create_booking.duration_minutes_required'),
            'duration_minutes.integer' => __('validation.create_booking.duration_minutes_integer'),
            'duration_minutes.min' => __('validation.create_booking.duration_minutes_min'),
        ];
    }
}
