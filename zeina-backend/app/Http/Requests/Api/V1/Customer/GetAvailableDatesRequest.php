<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Models\Service;
use App\Traits\ParsesChildAgeGroups;
use Illuminate\Foundation\Http\FormRequest;

class GetAvailableDatesRequest extends FormRequest
{
    use ParsesChildAgeGroups;

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
        $service = $serviceId ? Service::find($serviceId) : null;

        $mergeData = [];
        $mergeData['child_age_groups'] = $this->parseChildAgeGroups();

        if ($service) {
            if (! $this->filled('worker_gender')) {
                $genderOptions = $service->worker_gender_options ?? [];
                $mergeData['worker_gender'] = count($genderOptions) > 0 ? $genderOptions[0] : 'Female';
            }
        }

        if (! empty($mergeData)) {
            $this->merge($mergeData);
        }
    }

    public function rules(): array
    {
        $serviceId = $this->input('service_id');
        $service = $serviceId ? Service::find($serviceId) : null;
        $isBabysitter = $service && $service->service_code === 'BABYSITTER_HOURLY';

        return [
            'service_id' => 'required|exists:services,id',
            'address_id' => 'required|exists:customer_addresses,id',
            'start_time' => 'required|date_format:H:i',
            'duration' => 'required|integer',
            'worker_gender' => ($isBabysitter ? 'nullable' : 'required').'|string|in:Male,Female',
            'workers_count' => ($isBabysitter ? 'nullable' : 'required').'|integer|min:1',
            'number_of_children' => ($isBabysitter ? 'required' : 'nullable').'|integer|min:1',
            'child_age_groups' => $isBabysitter ? 'required|array|min:1' : 'nullable|array',
            'child_age_groups.*' => 'integer|exists:service_child_age_groups,id',
            'note' => 'nullable|string',
        ];
    }

    /**
     * Get custom error messages.
     */
    public function messages(): array
    {
        return [
            'service_id.required' => __('validation.create_booking.service_id_required'),
            'service_id.exists' => __('validation.create_booking.service_id_exists'),
            'address_id.required' => __('validation.create_booking.address_id_required'),
            'address_id.exists' => __('validation.create_booking.address_id_exists'),
            'start_time.required' => __('validation.create_booking.start_time_required'),
            'start_time.date_format' => __('validation.create_booking.start_time_date_format'),
            'duration.required' => __('validation.create_booking.duration_required'),
            'duration.integer' => __('validation.create_booking.duration_integer'),
            'workers_count.integer' => __('validation.create_booking.workers_count_integer'),
            'workers_count.min' => __('validation.create_booking.workers_count_min'),
            'number_of_children.required' => __('validation.create_booking.children_count_between', ['min' => 1, 'max' => 6]),
            'number_of_children.integer' => __('validation.create_booking.number_of_children_integer'),
            'number_of_children.min' => __('validation.create_booking.number_of_children_min'),
            'child_age_groups.required' => __('validation.create_booking.child_age_groups_count_mismatch'),
            'child_age_groups.array' => __('validation.create_booking.dates_array'),
            'child_age_groups.min' => __('validation.create_booking.child_age_groups_min'),
            'child_age_groups.*.integer' => __('validation.create_booking.child_age_groups_integer'),
            'child_age_groups.*.exists' => __('validation.create_booking.child_age_groups_invalid'),
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
                $service = Service::with('childAgeGroups')->find($serviceId);
                if (! $service || $service->service_code !== 'BABYSITTER_HOURLY') {
                    return;
                }

                $childAgeGroups = $this->input('child_age_groups') ?? [];
                if (! is_array($childAgeGroups)) {
                    $childAgeGroups = [];
                }
                $numberOfChildren = (int) $this->input('number_of_children');

                if (count($childAgeGroups) !== $numberOfChildren) {
                    $validator->errors()->add('child_age_groups', __('validation.create_booking.child_age_groups_count_mismatch'));
                }

                $minChildren = $service->min_children ?? 1;
                $maxChildren = $service->max_children ?? 6;
                if ($numberOfChildren < $minChildren || $numberOfChildren > $maxChildren) {
                    $validator->errors()->add('number_of_children', __('validation.create_booking.children_count_between', ['min' => $minChildren, 'max' => $maxChildren]));
                }

                $validGroupIds = $service->childAgeGroups->where('status', 'active')->pluck('id')->toArray();
                foreach ($childAgeGroups as $index => $groupId) {
                    if (! in_array($groupId, $validGroupIds)) {
                        $validator->errors()->add("child_age_groups.{$index}", __('validation.create_booking.child_age_groups_invalid'));
                    }
                }
            },
        ];
    }
}
