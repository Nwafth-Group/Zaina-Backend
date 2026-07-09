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
        $rules = [
            'dates' => ['required', 'array'],
            'start_time' => ['required', 'date_format:H:i'],
        ];

        if (str_contains($this->path(), 'api/v1/admin')) {
            $rules['dates.*'] = ['required', 'date_format:Y-m-d'];
        } else {
            $rules['dates.*'] = ['required', 'date_format:Y-m-d', 'after_or_equal:today'];
            
            if (!str_ends_with($this->path(), 'send-otp')) {
                $rules['otp'] = ['required', 'string', 'size:6'];
            }
        }

        return $rules;
    }
}
