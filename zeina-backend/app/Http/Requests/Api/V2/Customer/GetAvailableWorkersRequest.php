<?php

namespace App\Http\Requests\Api\V2\Customer;

use Illuminate\Foundation\Http\FormRequest;

class GetAvailableWorkersRequest extends FormRequest
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
            'date'          => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'start_time'    => ['required', 'date_format:H:i'],
        ];
    }

    /**
     * Get the custom validation error messages.
     */
    public function messages(): array
    {
        return [
            'date.required'          => __('validation.getAvailableWorkers.date_required'),
            'date.date_format'       => __('validation.getAvailableWorkers.date_format'),
            'date.after_or_equal'    => 'The date must be a date after or equal to today.',
            'start_time.required'    => __('validation.getAvailableWorkers.start_time_required'),
            'start_time.date_format' => __('validation.getAvailableWorkers.start_time_format'),
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
