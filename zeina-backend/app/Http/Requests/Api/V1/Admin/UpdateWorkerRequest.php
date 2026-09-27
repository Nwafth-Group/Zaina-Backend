<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkerRequest extends FormRequest
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
        $workerId = $this->route('worker') instanceof \App\Models\Worker 
            ? $this->route('worker')->id 
            : $this->route('worker');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone_number' => ['sometimes', 'required', 'string', 'unique:workers,phone_number,' . $workerId, 'max:255'],
            'nationality' => ['sometimes', 'required', 'string', 'max:255'],
            'id_number' => ['sometimes', 'required', 'string', 'unique:workers,id_number,' . $workerId, 'max:255'],
            'address' => ['sometimes', 'required', 'string'],
            'gender' => ['sometimes', 'required', 'string', 'in:male,female'],
            'status' => ['sometimes', 'nullable', 'in:active,inactive,suspended'],
            'team_id' => ['sometimes', 'nullable', 'exists:teams,id'],
            'services' => ['sometimes', 'nullable', 'array'],
            'services.*' => ['exists:services,id'],
            'image' => ['sometimes', 'nullable', 'image', 'mimes:jpeg,png,jpg,gif,svg', 'max:2048'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $workerId = $this->route('worker') instanceof \App\Models\Worker 
                ? $this->route('worker')->id 
                : $this->route('worker');
            $worker = \App\Models\Worker::find($workerId);

            // Check team_id: Teams only male
            if ($this->has('team_id') && $this->input('team_id')) {
                $gender = strtolower($this->input('gender', $worker?->gender ?? ''));
                if ($gender !== 'male') {
                    $validator->errors()->add('team_id', __('validation.store_worker.team_male_only'));
                }
            }

            // Check services assignment rules
            if ($this->has('services')) {
                $serviceIds = $this->input('services', []);
                if (!empty($serviceIds) && is_array($serviceIds)) {
                    $services = \App\Models\Service::whereIn('id', $serviceIds)->get();
                    $teamCodes = \App\Models\Service::getTeamBasedCodes();

                    $nonTeamServices = $services->filter(fn($s) => !in_array($s->service_code, $teamCodes));

                    // If any non-team service (Services 1-5) is selected, max allowed service is 1
                    if ($nonTeamServices->isNotEmpty() && $services->count() > 1) {
                        $validator->errors()->add(
                            'services',
                            __('validation.store_worker.services_single_allowed')
                        );
                    }
                }
            }
        });
    }

    public function messages()
    {
        return [
            'name.required' => __('validation.store_worker.name_required'),
            'nationality.required' => __('validation.store_worker.nationality_required'),
            'phone_number.required' => __('validation.store_worker.phone_number_required'),
            'phone_number.unique' => __('validation.store_worker.phone_number_unique'),
            'id_number.required' => __('validation.store_worker.id_number_required'),
            'id_number.unique' => __('validation.store_worker.id_number_unique'),
            'address.required' => __('validation.store_worker.address_required'),
            'gender.required' => __('validation.store_worker.gender_required'),
            'gender.in' => __('validation.store_worker.gender_in'),
            'services.max' => __('validation.store_worker.services_max'),
            'status.in' => __('validation.store_worker.status_in'),
            'services.array' => __('validation.store_worker.services_array'),
            'services.*.exists' => __('validation.store_worker.services_exists'),
            'image.image' => __('validation.store_worker.image_image'),
            'image.mimes' => __('validation.store_worker.image_mimes'),
            'image.max' => __('validation.store_worker.image_max'),
        ];
    }
}
