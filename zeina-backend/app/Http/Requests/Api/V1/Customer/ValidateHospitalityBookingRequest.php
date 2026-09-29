<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Http\Requests\Api\V1\CustomFormRequest;

class ValidateHospitalityBookingRequest extends CustomFormRequest
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
     */
    public function rules(): array
    {
        return [
            'address_id' => 'required|exists:customer_addresses,id',
            'dates' => 'required|array|size:1',
            'dates.*' => 'required|date_format:Y-m-d|after_or_equal:today',
            'start_time' => 'required|date_format:H:i',
            'duration' => 'required|integer',
            'worker_gender' => 'required|string|in:Male,Female',
            'workers_count' => 'required|integer|min:1',
            'coupon_code' => 'nullable|string',
        ];
    }
}
