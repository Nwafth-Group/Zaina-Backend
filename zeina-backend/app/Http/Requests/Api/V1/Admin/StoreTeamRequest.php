<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\Worker;
use App\Http\Requests\Api\V1\CustomFormRequest;

class StoreTeamRequest extends CustomFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:teams,code'],
            'status' => ['required', 'in:active,inactive'],
            'notes' => ['nullable', 'string'],
            'worker_ids' => ['required', 'array', 'min:1'],
            'worker_ids.*' => ['exists:workers,id'],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['exists:services,id'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $workerIds = $this->input('worker_ids', []);
            if (!empty($workerIds) && is_array($workerIds)) {
                $workers = Worker::whereIn('id', $workerIds)->get();

                foreach ($workers as $worker) {
                    if (strtolower($worker->gender ?? '') !== 'male') {
                        $validator->errors()->add(
                            'worker_ids',
                            __('validation.team.worker_male_only', ['name' => $worker->name])
                        );
                    }

                    if (!empty($worker->team_id)) {
                        $validator->errors()->add(
                            'worker_ids',
                            __('validation.team.worker_already_in_team', ['name' => $worker->name])
                        );
                    }
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'name_ar.required' => __('validation.team.name_ar_required'),
            'name_en.required' => __('validation.team.name_en_required'),
            'code.required' => __('validation.team.code_required'),
            'code.unique' => __('validation.team.code_unique'),
            'status.required' => __('validation.team.status_required'),
            'status.in' => __('validation.team.status_in'),
            'worker_ids.required' => __('validation.team.worker_ids_required'),
            'worker_ids.min' => __('validation.team.worker_ids_min'),
            'worker_ids.*.exists' => __('validation.team.worker_ids_exists'),
        ];
    }
}
