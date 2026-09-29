<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Http\Requests\Api\V1\CustomFormRequest;

class AssignBookingTeamRequest extends CustomFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'team_id'           => ['required', 'integer', 'exists:teams,id'],
            'note'              => ['nullable', 'string', 'max:1000'],
            'override_conflict' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'team_id.required' => __('validation.assign_team.team_id_required'),
            'team_id.integer'  => __('validation.assign_team.team_id_integer'),
            'team_id.exists'   => __('validation.assign_team.team_id_exists'),
            'note.string'      => __('validation.assign_team.note_string'),
            'note.max'         => __('validation.assign_team.note_max'),
        ];
    }
}
