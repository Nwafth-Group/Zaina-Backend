<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ReplaceBookingWorkerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'old_worker_id'   => ['required', 'integer', 'exists:workers,id'],
            'new_worker_id'   => ['required', 'integer', 'exists:workers,id', 'different:old_worker_id'],
            'reason'          => ['required', 'string', 'max:500'],
            'override_buffer' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'old_worker_id.required'  => __('validation.replace_worker.old_worker_required'),
            'old_worker_id.exists'    => __('validation.replace_worker.old_worker_exists'),
            'new_worker_id.required'  => __('validation.replace_worker.new_worker_required'),
            'new_worker_id.exists'    => __('validation.replace_worker.new_worker_exists'),
            'new_worker_id.different' => __('validation.replace_worker.new_worker_different'),
            'reason.required'         => __('validation.replace_worker.reason_required'),
            'reason.string'           => __('validation.replace_worker.reason_string'),
            'reason.max'              => __('validation.replace_worker.reason_max'),
        ];
    }
}
