<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateServiceRequest extends FormRequest
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
        $service = $this->route('service');
        $serviceId = $service ? (is_object($service) ? $service->id : $service) : null;
        $categoryId = $this->input('category_id') ?? ($service ? $service->category_id : null);

        return [
            // البيانات الأساسية
            'category_id' => 'sometimes|nullable|exists:categories,id',
            'service_code' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
                \Illuminate\Validation\Rule::unique('services', 'service_code')->ignore($serviceId),
            ],
            'type' => 'sometimes|required|in:hourly,other',
            'name_ar' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                \Illuminate\Validation\Rule::unique('services', 'name_ar')->where('category_id', $categoryId)->ignore($serviceId),
            ],
            'name_en' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                \Illuminate\Validation\Rule::unique('services', 'name_en')->where('category_id', $categoryId)->ignore($serviceId),
            ],
            'description_ar' => 'sometimes|required|string|max:500',
            'description_en' => 'sometimes|required|string|max:500',
            'features' => 'nullable|array',
            'features.*.ar' => 'nullable|string|max:255',
            'features.*.en' => 'nullable|string|max:255',
            'status' => 'sometimes|required|in:active,inactive',
            'workers' => 'sometimes|nullable|array',
            'workers.*' => [
                'integer',
                \Illuminate\Validation\Rule::exists('workers', 'id')->where('status', 'active'),
            ],
            
            // Available Visit Type & Pricing
            'available_visit_types' => 'sometimes|required|array|min:1',
            'available_visit_types.*.type' => 'required_with:available_visit_types|string|in:single_visit,3_days_per_week',
            'available_visit_types.*.discount' => 'nullable|numeric|min:0|max:100',
            'discount_value' => 'sometimes|nullable|numeric|min:0',
            'base_price' => 'sometimes|required|numeric|min:0',

            // Service Image
            'image' => 'sometimes|nullable|file|mimes:svg|max:2048',

            // Coverage Cities & Areas
            'coverages' => 'sometimes|required|array|min:1',
            'coverages.*.city_id' => 'required_with:coverages|exists:cities,id',
            'coverages.*.area_id' => 'nullable|exists:areas,id',
            'coverages.*.status' => 'nullable|in:active,inactive,coming_soon',

            // Prices
            'prices' => 'sometimes|nullable|array',
            'prices.*.pricing_scope' => 'required_with:prices|in:city,area',
            'prices.*.city_id' => 'nullable|exists:cities,id',
            'prices.*.area_id' => 'nullable|exists:areas,id',
            'prices.*.price_per_service_period' => 'required_with:prices|numeric|min:0',
            'prices.*.currency' => 'nullable|string|max:10',
            'prices.*.effective_start' => 'nullable|date',
            'prices.*.effective_end' => 'nullable|date|after_or_equal:prices.*.effective_start',

            // Service Duration & Time
            'start_time' => 'sometimes|nullable|date_format:H:i',
            'end_time' => 'sometimes|nullable|date_format:H:i|after:start_time',
            'default_duration' => 'sometimes|required|integer|min:1',
            'duration_unit' => 'sometimes|nullable|string|in:Hours,Days',
            'has_duration_range' => 'sometimes|nullable|boolean',
            'min_duration' => 'sometimes|nullable|integer|min:1',
            'max_duration' => 'sometimes|nullable|integer|min:1|gte:min_duration',
            'min_workers' => 'sometimes|required|integer|min:1',
            'max_workers' => 'sometimes|required|integer|min:1|gte:min_workers',

            // Durations array for backward compatibility
            'durations' => 'sometimes|nullable|array',
            'durations.service_period_hours' => 'sometimes|nullable|integer|min:1',
            'durations.allowed_contract_durations' => 'sometimes|nullable|array',
            'durations.min_contract_months' => 'sometimes|nullable|integer|min:1',

            // Service Shifts & Schedules
            'working_days' => 'sometimes|nullable|array|min:1',
            'working_days.*' => 'required_with:working_days|in:saturday,sunday,monday,tuesday,wednesday,thursday,friday',
            'shifts' => 'required_with:working_days|array|min:1',
            'shifts.*.start_time' => 'required_with:shifts|date_format:H:i',
            'shifts.*.end_time' => 'nullable|date_format:H:i',

            // Fallback for old schedules format in tests/seeders
            'schedules' => 'sometimes|nullable|array|min:1',
            'schedules.*.day' => 'required_with:schedules|in:saturday,sunday,monday,tuesday,wednesday,thursday,friday',
            'schedules.*.start_time' => 'required_with:schedules|date_format:H:i',
            'schedules.*.end_time' => 'required_with:schedules|date_format:H:i',
            'schedules.*.is_active' => 'nullable|boolean',

            // Service Rules
            'rules' => 'sometimes|array',
            'rules.allow_cancel' => 'nullable|boolean',
            'rules.free_cancel_cutoff_hours' => 'required_if:rules.allow_cancel,true|integer|min:0',
            'rules.allow_reschedule' => 'nullable|boolean',
            'rules.reschedule_cutoff_hours' => 'required_if:rules.allow_reschedule,true|integer|min:0',
            'rules.allow_same_day' => 'nullable|boolean',
            'rules.max_advance_booking_days' => 'nullable|integer|min:0',
            'rules.lead_time_min_hours' => 'nullable|integer|min:0',
            'rules.lead_time_max_days' => 'nullable|integer|min:1',
            'rules.late_cancel_action' => 'nullable|in:fee,block,manual',
            'rules.late_cancel_fee_type' => 'nullable|in:percent,fixed',
            'rules.late_cancel_fee_value' => 'nullable|numeric|min:0',
            'rules.no_show_policy' => 'nullable|in:compensate,consumed',
            'rules.max_changes_per_booking' => 'nullable|integer|min:0',
            'rules.arrival_buffer_minutes' => 'nullable|integer|min:0',
            'rules.late_arrival_grace_minutes' => 'nullable|integer|min:0',
            
            // Terms & Conditions
            'terms_ar' => 'sometimes|required|string|max:5000',
            'terms_en' => 'sometimes|required|string|max:5000',
            'acceptance_required' => 'sometimes|nullable|boolean',
        ];
    }

    public function after(): array
    {
        return [
            function ($validator) {
                $service = $this->route('service');
                if ($this->has('service_code') && $service && $this->input('service_code') !== $service->service_code) {
                    $validator->errors()->add('service_code', 'The service code cannot be updated.');
                }
                $startTime = $this->input('start_time') ?? ($service ? $service->start_time : null);
                $endTime = $this->input('end_time') ?? ($service ? $service->end_time : null);
                $shifts = $this->input('shifts', []);
                $defaultDuration = (int) ($this->input('default_duration') ?? ($service ? $service->default_duration : 1));

                if ($startTime && $endTime) {
                    foreach ($shifts as $index => $shift) {
                        if (!empty($shift['start_time'])) {
                            try {
                                $start = \Carbon\Carbon::parse($shift['start_time']);
                                $end = !empty($shift['end_time']) 
                                    ? \Carbon\Carbon::parse($shift['end_time']) 
                                    : $start->copy()->addHours($defaultDuration);
                                    
                                $startStr = $start->format('H:i');
                                $endStr = $end->format('H:i');

                                // Check window boundary limits
                                if ($startStr < $startTime || $endStr > $endTime) {
                                    $validator->errors()->add(
                                        "shifts.{$index}.start_time",
                                        "The shift schedule times must be within the service request window ({$startTime} to {$endTime})."
                                    );
                                }
                            } catch (\Exception $e) {
                                // Skip parsing errors, handled by standard validation
                            }
                        }
                    }
                }
            }
        ];
    }

    public function messages(): array
    {
        return [
            // البيانات الأساسية
            'category_id.exists' => __('validation.update_service.category_id_exists'),
            'type.in' => __('validation.update_service.type_in'),
            'name_ar.max' => __('validation.update_service.name_ar_max'),
            'name_en.max' => __('validation.update_service.name_en_max'),
            'status.in' => __('validation.update_service.status_in'),

            // المدد الزمنية
            'durations.array' => __('validation.update_service.durations_array'),
            'durations.service_period_hours.required_if' => __('validation.update_service.service_period_hours_required_if'),
            'durations.service_period_hours.integer' => __('validation.update_service.service_period_hours_integer'),
            'durations.service_period_hours.min' => __('validation.update_service.service_period_hours_min'),
            'durations.allowed_contract_durations.required_if' => __('validation.update_service.allowed_contract_durations_required_if'),
            'durations.allowed_contract_durations.array' => __('validation.update_service.allowed_contract_durations_array'),
            'durations.min_contract_months.required_if' => __('validation.update_service.min_contract_months_required_if'),
            'durations.min_contract_months.integer' => __('validation.update_service.min_contract_months_integer'),
            'durations.min_contract_months.min' => __('validation.update_service.min_contract_months_min'),

            // العمالة
            'workers.required' => __('validation.update_service.workers_required'),
            'workers.array' => __('validation.update_service.workers_array'),
            'workers.min' => __('validation.update_service.workers_min'),
            'workers.*.integer' => __('validation.update_service.worker_id_integer'),
            'workers.*.exists' => __('validation.update_service.worker_id_exists'),

            // قواعد الخدمة
            'rules.array' => __('validation.update_service.rules_array'),
            'rules.free_cancel_cutoff_hours.integer' => __('validation.update_service.free_cancel_cutoff_hours_integer'),
            'rules.free_cancel_cutoff_hours.min' => __('validation.update_service.free_cancel_cutoff_hours_min'),
            'rules.reschedule_cutoff_hours.integer' => __('validation.update_service.reschedule_cutoff_hours_integer'),
            'rules.reschedule_cutoff_hours.min' => __('validation.update_service.reschedule_cutoff_hours_min'),
            'rules.lead_time_min_hours.integer' => __('validation.update_service.lead_time_min_hours_integer'),
            'rules.lead_time_min_hours.min' => __('validation.update_service.lead_time_min_hours_min'),
            'rules.lead_time_max_days.integer' => __('validation.update_service.lead_time_max_days_integer'),
            'rules.lead_time_max_days.min' => __('validation.update_service.lead_time_max_days_min'),
            'rules.late_cancel_action.in' => __('validation.update_service.late_cancel_action_in'),
            'rules.late_cancel_fee_type.in' => __('validation.update_service.late_cancel_fee_type_in'),
            'rules.late_cancel_fee_value.numeric' => __('validation.update_service.late_cancel_fee_value_numeric'),
            'rules.late_cancel_fee_value.min' => __('validation.update_service.late_cancel_fee_value_min'),
            'rules.no_show_policy.in' => __('validation.update_service.no_show_policy_in'),
            'rules.max_changes_per_booking.integer' => __('validation.update_service.max_changes_per_booking_integer'),
            'rules.max_changes_per_booking.min' => __('validation.update_service.max_changes_per_booking_min'),

            // التغطية الجغرافية
            'coverages.required' => __('validation.update_service.coverages_required'),
            'coverages.array' => __('validation.update_service.coverages_array'),
            'coverages.min' => __('validation.update_service.coverages_min'),
            'coverages.*.city_id.required_with' => __('validation.update_service.coverage_city_required'),
            'coverages.*.city_id.exists' => __('validation.update_service.coverage_city_exists'),
            'coverages.*.area_id.exists' => __('validation.update_service.coverage_area_exists'),
            'coverages.*.status.in' => __('validation.update_service.coverage_status_in'),

            // التسعير
            'prices.required' => __('validation.update_service.prices_required'),
            'prices.array' => __('validation.update_service.prices_array'),
            'prices.min' => __('validation.update_service.prices_min'),
            'prices.*.pricing_scope.required_with' => __('validation.update_service.pricing_scope_required'),
            'prices.*.pricing_scope.in' => __('validation.update_service.pricing_scope_in'),
            'prices.*.city_id.exists' => __('validation.update_service.price_city_exists'),
            'prices.*.area_id.exists' => __('validation.update_service.price_area_exists'),
            'prices.*.price_per_service_period.required_with' => __('validation.update_service.price_required'),
            'prices.*.price_per_service_period.numeric' => __('validation.update_service.price_numeric'),
            'prices.*.price_per_service_period.min' => __('validation.update_service.price_min'),
            'prices.*.currency.max' => __('validation.update_service.currency_max'),
            'prices.*.effective_start.date' => __('validation.update_service.effective_start_date'),
            'prices.*.effective_end.date' => __('validation.update_service.effective_end_date'),
            'prices.*.effective_end.after_or_equal' => __('validation.update_service.effective_end_after'),

            // الصور
            'image.image' => __('validation.update_service.image_invalid'),
            'image.mimes' => __('validation.update_service.image_mimes'),
            'image.max' => __('validation.update_service.image_max'),
        ];
    }
}
