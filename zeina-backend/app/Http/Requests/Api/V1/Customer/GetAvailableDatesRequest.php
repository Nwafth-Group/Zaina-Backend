<?php

namespace App\Http\Requests\Api\V1\Customer;

use Illuminate\Foundation\Http\FormRequest;

class GetAvailableDatesRequest extends FormRequest
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
            'service_id' => 'required|exists:services,id',
            'address_id' => 'required|exists:customer_addresses,id',
            'start_time' => 'required|date_format:H:i',
            'duration' => 'required|integer',
            'worker_gender' => 'required|string|in:Male,Female',
            'workers_count' => 'required|integer|min:1',
        ];
    }
}
