<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Models\Booking;
use App\Models\Service;
use App\Service\Customer\CustomerBookingService;
use App\Traits\ParsesChildAgeGroups;
use Carbon\Carbon;
use App\Http\Requests\Api\V1\CustomFormRequest;

class StoreBookingRequest extends CustomFormRequest
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
        if ($this->has('number_of_children')) {
            $mergeData['number_of_children'] = (int) $this->input('number_of_children');
        }
        if ($this->has('number_of_persons')) {
            $mergeData['number_of_persons'] = (int) $this->input('number_of_persons');
        }

        if ($service) {
            $genderOptions = $service->worker_gender_options ?? [];
            $isTeamService = $service->isTeamBased();
            if ($isTeamService) {
                $mergeData['duration'] = (int) ($service->default_duration ?? 2);
                $mergeData['visit_type'] = 'single_visit';
                $mergeData['worker_gender'] = 'Male';
                if (! $this->filled('start_time')) {
                    $mergeData['start_time'] = '08:00';
                }
            } elseif ($service->service_code === 'DEEP_CLEANING') {
                $mergeData['duration'] = (int) ($service->default_duration ?? 6);
                $mergeData['visit_type'] = 'single_visit';
                $mergeData['worker_gender'] = in_array('Male', $genderOptions) ? 'Male' : (count($genderOptions) > 0 ? $genderOptions[0] : 'Male');
            } elseif ($service->service_code === 'PEDICURE_MANICURE') {
                $mergeData['worker_gender'] = 'Female';
                $bookingService = app(CustomerBookingService::class);
                $persons = (int) ($this->input('number_of_persons') ?? 1);
                $workerIds = $this->input('worker_ids') ?? [];
                $workersCount = count($workerIds);
                if ($workersCount > 0) {
                    $mergeData['duration'] = $bookingService->calculatePedicureDuration($service, $persons, $workersCount);
                }
            } else {
                if (! $this->filled('duration')) {
                    $mergeData['duration'] = (int) ($service->min_duration ?: ($service->default_duration ?? 4));
                }

                if ($service->service_code === 'DOMESTIC_HELPER_HOURLY' || $service->service_code === 'BABYSITTER_HOURLY' || (count($genderOptions) === 1 && $genderOptions[0] === 'Female')) {
                    $mergeData['worker_gender'] = 'Female';
                } elseif (! $this->filled('worker_gender')) {
                    $mergeData['worker_gender'] = count($genderOptions) > 0 ? $genderOptions[0] : 'Female';
                }
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
        $isPedicureManicure = $service && $service->service_code === 'PEDICURE_MANICURE';

        $isTeamService = $service && $service->isTeamBased();

        return [
            'service_id' => ['required', 'exists:services,id'],
            'address_id' => ['required', 'exists:customer_addresses,id'],
            'visit_type' => ['nullable', 'string', 'in:single_visit,three_visits_per_week'],
            'dates' => ['required', 'array'],
            'dates.*' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'start_time' => [$isTeamService ? 'nullable' : 'required', 'date_format:H:i'],
            'team_id' => ['nullable', 'exists:teams,id'],
            'worker_ids' => $isTeamService ? ['nullable', 'array'] : ['required', 'array', 'min:1'],
            'worker_ids.*' => ['required', 'integer', 'exists:workers,id'],
            'payment_method' => ['required', 'string', 'in:points,wallet,card'],
            'coupon_code' => ['nullable', 'string'],
            'duration' => ['nullable', 'integer'],
            'worker_gender' => ['nullable', 'string', 'in:Male,Female'],
            'number_of_children' => [$isBabysitter ? 'required' : 'nullable', 'integer', 'min:1'],
            'number_of_persons' => [$isPedicureManicure ? 'required' : 'nullable', 'integer', 'min:1'],
            'child_age_groups' => $isBabysitter ? ['required', 'array', 'min:1'] : ['nullable', 'array'],
            'child_age_groups.*' => ['integer', 'exists:service_child_age_groups,id'],
            'options' => ['nullable', 'array'],
            'options.*.id' => ['required_with:options', 'integer', 'exists:service_options,id'],
            'options.*.quantity' => ['required_with:options', 'integer', 'min:1'],
            'selected_options' => ['nullable', 'array'],
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
            'payment_method.required' => __('validation.create_booking.payment_method_required'),
            'payment_method.string' => __('validation.create_booking.payment_method_string'),
            'payment_method.in' => __('validation.create_booking.payment_method_in'),
            'worker_ids.required' => __('validation.create_booking.worker_ids_required'),
            'worker_ids.array' => __('validation.create_booking.worker_ids_array'),
            'worker_ids.min' => __('validation.create_booking.worker_ids_min'),
            'worker_ids.*.exists' => __('validation.create_booking.worker_ids_exists'),
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
                $customerId = $this->user()?->id ?? auth()->id();
                if ($serviceId && $customerId) {
                    $hasActiveBooking = Booking::where('customer_id', $customerId)
                        ->where('service_id', $serviceId)
                        ->whereIn('status', ['Upcoming', 'inService'])
                        ->exists();

                    if ($hasActiveBooking) {
                        $validator->errors()->add('service_id', __('validation.create_booking.active_service_booking_exists'));
                    }
                }
            },
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

                // 2. Validate all dates belong to a 1-week window starting from the first visit (e.g. Monday to Monday)
                if ($visitType === 'three_visits_per_week' && count($dates) >= 2) {
                    $sortedDates = collect($dates)->map(fn($d) => Carbon::parse($d))->sort()->values();
                    $firstDate = $sortedDates->first();
                    $lastDate = $sortedDates->last();

                    if ($lastDate->gt($firstDate->copy()->addDays(7))) {
                        $validator->errors()->add('dates', __('validation.create_booking.dates_same_week'));
                    }
                }

                // 3. Same-day & Advance limit checks
                $rules = $service->rules;
                $todayStr = now()->toDateString();

                foreach ($dates as $dateStr) {
                    $date = Carbon::parse($dateStr);

                    if ($dateStr === $todayStr) {
                        if ($rules && ! $rules->allow_same_day) {
                            $validator->errors()->add('dates', __('validation.create_booking.dates_same_day_not_allowed', ['date' => $dateStr]));
                        }
                    }

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

                $isSecondService = ($service->service_code === 'HOSPITALITY_WORKER_HOURLY');
                $isThirdService = ($service->service_code === 'BABYSITTER_HOURLY');
                $isSecondOrThird = ($isSecondService || $isThirdService);

                if ($isThirdService) {
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
                    if ($calculatedMinWorkers === 1) {
                        $maxWorkers = 1;
                    }
                    if ($calculatedMinWorkers > $maxWorkers) {
                        $validator->errors()->add('child_age_groups', __('validation.create_booking.babysitters_count_max', ['max' => $maxWorkers]));
                    }

                    $workerIds = $this->input('worker_ids') ?? [];
                    $workersCount = count($workerIds);
                    if ($workersCount < $calculatedMinWorkers) {
                        $breakdown = app(CustomerBookingService::class)->getBabysitterBreakdown($service, $childAgeGroups);
                        $msg = __('validation.create_booking.babysitters_count_min', ['min' => $calculatedMinWorkers]);
                        if (! empty($breakdown)) {
                            $msg .= ' ('.$breakdown.')';
                        }
                        $validator->errors()->add('worker_ids', $msg);
                    }
                    if ($workersCount > $maxWorkers) {
                        $validator->errors()->add('worker_ids', __('validation.create_booking.babysitters_count_max', ['max' => $maxWorkers]));
                    }
                }

                if ($service->service_code === 'PEDICURE_MANICURE') {
                    $numberOfPersons = (int) ($this->input('number_of_persons') ?? 1);
                    $bookingService = app(CustomerBookingService::class);
                    $calculatedMinWorkers = $bookingService->calculateMinimumPedicureWorkers($service, $numberOfPersons);
                    $workerIds = $this->input('worker_ids') ?? [];
                    $workersCount = count($workerIds);
                    $maxAllowedWorkers = min($numberOfPersons, $service->max_workers ?? 10);

                    if ($workersCount < $calculatedMinWorkers) {
                        $isArabic = app()->getLocale() === 'ar';
                        $msg = $isArabic
                            ? "الحد الأدنى للعمالة المطلوبة لعدد {$numberOfPersons} أشخاص هو {$calculatedMinWorkers} عاملة."
                            : "Minimum required workers for {$numberOfPersons} person(s) is {$calculatedMinWorkers}.";
                        $validator->errors()->add('worker_ids', $msg);
                    }
                    if ($workersCount > $maxAllowedWorkers) {
                        $isArabic = app()->getLocale() === 'ar';
                        $msg = $isArabic
                            ? "الحد الأقصى للعمالة المسموح به لهذا الحجز هو {$maxAllowedWorkers} عاملة."
                            : "Maximum allowed workers for this booking is {$maxAllowedWorkers}.";
                        $validator->errors()->add('worker_ids', $msg);
                    }
                }

                if ($service->pricing_type === 'per_hour') {
                    $workerGender = $this->input('worker_gender');
                    if (empty($workerGender) && $service->service_code === 'BABYSITTER_HOURLY') {
                        $workerGender = 'Female';
                    }
                    $genderOptions = $service->worker_gender_options ?? [];
                    if (empty($workerGender)) {
                        $validator->errors()->add('worker_gender', __('validation.create_booking.worker_gender_invalid'));
                    } elseif (! in_array($workerGender, $genderOptions)) {
                        $validator->errors()->add('worker_gender', __('validation.create_booking.worker_gender_invalid'));
                    }

                    $duration = $this->input('duration');
                    if ($duration === null) {
                        $validator->errors()->add('duration', __('validation.create_booking.duration_required'));
                    } else {
                        $duration = (int) $duration;
                        if ($service->has_duration_range) {
                            $minDuration = $service->min_duration ?? 4;
                            $maxDuration = $service->max_duration ?? 6;
                            if ($duration < $minDuration || $duration > $maxDuration) {
                                $validator->errors()->add('duration', __('validation.create_booking.duration_between', ['min' => $minDuration, 'max' => $maxDuration]));
                            }
                        } else {
                            $defaultDuration = $service->default_duration ?? 2;
                            if ($duration !== $defaultDuration) {
                                $validator->errors()->add('duration', __('validation.create_booking.duration_exact', ['duration' => $defaultDuration]));
                            }
                        }
                    }
                }
            },
        ];
    }
}
