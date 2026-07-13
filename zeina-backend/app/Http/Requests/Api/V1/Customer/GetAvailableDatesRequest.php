<?php

namespace App\Http\Requests\Api\V1\Customer;

use Illuminate\Foundation\Http\FormRequest;

class GetAvailableDatesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation()
    {
        $serviceId = $this->input('service_id');
        $service = $serviceId ? \App\Models\Service::find($serviceId) : null;

        $mergeData = [];

        // Parse child_age_groups if passed as string representation
        $childAgeGroups = $this->input('child_age_groups');
        if (is_string($childAgeGroups)) {
            $decoded = json_decode($childAgeGroups, true);
            if (is_array($decoded)) {
                $mergeData['child_age_groups'] = $decoded;
            } else {
                $trimmed = trim($childAgeGroups, '[]');
                if ($trimmed !== '') {
                    $mergeData['child_age_groups'] = array_map('intval', explode(',', $trimmed));
                } else {
                    $mergeData['child_age_groups'] = [];
                }
            }
        }

        if ($service) {
            if (!$this->filled('worker_gender')) {
                $genderOptions = $service->worker_gender_options ?? [];
                $mergeData['worker_gender'] = count($genderOptions) > 0 ? $genderOptions[0] : 'Female';
            }
        }

        if (!empty($mergeData)) {
            $this->merge($mergeData);
        }
    }

    public function rules(): array
    {
        $serviceId = $this->input('service_id');
        $service = $serviceId ? \App\Models\Service::find($serviceId) : null;
        $isBabysitter = $service && $service->service_code === 'BABYSITTER_HOURLY';

        return [
            'service_id' => 'required|exists:services,id',
            'address_id' => 'required|exists:customer_addresses,id',
            'start_time' => 'required|date_format:H:i',
            'duration' => 'required|integer',
            'worker_gender' => ($isBabysitter ? 'nullable' : 'required') . '|string|in:Male,Female',
            'workers_count' => ($isBabysitter ? 'nullable' : 'required') . '|integer|min:1',
            'number_of_children' => ($isBabysitter ? 'required' : 'nullable') . '|integer|min:1',
            'child_age_groups' => ($isBabysitter ? 'required' : 'nullable') . '|array|min:1',
            'child_age_groups.*' => 'integer|exists:service_child_age_groups,id',
            'note' => 'nullable|string',
        ];
    }

    /**
     * Run advanced validation hooks.
     */
    public function after(): array
    {
        return [
            function ($validator) {
                $serviceId = $this->input('service_id');
                $service = \App\Models\Service::with('childAgeGroups')->find($serviceId);
                if (!$service || $service->service_code !== 'BABYSITTER_HOURLY') {
                    return;
                }

                $childAgeGroups = $this->input('child_age_groups') ?? [];
                if (!is_array($childAgeGroups)) {
                    $childAgeGroups = [];
                }
                $numberOfChildren = (int) $this->input('number_of_children');

                if (count($childAgeGroups) !== $numberOfChildren) {
                    $validator->errors()->add('child_age_groups', 'The number of child age groups must match the number of children.');
                }

                $minChildren = $service->min_children ?? 1;
                $maxChildren = $service->max_children ?? 6;
                if ($numberOfChildren < $minChildren || $numberOfChildren > $maxChildren) {
                    $validator->errors()->add('number_of_children', "The number of children must be between {$minChildren} and {$maxChildren}.");
                }

                $validGroupIds = $service->childAgeGroups->where('status', 'active')->pluck('id')->toArray();
                foreach ($childAgeGroups as $index => $groupId) {
                    if (!in_array($groupId, $validGroupIds)) {
                        $validator->errors()->add("child_age_groups.{$index}", 'The selected age group is invalid or inactive for this service.');
                    }
                }
            }
        ];
    }
}
