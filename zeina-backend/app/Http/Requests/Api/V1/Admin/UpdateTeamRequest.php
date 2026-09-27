<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\Team;
use App\Models\Worker;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $team = $this->route('team');
        $teamId = $team instanceof Team ? $team->id : $team;

        return [
            'name_ar' => ['sometimes', 'required', 'string', 'max:255'],
            'name_en' => ['sometimes', 'required', 'string', 'max:255'],
            'code' => ['sometimes', 'required', 'string', 'max:50', 'unique:teams,code,' . $teamId],
            'status' => ['sometimes', 'required', 'in:active,inactive'],
            'notes' => ['nullable', 'string'],
            'worker_ids' => ['sometimes', 'array', 'size:3'],
            'worker_ids.*' => ['exists:workers,id'],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['exists:services,id'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $team = $this->route('team');
            $teamId = $team instanceof Team ? $team->id : $team;

            $workerIds = $this->input('worker_ids');
            if ($workerIds !== null && is_array($workerIds)) {
                if (count($workerIds) !== 3) {
                    $validator->errors()->add(
                        'worker_ids',
                        __('validation.team.worker_ids_size')
                    );
                }

                $workers = Worker::whereIn('id', $workerIds)->get();

                foreach ($workers as $worker) {
                    if (strtolower($worker->gender ?? '') !== 'male') {
                        $validator->errors()->add(
                            'worker_ids',
                            __('validation.team.worker_male_only', ['name' => $worker->name])
                        );
                    }

                    if (!empty($worker->team_id) && $worker->team_id != $teamId) {
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
            'worker_ids.size' => __('validation.team.worker_ids_size'),
            'worker_ids.*.exists' => __('validation.team.worker_ids_exists'),
        ];
    }
}
