<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Http\Requests\Api\V1\CustomFormRequest;


class PointHistoryRequest extends CustomFormRequest
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
            'filter'     => 'nullable|in:today,last_7_days,last_30_days,custom',
            'start_date' => 'required_if:filter,custom|nullable|date_format:Y-m-d',
            'end_date'   => 'required_if:filter,custom|nullable|date_format:Y-m-d|after_or_equal:start_date',
        ];
    }

    public function messages(): array
    {
        return [
            'start_date.date_format' => __('validation.history.date_format', ['attribute' => 'start_date', 'format' => 'Y-m-d']),
            'end_date.date_format'   => __('validation.history.date_format', ['attribute' => 'end_date', 'format' => 'Y-m-d']),
            'end_date.after_or_equal'=> __('validation.history.after_or_equal', ['attribute' => 'end_date', 'date' => 'start_date']),
        ];
    }
}
