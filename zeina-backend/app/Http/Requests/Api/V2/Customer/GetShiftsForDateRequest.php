<?php

namespace App\Http\Requests\Api\V2\Customer;

use Illuminate\Foundation\Http\FormRequest;

class GetShiftsForDateRequest extends FormRequest
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
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
        ];
    }

    /**
     * Get the custom validation error messages.
     */
    public function messages(): array
    {
        return [
            'date.required' => __('validation.getShiftsForDate.date_required'),
            'date.date_format' => __('validation.getShiftsForDate.date_format'),
            'date.after_or_equal' => 'The date must be a date after or equal to today.',
        ];
    }

    /**
     * Perform same-day validation hooks.
     */
    public function after(): array
    {
        return [
            function ($validator) {
                $service = $this->route('service');
                $date = $this->input('date');

                if ($service && $date === now()->toDateString()) {
                    $rules = $service->rules;
                    if ($rules && !$rules->allow_same_day) {
                        $validator->errors()->add('date', 'Same-day booking is not allowed for this service.');
                    }
                }
            }
        ];
    }
}
