<?php

namespace App\Http\Requests\Api\V1\Customer;

use Illuminate\Foundation\Http\FormRequest;

class RportBookingRequest extends FormRequest
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
            'reason' => 'required|string|in:worker_didnt_show_up,late_arrival,poor_service_quality,wrong_charges,other',
            'description' => 'nullable|string|required_if:reason,other',
            'worker_id' => 'nullable|exists:workers,id',
        ];
    }

    public function messages()
    {
        return [
            'reason.required' => __('validation.report.report_reason_required'),
            'reason.string' => __('validation.report.report_reason_string'),
            'reason.in' => __('validation.report.report_reason_invalid'),
            'description.string' => __('validation.report.report_description_string'),
            'description.required_if' => __('validation.report.report_description_required_if_other'),
        ];
    }
}
