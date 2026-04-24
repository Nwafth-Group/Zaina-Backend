<?php

namespace App\Http\Requests\Api\V1\Customer;

use Illuminate\Foundation\Http\FormRequest;

class RescheduleBookingRequest extends FormRequest
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
          'new_date'       => 'required|date_format:Y-m-d|after_or_equal:today',
          'new_start_time' => 'required|date_format:H:i',
          'new_end_time'   => 'required|date_format:H:i|after:new_start_time',
        ];
    }
}
