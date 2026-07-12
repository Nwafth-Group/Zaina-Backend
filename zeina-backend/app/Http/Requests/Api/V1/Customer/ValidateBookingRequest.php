<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class ValidateBookingRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'service_id' => ['required', 'exists:services,id'],
            'address_id' => ['nullable', 'exists:customer_addresses,id'],
            'visit_type' => ['nullable', 'string', 'in:single_visit,three_visits_per_week'],
            'dates' => ['required', 'array'],
            'dates.*' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'workers_count' => ['required', 'integer', 'min:1'],
            'preferred_worker_ids' => ['nullable', 'array'],
            'preferred_worker_ids.*' => ['exists:workers,id'],
            'coupon_code' => ['nullable', 'string'],
            'duration' => ['nullable', 'integer'],
            'worker_gender' => ['nullable', 'string', 'in:Male,Female'],
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
            'dates.array' => 'Dates must be an array of dates.',
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
                if (!$service) {
                    return;
                }

                $dates = $this->input('dates') ?? [];
                if (empty($dates) || !is_array($dates)) {
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
                        if ($rules && !$rules->allow_same_day) {
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

                // 4. Workers count boundary validation
                $workersCount = (int) $this->input('workers_count');
                $minWorkers = $service->min_workers ?? 1;
                $maxWorkers = $service->max_workers ?? 3;

                if ($workersCount < $minWorkers || $workersCount > $maxWorkers) {
                    $validator->errors()->add(
                        'workers_count',
                        __('validation.create_booking.workers_count_between', ['min' => $minWorkers, 'max' => $maxWorkers])
                    );
                }

                if ($service->pricing_type === 'per_hour') {
                    $duration = (int) $this->input('duration');
                    $minDuration = $service->min_duration ?? 4;
                    $maxDuration = $service->max_duration ?? 6;
                    if ($duration < $minDuration || $duration > $maxDuration) {
                        $validator->errors()->add('duration', "Duration must be between {$minDuration} and {$maxDuration} hours.");
                    }

                    $gender = $this->input('worker_gender');
                    $genderOptions = $service->worker_gender_options ?? [];
                    if (!$gender || !in_array($gender, $genderOptions)) {
                        $validator->errors()->add('worker_gender', "The selected gender is not available for this service.");
                    }
                }
            }
        ];
    }
}
