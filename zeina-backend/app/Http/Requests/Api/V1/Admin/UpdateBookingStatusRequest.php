<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\Admin\BookingStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBookingStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(BookingStatus::class)],
            'note' => [
                Rule::requiredIf($this->input('status') === BookingStatus::Cancelled->value),
                'string',
                'max:500'
            ],
        ];
    }
}
