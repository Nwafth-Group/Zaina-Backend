<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Models\Service;
use App\Traits\ParsesChildAgeGroups;
use App\Http\Requests\Api\V1\CustomFormRequest;
use Illuminate\Support\Facades\Log;

class GetAvailableDatesRequest extends CustomFormRequest
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

        if ($this->has('selected_options') && !$this->has('options')) {
            $mergeData['options'] = $this->input('selected_options');
        }

        if ($this->has('duration')) {
            $mergeData['duration'] = (int) $this->input('duration');
        }
        if ($this->has('service_id')) {
            $mergeData['service_id'] = (int) $this->input('service_id');
        }
        if ($this->has('address_id')) {
            $mergeData['address_id'] = (int) $this->input('address_id');
        }
        if ($this->has('workers_count')) {
            $mergeData['workers_count'] = (int) $this->input('workers_count');
        }

        if ($service) {
            $isTeamService = $service->isTeamBased();
            if ($isTeamService) {
                if (!$this->filled('duration')) {
                    $mergeData['duration'] = (int) ($service->default_duration ?? 2);
                }
                if (!$this->filled('workers_count')) {
                    $mergeData['workers_count'] = (int) ($service->min_workers ?? 3);
                }
                if (!$this->filled('worker_gender')) {
                    $mergeData['worker_gender'] = 'Male';
                }
            } else {
                if (!$this->filled('duration')) {
                    $mergeData['duration'] = (int) ($service->default_duration ?? 2);
                }
                if (!$this->filled('workers_count')) {
                    $mergeData['workers_count'] = (int) ($service->min_workers ?? 1);
                }
                if (!$this->filled('worker_gender')) {
                    $genderOptions = $service->worker_gender_options ?? [];
                    $mergeData['worker_gender'] = count($genderOptions) > 0 ? $genderOptions[0] : 'Female';
                }
            }
        }

        if (!empty($mergeData)) {
            $this->merge($mergeData);
        }
    }

    public function rules(): array
    {
        $serviceId = $this->input('service_id');
        $service = $serviceId ? Service::find($serviceId) : null;
        $isBabysitter = $service && $service->service_code === 'BABYSITTER_HOURLY';
        $isTeamService = $service && $service->isTeamBased();

        return [
            'service_id' => 'required|exists:services,id',
            'address_id' => 'required|exists:customer_addresses,id',
            'start_time' => ($isTeamService ? 'nullable' : 'required') . '|date_format:H:i',
            'duration' => ($isTeamService ? 'nullable' : 'required') . '|integer',
            'worker_gender' => ($isBabysitter || $isTeamService ? 'nullable' : 'required') . '|string|in:Male,Female',
            'workers_count' => ($isBabysitter || $isTeamService ? 'nullable' : 'required') . '|integer|min:1',
            'number_of_children' => ($isBabysitter ? 'required' : 'nullable') . '|integer|min:1',
            'child_age_groups' => $isBabysitter ? 'required|array|min:1' : 'nullable|array',
            'child_age_groups.*' => 'integer|exists:service_child_age_groups,id',
            'options' => 'nullable|array',
            'options.*.id' => 'required_with:options|integer|exists:service_options,id',
            'options.*.quantity' => 'required_with:options|integer|min:1',
            'selected_options' => 'nullable|array',
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
                if (!$service || $service->service_code !== 'BABYSITTER_HOURLY') {
                    return;
                }

                $childAgeGroups = $this->input('child_age_groups') ?? [];
                Log::info('--- GetAvailableDatesRequest DEBUG ---', [
                    'raw_child_age_groups_input' => $this->input('child_age_groups'),
                    'parsed_child_age_groups' => $childAgeGroups,
                    'numberOfChildren_input' => $this->input('number_of_children'),
                    'query_string' => request()->getQueryString(),
                    'raw_content' => request()->getContent(),
                ]);
                if (!is_array($childAgeGroups)) {
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
                    if (!in_array($groupId, $validGroupIds)) {
                        $validator->errors()->add("child_age_groups.{$index}", __('validation.create_booking.child_age_groups_invalid'));
                    }
                }

                if (!empty($childAgeGroups)) {
                    $bookingService = app(\App\Service\Customer\CustomerBookingService::class);
                    $calculatedMinWorkers = $bookingService->calculateMinimumBabysitters($service, $childAgeGroups);
                    $maxWorkers = $service->max_workers ?? 5;

                    if ($calculatedMinWorkers > $maxWorkers) {
                        $validator->errors()->add('child_age_groups', __('validation.create_booking.babysitters_count_max', ['max' => $maxWorkers]));
                    }

                    $workersCount = $this->input('workers_count');
                    if ($workersCount !== null && $workersCount !== '') {
                        $workersCount = (int) $workersCount;
                        if ($workersCount < $calculatedMinWorkers) {
                            $breakdown = $bookingService->getBabysitterBreakdown($service, $childAgeGroups);
                            $msg = __('validation.create_booking.babysitters_count_min', ['min' => $calculatedMinWorkers]);
                            if (!empty($breakdown)) {
                                $msg .= ' (' . $breakdown . ')';
                            }
                            $validator->errors()->add('workers_count', $msg);
                        }
                        if ($workersCount > $maxWorkers) {
                            $validator->errors()->add('workers_count', __('validation.create_booking.babysitters_count_max', ['max' => $maxWorkers]));
                        }
                    }
                }

                if ($service->service_code === 'PEDICURE_MANICURE') {
                    $numberOfPersons = (int) ($this->input('number_of_persons') ?? 1);
                    $bookingService = app(\App\Service\Customer\CustomerBookingService::class);
                    $calculatedMinWorkers = $bookingService->calculateMinimumPedicureWorkers($service, $numberOfPersons);
                    $workersCount = $this->input('workers_count');
                    if ($workersCount !== null && $workersCount !== '') {
                        $workersCount = (int) $workersCount;
                        if ($workersCount < $calculatedMinWorkers) {
                            $isArabic = app()->getLocale() === 'ar';
                            $msg = $isArabic
                                ? "الحد الأدنى للعمالة المطلوبة لعدد {$numberOfPersons} أشخاص هو {$calculatedMinWorkers} عاملة."
                                : "Minimum required workers for {$numberOfPersons} person(s) is {$calculatedMinWorkers}.";
                            $validator->errors()->add('workers_count', $msg);
                        }
                    }
                }
            },
        ];
    }
}
