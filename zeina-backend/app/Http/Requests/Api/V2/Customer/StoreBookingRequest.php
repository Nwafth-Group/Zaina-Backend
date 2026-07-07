<?php

namespace App\Http\Requests\Api\V2\Customer;

use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
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
            'service_id'     => ['required', 'exists:services,id'],
            'address_id'     => ['required', 'exists:customer_addresses,id'],
            'visit_type'     => ['nullable', 'string', 'in:single_visit,three_visits_per_week'],
            'dates'          => ['required', 'array'],
            'dates.*'        => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'start_time'     => ['required', 'date_format:H:i'],
            'worker_ids'     => ['required', 'array', 'min:1'],
            'worker_ids.*'   => ['required', 'exists:workers,id'],
            'payment_method' => ['required', 'string', 'in:points,wallet,card'],
            'coupon_code'    => ['nullable', 'string'],
        ];
    }

    /**
     * Get custom error messages.
     */
    public function messages(): array
    {
        return [
            'service_id.required'     => __('validation.create_booking.service_id_required'),
            'service_id.exists'       => __('validation.create_booking.service_id_exists'),
            'address_id.required'     => __('validation.create_booking.address_id_required'),
            'address_id.exists'       => __('validation.create_booking.address_id_exists'),
            'dates.required'          => __('validation.create_booking.date_required'),
            'dates.array'             => 'Dates must be an array of dates.',
            'dates.*.date_format'     => __('validation.create_booking.date_date_format'),
            'dates.*.after_or_equal'  => 'The date must be a date after or equal to today.',
            'start_time.required'     => __('validation.create_booking.start_time_required'),
            'start_time.date_format'  => __('validation.create_booking.start_time_date_format'),
            'payment_method.required' => __('validation.create_booking.payment_method_required'),
            'payment_method.string'   => __('validation.create_booking.payment_method_string'),
            'payment_method.in'       => __('validation.create_booking.payment_method_in'),
            'worker_ids.required'     => __('validation.create_booking.worker_ids_required'),
            'worker_ids.array'        => __('validation.create_booking.worker_ids_array'),
            'worker_ids.min'          => __('validation.create_booking.worker_ids_min'),
            'worker_ids.*.exists'     => __('validation.create_booking.worker_ids_exists'),
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

                // 2. Validate all dates belong to the same calendar week
                if ($visitType === 'three_visits_per_week') {
                    $weeks = collect($dates)->map(function ($d) {
                        return Carbon::parse($d)->format('o-W');
                    })->unique();

                    if ($weeks->count() > 1) {
                        $validator->errors()->add('dates', __('validation.create_booking.dates_same_week'));
                    }
                }

                // 3. Same-day & Advance limit checks
                $rules = $service->rules;
                $todayStr = now()->toDateString();

                foreach ($dates as $dateStr) {
                    $date = Carbon::parse($dateStr);

                    if ($dateStr === $todayStr) {
                        if ($rules && !$rules->allow_same_day) {
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
            }
        ];
    }
}
