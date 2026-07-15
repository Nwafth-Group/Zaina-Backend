<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Models\Service;
use App\Service\Customer\CustomerBookingService;
use App\Traits\ParsesChildAgeGroups;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;

class ValidateBookingRequest extends FormRequest
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
            if (! $this->filled('duration')) {
                $mergeData['duration'] = $service->default_duration ?? 4;
            }

            if (! $this->filled('worker_gender')) {
                $genderOptions = $service->worker_gender_options ?? [];
                $mergeData['worker_gender'] = count($genderOptions) > 0 ? $genderOptions[0] : 'Female';
            }
        }

        if (! empty($mergeData)) {
            $this->merge($mergeData);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $serviceId = $this->input('service_id');
        $service = $serviceId ? Service::find($serviceId) : null;
        $isBabysitter = $service && $service->service_code === 'BABYSITTER_HOURLY';

        return [
            'service_id' => ['required', 'exists:services,id'],
            'address_id' => ['nullable', 'exists:customer_addresses,id'],
            'visit_type' => ['nullable', 'string', 'in:single_visit,three_visits_per_week'],
            'dates' => ['required', 'array'],
            'dates.*' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'workers_count' => [$isBabysitter ? 'nullable' : 'required', 'integer', 'min:1'],
            'preferred_worker_ids' => ['nullable', 'array'],
            'preferred_worker_ids.*' => ['exists:workers,id'],
            'coupon_code' => ['nullable', 'string'],
            'duration' => ['nullable', 'integer'],
            'worker_gender' => ['nullable', 'string', 'in:Male,Female'],
            'number_of_children' => [$isBabysitter ? 'required' : 'nullable', 'integer', 'min:1'],
            'child_age_groups' => $isBabysitter ? ['required', 'array', 'min:1'] : ['nullable', 'array'],
            'child_age_groups.*' => ['integer', 'exists:service_child_age_groups,id'],
            'note' => ['nullable', 'string'],
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
            'dates.required' => __('validation.create_booking.date_required'),
            'dates.array' => __('validation.create_booking.dates_array'),
            'dates.*.date_format' => __('validation.create_booking.date_date_format'),
            'dates.*.after_or_equal' => __('validation.create_booking.date_after_or_equal'),
            'start_time.required' => __('validation.create_booking.start_time_required'),
            'start_time.date_format' => __('validation.create_booking.start_time_date_format'),
            'workers_count.required' => __('validation.create_booking.workers_count_required'),
            'workers_count.integer' => __('validation.create_booking.workers_count_integer'),
            'workers_count.min' => __('validation.create_booking.workers_count_min'),
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
                $service = Service::with('rules')->find($serviceId);
                if (! $service) {
                    return;
                }

                $dates = $this->input('dates') ?? [];
                if (empty($dates) || ! is_array($dates)) {
                    return;
                }

                // Check for duplicate dates
                if (count($dates) !== count(array_unique($dates))) {
                    $validator->errors()->add('dates', __('validation.create_booking.dates_unique'));

                    return;
                }

                $visitType = $this->input('visit_type') ?? 'single_visit';

                // 1. Validate date counts by visit type
                if ($visitType === 'three_visits_per_week' && count($dates) !== 3) {
                    $validator->errors()->add('dates', __('validation.create_booking.dates_exactly_three'));

                    return;
                } elseif ($visitType === 'single_visit' && count($dates) !== 1) {
                    $validator->errors()->add('dates', __('validation.create_booking.dates_exactly_one'));

                    return;
                }

                // 2. Validate all dates belong to the same calendar week (ISO 8601 week definition)
                if ($visitType === 'three_visits_per_week') {
                    $weeks = collect($dates)->map(function ($d) {
                        return Carbon::parse($d)->startOfWeek(Carbon::SATURDAY)->toDateString();
                    })->unique();

                    if ($weeks->count() > 1) {
                        $validator->errors()->add('dates', __('validation.create_booking.dates_same_week'));
                    }
                }

                // 3. Same-day & Advance booking limits validation per date
                $rules = $service->rules;
                $todayStr = now()->toDateString();

                foreach ($dates as $dateStr) {
                    $date = Carbon::parse($dateStr);

                    // Same-day check
                    if ($dateStr === $todayStr) {
                        if ($rules && ! $rules->allow_same_day) {
                            $validator->errors()->add('dates', __('validation.create_booking.dates_same_day_not_allowed', ['date' => $dateStr]));
                        }
                    }

                    // Max advance limit check
                    if ($rules && $rules->max_advance_booking_days > 0) {
                        $maxDate = Carbon::today()->addDays($rules->max_advance_booking_days);
                        if ($date->gt($maxDate)) {
                            $validator->errors()->add(
                                'dates',
                                __('validation.create_booking.dates_max_advance', ['date' => $dateStr, 'days' => $rules->max_advance_booking_days])
                            );
                        }
                    }
                }
            },
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

                $validGroups = $service->childAgeGroups()->where('status', 'active')->get();
                $validGroupIds = $validGroups->pluck('id')->toArray();

                $calculatedMinWorkers = 0;
                $groupedCounts = array_count_values($childAgeGroups);

                foreach ($groupedCounts as $groupId => $count) {
                    $group = $validGroups->firstWhere('id', $groupId);
                    if (! $group) {
                        $validator->errors()->add('child_age_groups', __('validation.create_booking.child_age_groups_invalid'));

                        continue;
                    }

                    $childrenCountRule = $group->children_count ?? 1;
                    if ($childrenCountRule <= 0) {
                        $childrenCountRule = 1;
                    }
                    $requiredWorkerCountRule = $group->required_worker_count ?? 1;

                    $calculatedMinWorkers += (int) ceil($count / $childrenCountRule) * $requiredWorkerCountRule;
                }

                if ($numberOfChildren >= 3) {
                    $calculatedMinWorkers = max($calculatedMinWorkers, 2);
                }

                $maxWorkers = $service->max_workers ?? 5;
                if ($calculatedMinWorkers > $maxWorkers) {
                    $validator->errors()->add('child_age_groups', __('validation.create_booking.babysitters_count_max', ['max' => $maxWorkers]));
                }

                $workersCount = $this->input('workers_count');
                if ($workersCount !== null) {
                    $workersCount = (int) $workersCount;
                    if ($workersCount < $calculatedMinWorkers) {
                        $breakdown = app(CustomerBookingService::class)->getBabysitterBreakdown($service, $childAgeGroups);
                        $msg = __('validation.create_booking.babysitters_count_min', ['min' => $calculatedMinWorkers]);
                        if (! empty($breakdown)) {
                            $msg .= ' ('.$breakdown.')';
                        }
                        $validator->errors()->add('workers_count', $msg);
                    }
                    if ($workersCount > $maxWorkers) {
                        $validator->errors()->add('workers_count', __('validation.create_booking.babysitters_count_max', ['max' => $maxWorkers]));
                    }
                }
            },
            function ($validator) {
                $serviceId = $this->input('service_id');
                $service = Service::find($serviceId);
                if (! $service) {
                    return;
                }

                if ($service->service_code !== 'BABYSITTER_HOURLY') {
                    $workersCount = (int) $this->input('workers_count');
                    $minWorkers = $service->min_workers ?? 1;
                    $maxWorkers = $service->max_workers ?? 3;

                    if ($workersCount < $minWorkers || $workersCount > $maxWorkers) {
                        $validator->errors()->add(
                            'workers_count',
                            __('validation.create_booking.workers_count_between', ['min' => $minWorkers, 'max' => $maxWorkers])
                        );
                    }
                }

                if ($service->pricing_type === 'per_hour') {
                    $duration = (int) $this->input('duration');
                    if ($service->has_duration_range) {
                        $minDuration = $service->min_duration ?? 4;
                        $maxDuration = $service->max_duration ?? 6;
                        if ($duration < $minDuration || $duration > $maxDuration) {
                            $validator->errors()->add('duration', "Duration must be between {$minDuration} and {$maxDuration} hours.");
                        }
                    } else {
                        $defaultDuration = $service->default_duration ?? 2;
                        if ($duration !== $defaultDuration) {
                            $validator->errors()->add('duration', "Duration must be exactly {$defaultDuration} hours.");
                        }
                    }

                    $gender = $this->input('worker_gender');
                    if (empty($gender) && $service->service_code === 'BABYSITTER_HOURLY') {
                        $gender = 'Female';
                    }
                    $genderOptions = $service->worker_gender_options ?? [];
                    if (! $gender || ! in_array($gender, $genderOptions)) {
                        $validator->errors()->add('worker_gender', __('validation.create_booking.worker_gender_invalid'));
                    }
                }
            },
        ];
    }
}
