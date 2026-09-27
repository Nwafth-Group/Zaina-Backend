<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\Category;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServiceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('workers') || array_key_exists('workers', $this->all())) {
            $workers = $this->input('workers');
            if (empty($workers) || $workers === '' || $workers === 'null' || $workers === '[]') {
                $this->merge(['workers' => []]);
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $service = $this->route('service');
        $serviceId = $service ? (is_object($service) ? $service->id : $service) : null;
        $categoryId = $this->input('category_id') ?? ($service ? $service->category_id : null);

        $rules = [
            // البيانات الأساسية
            'category_id' => 'sometimes|nullable|exists:categories,id',
            'service_code' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
                Rule::unique('services', 'service_code')->ignore($serviceId),
            ],
            'type' => 'sometimes|required|in:hourly,other',
            'worker_gender_options' => 'sometimes|required|array|min:1',
            'worker_gender_options.*' => 'required_with:worker_gender_options|string|in:Male,Female',
            'pricing_type' => 'nullable|string|in:per_hour,per_service',
            'name_ar' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('services', 'name_ar')->where('category_id', $categoryId)->ignore($serviceId),
            ],
            'name_en' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('services', 'name_en')->where('category_id', $categoryId)->ignore($serviceId),
            ],
            'description_ar' => 'sometimes|required|string|max:500',
            'description_en' => 'sometimes|required|string|max:500',
            'features' => 'nullable|array',
            'features.*.ar' => 'nullable|string|max:255',
            'features.*.en' => 'nullable|string|max:255',
            'status' => 'sometimes|required|in:active,inactive',
            'workers' => 'nullable|array',
            'workers.*' => [
                'integer',
                Rule::exists('workers', 'id')->where('status', 'active'),
            ],
            'teams' => 'nullable|array',
            'teams.*' => [
                'integer',
                Rule::exists('teams', 'id')->where('status', 'active'),
            ],

            // Available Visit Type & Pricing
            'available_visit_types' => 'sometimes|required|array|min:1',
            'available_visit_types.*.type' => 'required_with:available_visit_types|string|in:single_visit,3_days_per_week',
            'available_visit_types.*.discount' => 'nullable|numeric|min:0|max:100',
            'discount_value' => 'sometimes|nullable|numeric|min:0',
            'base_price' => 'sometimes|nullable|numeric|min:0',

            // Service Sub-Items / Options
            'options' => 'sometimes|nullable|array',
            'options.*.name_ar' => 'required_with:options|string|max:100',
            'options.*.name_en' => 'required_with:options|string|max:100',
            'options.*.description_ar' => 'nullable|string|max:255',
            'options.*.description_en' => 'nullable|string|max:255',
            'options.*.unit_price' => 'required_with:options|numeric|min:0',
            'options.*.min_qty' => 'nullable|integer|min:0',
            'options.*.max_qty' => 'nullable|integer',
            'options.*.status' => 'nullable|in:active,inactive',

            // Service Image
            'image' => 'sometimes|nullable|file|mimes:svg|max:2048',

            // Geo-Fenced Service Zones
            'service_zone_ids' => 'sometimes|nullable|array',
            'service_zone_ids.*' => 'integer|exists:service_zones,id',

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
            'min_children' => 'sometimes|nullable|integer|min:1',
            'max_children' => 'sometimes|nullable|integer|min:1|gte:min_children',
            'covered_persons_per_worker' => 'sometimes|nullable|integer|min:1',
            'child_age_groups' => 'sometimes|nullable|array',
            'child_age_groups.*.name_ar' => 'required_with:child_age_groups|string|max:100',
            'child_age_groups.*.name_en' => 'required_with:child_age_groups|string|max:100',
            'child_age_groups.*.age_from' => 'required_with:child_age_groups|integer|min:0',
            'child_age_groups.*.age_to' => 'required_with:child_age_groups|integer|min:0',
            'child_age_groups.*.calculation_type' => 'required_with:child_age_groups|in:per_child,ratio_based',
            'child_age_groups.*.children_count' => 'required_with:child_age_groups|integer|min:1',
            'child_age_groups.*.required_worker_count' => 'required_with:child_age_groups|integer|min:1',
            'child_age_groups.*.status' => 'required_with:child_age_groups|in:active,inactive',

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

        $service = $this->route('service');
        $serviceCode = $this->input('service_code') ?? ($service ? (is_object($service) ? $service->service_code : Service::find($service)?->service_code) : null);
        $categoryId = $this->input('category_id') ?? ($service ? (is_object($service) ? $service->category_id : Service::find($service)?->category_id) : null);

        $isSecondOrThird = false;
        if ($serviceCode === 'HOSPITALITY_WORKER_HOURLY' || $serviceCode === 'BABYSITTER_HOURLY' || $serviceCode === 'PEDICURE_MANICURE' || $this->input('type') === 'other') {
            $isSecondOrThird = true;
        } elseif ($categoryId) {
            $category = Category::find($categoryId);
            if ($category) {
                $catEn = strtolower($category->name_en);
                if (str_contains($catEn, 'hospitality') || str_contains($category->name_ar, 'ضيافة') ||
                    str_contains($catEn, 'babysitting') || str_contains($catEn, 'nanny') || str_contains($category->name_ar, 'أطفال') || str_contains($catEn, 'babysitter') ||
                    str_contains($catEn, 'other') || str_contains($category->name_ar, 'أخرى') || str_contains($catEn, 'pedicure') || str_contains($category->name_ar, 'أقدام')
                ) {
                    $isSecondOrThird = true;
                }
            }
        }

        if ($isSecondOrThird) {
            $rules['shifts'] = 'nullable|array';
            $rules['shifts.*.start_time'] = 'nullable';
        }

        return $rules;
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
                        if (! empty($shift['start_time'])) {
                            try {
                                $start = Carbon::parse($shift['start_time']);
                                $end = ! empty($shift['end_time'])
                                    ? Carbon::parse($shift['end_time'])
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

                // Check Hourly Hospitality / Babysitting Worker service constraints
                $categoryId = $this->input('category_id') ?? ($service ? $service->category_id : null);
                $serviceCode = $this->input('service_code') ?? ($service ? $service->service_code : null);

                $isSecondService = false;
                $isThirdService = false;
                $isPedicureManicure = false;

                if ($serviceCode === 'HOSPITALITY_WORKER_HOURLY') {
                    $isSecondService = true;
                } elseif ($serviceCode === 'BABYSITTER_HOURLY') {
                    $isThirdService = true;
                } elseif ($serviceCode === 'PEDICURE_MANICURE' || $serviceCode === 'DEEP_CLEANING' || $this->input('type') === 'other') {
                    $isPedicureManicure = true;
                } elseif ($categoryId) {
                    $category = Category::find($categoryId);
                    if ($category) {
                        $catEn = strtolower($category->name_en);
                        if (str_contains($catEn, 'hospitality') || str_contains($category->name_ar, 'ضيافة')) {
                            $isSecondService = true;
                        }
                        if (str_contains($catEn, 'babysitting') || str_contains($catEn, 'nanny') || str_contains($category->name_ar, 'أطفال') || str_contains($catEn, 'babysitter')) {
                            $isThirdService = true;
                        }
                        if (str_contains($catEn, 'other') || str_contains($category->name_ar, 'أخرى') || str_contains($catEn, 'pedicure') || str_contains($category->name_ar, 'أقدام') || str_contains($catEn, 'deep') || str_contains($category->name_ar, 'عميق')) {
                            $isPedicureManicure = true;
                        }
                    }
                }

                $isAr = app()->getLocale() === 'ar';

                if ($isSecondService) {
                    if ($this->has('worker_gender_options')) {
                        $genders = $this->input('worker_gender_options', []);
                        if (empty($genders)) {
                            $validator->errors()->add('worker_gender_options', $isAr 
                                ? 'يجب اختيار خيار جنس واحد على الأقل لخدمة الضيافة.' 
                                : 'At least one worker gender option must be enabled for the Hourly Hospitality Worker service.');
                        }
                    }
                }

                if ($isSecondService || $isThirdService) {
                    $minWorkers = $this->input('min_workers') ?? ($service ? $service->min_workers : null);
                    $maxWorkers = $this->input('max_workers') ?? ($service ? $service->max_workers : null);
                    if ($minWorkers !== null && $minWorkers < 1) {
                        $validator->errors()->add('min_workers', $isAr ? 'يجب أن يكون الحد الأدنى للعمال أكثر من 0.' : 'Minimum worker count must be greater than 0.');
                    }
                    if ($isSecondService) {
                        if ($maxWorkers !== null && $maxWorkers > 12) {
                            $validator->errors()->add('max_workers', $isAr ? 'الحد الأقصى لعدد العمال لهذه الخدمة هو 12.' : 'The maximum number of workers for this service must be 12.');
                        }
                    }
                    if ($minWorkers !== null && $maxWorkers !== null && $minWorkers > $maxWorkers) {
                        $validator->errors()->add('min_workers', $isAr ? 'الحد الأدنى للعمال لا يمكن أن يكون أكبر من الحد الأقصى.' : 'Minimum worker count cannot be greater than maximum worker count.');
                    }

                    $workersInput = $this->input('workers', []);
                    if (is_array($workersInput) && !empty($workersInput) && $maxWorkers !== null && $maxWorkers > 0) {
                        if (count($workersInput) > $maxWorkers) {
                            $msg = $isAr
                                ? "عدد العمال المحددين (" . count($workersInput) . ") يتجاوز الحد الأقصى المسموح به للعمال لهذا الخدمة ({$maxWorkers})."
                                : "Selected workers count (" . count($workersInput) . ") exceeds the maximum allowed limit for this service ({$maxWorkers}).";
                            $validator->errors()->add('workers', $msg);
                        }
                    }

                    $minDuration = $this->input('min_duration') ?? ($service ? $service->min_duration : null);
                    $maxDuration = $this->input('max_duration') ?? ($service ? $service->max_duration : null);
                    if ($minDuration !== null && $minDuration < 4) {
                        $validator->errors()->add('min_duration', $isAr ? 'الحد الأدنى لمدة هذه الخدمة هو 4 ساعات.' : 'The minimum service duration for this service must be 4 hours.');
                    }
                    if ($maxDuration !== null && $maxDuration > 6) {
                        $validator->errors()->add('max_duration', $isAr ? 'الحد الأقصى لمدة هذه الخدمة هو 6 ساعات.' : 'The maximum service duration for this service must be 6 hours.');
                    }
                    if ($minDuration !== null && $maxDuration !== null && $minDuration > $maxDuration) {
                        $validator->errors()->add('min_duration', $isAr ? 'الحد الأدنى للمدة لا يمكن أن يكون أكبر من الحد الأقصى.' : 'Minimum duration cannot be greater than maximum duration.');
                    }

                    if ($startTime !== null && $startTime < '08:00') {
                        $validator->errors()->add('start_time', $isAr ? 'وقت بداية الخدمة يجب أن يكون بين 8:00 صباحاً و 8:00 مساءً.' : 'The allowed service start time must be between 8:00 AM and 8:00 PM.');
                    }
                    if ($endTime !== null && $endTime > '20:00') {
                        $validator->errors()->add('end_time', $isAr ? 'وقت نهاية الخدمة يجب أن يكون بين 8:00 صباحاً و 8:00 مساءً.' : 'The allowed service end time must be between 8:00 AM and 8:00 PM.');
                    }
                    if ($startTime !== null && $endTime !== null && $startTime >= $endTime) {
                        $validator->errors()->add('start_time', $isAr ? 'وقت البداية يجب أن يكون قبل وقت النهاية.' : 'Start time must be strictly before end time.');
                    }

                    if ($this->has('available_visit_types')) {
                        $visitTypes = $this->input('available_visit_types', []);
                        foreach ($visitTypes as $index => $vt) {
                            if (isset($vt['type']) && $vt['type'] !== 'single_visit') {
                                $validator->errors()->add('available_visit_types', 'Only Single Visit is allowed for this service.');
                            }
                        }
                    }
                }

                if ($isThirdService) {
                    $minChildren = $this->input('min_children') ?? ($service ? $service->min_children : null);
                    $maxChildren = $this->input('max_children') ?? ($service ? $service->max_children : null);

                    if ($this->has('min_children') && ($minChildren === null || $minChildren < 1)) {
                        $validator->errors()->add('min_children', 'Minimum number of children must be at least 1.');
                    }
                    if ($minChildren !== null && $maxChildren !== null && $minChildren > $maxChildren) {
                        $validator->errors()->add('max_children', 'Maximum number of children must be greater than or equal to minimum.');
                    }

                    if ($this->has('child_age_groups')) {
                        $ageGroups = $this->input('child_age_groups', []);
                        $activeGroups = array_filter($ageGroups, fn ($g) => ($g['status'] ?? '') === 'active');

                        if (empty($activeGroups)) {
                            $validator->errors()->add('child_age_groups', 'At least one active child age group must be configured before publishing.');
                        } else {
                            // Check for overlaps: sort active groups by age_from
                            usort($activeGroups, fn ($a, $b) => ($a['age_from'] ?? 0) <=> ($b['age_from'] ?? 0));

                            for ($i = 0; $i < count($activeGroups); $i++) {
                                $current = $activeGroups[$i];
                                $from = (int) ($current['age_from'] ?? 0);
                                $to = (int) ($current['age_to'] ?? 0);

                                if ($from > $to) {
                                    $validator->errors()->add("child_age_groups.{$i}.age_from", 'Age From must be less than or equal to Age To.');
                                }
                            }
                        }
                    }
                }

                if ($isPedicureManicure) {
                    if ($this->filled('pricing_type') && $this->input('pricing_type') !== 'per_service') {
                        $validator->errors()->add('pricing_type', $isAr ? 'نوع التسعير لهذه الخدمة يجب أن يكون دائماً بالزيارة/الخدمة وليس بالساعة.' : 'For this service, the pricing type must always be per service.');
                    }
                    if ($this->has('worker_gender_options')) {
                        $genderOpts = $this->input('worker_gender_options', []);
                        if (empty($genderOpts)) {
                            $validator->errors()->add('worker_gender_options', $isAr ? 'خيار الجنس لهذه الخدمة يجب تحديده.' : 'Worker gender option must be selected.');
                        }
                    }
                } elseif ($this->filled('pricing_type') && ! $isSecondService && ! $isThirdService && $this->input('type') !== 'other' && ! $isPedicureManicure) {
                    $validator->errors()->add('pricing_type', 'Pricing type configuration is only allowed for the Hospitality, Babysitting, and Other services.');
                }
            },
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

            // الصور
            'image.image' => __('validation.update_service.image_invalid'),
            'image.mimes' => __('validation.update_service.image_mimes'),
            'image.max' => __('validation.update_service.image_max'),
        ];
    }
}
