<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Http\Requests\Api\V1\CustomFormRequest;
use App\Models\Category;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class StoreServiceRequest extends CustomFormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            // البيانات الأساسية
            'category_id' => 'nullable|exists:categories,id',
            'service_code' => 'nullable|string|max:100|unique:services,service_code',
            'type' => 'required|in:hourly,other',
            'worker_gender_options' => 'sometimes|required|array|min:1',
            'worker_gender_options.*' => 'required_with:worker_gender_options|string|in:Male,Female',
            'pricing_type' => 'nullable|string|in:per_hour,per_service',
            'is_team_based' => 'nullable|boolean',
            'name_ar' => [
                'required',
                'string',
                'max:100',
                Rule::unique('services', 'name_ar')->where('category_id', $this->category_id),
            ],
            'name_en' => [
                'required',
                'string',
                'max:100',
                Rule::unique('services', 'name_en')->where('category_id', $this->category_id),
            ],
            'description_ar' => 'required|string|max:500',
            'description_en' => 'required|string|max:500',
            'features' => 'nullable|array',
            'features.*.ar' => 'nullable|string|max:255',
            'features.*.en' => 'nullable|string|max:255',
            'status' => 'required|in:active,inactive',
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
            'available_visit_types' => 'required|array|min:1',
            'available_visit_types.*.type' => 'required|string|in:single_visit,3_days_per_week',
            'available_visit_types.*.discount' => 'nullable|numeric|min:0|max:100',
            'base_price' => 'nullable|numeric|min:0',

            // Service Sub-Items / Options
            'options' => 'nullable|array',
            'options.*.name_ar' => 'required_with:options|string|max:100',
            'options.*.name_en' => 'required_with:options|string|max:100',
            'options.*.description_ar' => 'nullable|string|max:255',
            'options.*.description_en' => 'nullable|string|max:255',
            'options.*.unit_price' => 'required_with:options|numeric|min:0',
            'options.*.min_qty' => 'nullable|integer|min:0',
            'options.*.max_qty' => 'nullable|integer',
            'options.*.status' => 'nullable|in:active,inactive',

            // Service Image
            'image' => 'nullable|file|mimes:svg|max:2048',

            // Geo-Fenced Service Zones
            'service_zone_ids' => 'sometimes|nullable|array',
            'service_zone_ids.*' => 'integer|exists:service_zones,id',

            // Service Duration & Time
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i|after:start_time',
            'default_duration' => 'required|integer|min:1',
            'has_duration_range' => 'nullable|boolean',
            'min_duration' => 'nullable|integer|min:1',
            'max_duration' => 'nullable|integer|min:1|gte:min_duration',
            'min_workers' => [
                Rule::requiredIf(fn () => ! $this->boolean('is_team_based')),
                'nullable',
                'integer',
                'min:1',
            ],
            'max_workers' => [
                Rule::requiredIf(fn () => ! $this->boolean('is_team_based')),
                'nullable',
                'integer',
                'min:1',
                'gte:min_workers',
            ],
            'min_children' => 'nullable|integer|min:1',
            'max_children' => 'nullable|integer|min:1|gte:min_children',
            'covered_persons_per_worker' => 'nullable|integer|min:1',
            'child_age_groups' => 'nullable|array',
            'child_age_groups.*.name_ar' => 'required_with:child_age_groups|string|max:100',
            'child_age_groups.*.name_en' => 'required_with:child_age_groups|string|max:100',
            'child_age_groups.*.age_from' => 'required_with:child_age_groups|integer|min:0',
            'child_age_groups.*.age_to' => 'required_with:child_age_groups|integer|min:0',
            'child_age_groups.*.calculation_type' => 'required_with:child_age_groups|in:per_child,ratio_based',
            'child_age_groups.*.children_count' => 'required_with:child_age_groups|integer|min:1',
            'child_age_groups.*.required_worker_count' => 'required_with:child_age_groups|integer|min:1',
            'child_age_groups.*.status' => 'required_with:child_age_groups|in:active,inactive',

            // Service Shifts & Schedules
            'working_days' => 'nullable|array|min:1',
            'working_days.*' => 'required_with:working_days|in:saturday,sunday,monday,tuesday,wednesday,thursday,friday',
            'shifts' => 'required_with:working_days|array|min:1',
            'shifts.*.start_time' => 'required_with:shifts|date_format:H:i',
            'shifts.*.end_time' => 'nullable|date_format:H:i',

            // Fallback for old schedules format in tests/seeders
            'schedules' => 'nullable|array|min:1',
            'schedules.*.day' => 'required_with:schedules|in:saturday,sunday,monday,tuesday,wednesday,thursday,friday',
            'schedules.*.start_time' => 'required_with:schedules|date_format:H:i',
            'schedules.*.end_time' => 'required_with:schedules|date_format:H:i',
            'schedules.*.is_active' => 'nullable|boolean',

            // Service Rules
            'rules' => 'nullable|array',
            'rules.allow_cancel' => 'nullable|boolean',
            'rules.free_cancel_cutoff_hours' => 'required_if:rules.allow_cancel,true|integer|min:0',
            'rules.allow_reschedule' => 'nullable|boolean',
            'rules.reschedule_cutoff_hours' => 'required_if:rules.allow_reschedule,true|integer|min:0',
            'rules.allow_same_day' => 'nullable|boolean',
            'rules.max_advance_booking_days' => 'nullable|integer|min:0',
            'rules.arrival_buffer_minutes' => 'nullable|integer|min:0',
            'rules.late_arrival_grace_minutes' => 'nullable|integer|min:0',

            // Terms & Conditions
            'terms_ar' => 'required|string|max:5000',
            'terms_en' => 'required|string|max:5000',
        ];

        $serviceCode = $this->input('service_code');
        $categoryId = $this->input('category_id');

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
                if ($this->filled('service_code')) {
                    $validator->errors()->add('service_code', 'The service code is generated automatically.');
                }
                $startTime = $this->input('start_time');
                $endTime = $this->input('end_time');
                $shifts = $this->input('shifts', []);
                $defaultDuration = (int) ($this->input('default_duration') ?? 1);

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
                $categoryId = $this->input('category_id');
                if (empty($categoryId) && $this->route('service')) {
                    $service = $this->route('service');
                    $categoryId = is_object($service) ? $service->category_id : Service::find($service)?->category_id;
                }

                $serviceCode = $this->input('service_code');
                if (empty($serviceCode) && $this->route('service')) {
                    $service = $this->route('service');
                    $serviceCode = is_object($service) ? $service->service_code : Service::find($service)?->service_code;
                }

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
                    $genders = $this->input('worker_gender_options', []);
                    if (empty($genders)) {
                        $validator->errors()->add('worker_gender_options', $isAr 
                            ? 'يجب اختيار خيار جنس واحد على الأقل لخدمة الضيافة.' 
                            : 'At least one worker gender option must be enabled for the Hourly Hospitality Worker service.');
                    }
                }

                if ($isSecondService || $isThirdService) {
                    $minWorkers = $this->input('min_workers');
                    $maxWorkers = $this->input('max_workers');

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

                    $minDuration = $this->input('min_duration');
                    $maxDuration = $this->input('max_duration');
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

                    $visitTypes = $this->input('available_visit_types', []);
                    foreach ($visitTypes as $index => $vt) {
                        if (isset($vt['type']) && $vt['type'] !== 'single_visit') {
                            $validator->errors()->add('available_visit_types', $isAr ? 'الزيارة المفردة هي الوحيدة المتاحة لهذه الخدمة.' : 'Only Single Visit is allowed for this service.');
                        }
                    }
                }

                if ($isThirdService) {
                    $minChildren = $this->input('min_children');
                    $maxChildren = $this->input('max_children');

                    if ($minChildren === null || $minChildren < 1) {
                        $validator->errors()->add('min_children', $isAr ? 'الحد الأدنى لعدد الأطفال يجب أن يكون 1 على الأقل.' : 'Minimum number of children must be at least 1.');
                    }
                    if ($minChildren !== null && $maxChildren !== null && $minChildren > $maxChildren) {
                        $validator->errors()->add('max_children', $isAr ? 'الحد الأقصى لعدد الأطفال يجب أن يكون أكبر من أو يساوي الحد الأدنى.' : 'Maximum number of children must be greater than or equal to minimum.');
                    }

                    $ageGroups = $this->input('child_age_groups', []);
                    $activeGroups = array_filter($ageGroups, fn ($g) => ($g['status'] ?? '') === 'active');

                    if (empty($activeGroups)) {
                        $validator->errors()->add('child_age_groups', $isAr ? 'يجب تهيئة وتفعيل فئة عمرية واحدة للأطفال على الأقل قبل النشر.' : 'At least one active child age group must be configured before publishing.');
                    } else {
                        // Check for overlaps: sort active groups by age_from
                        usort($activeGroups, fn ($a, $b) => ($a['age_from'] ?? 0) <=> ($b['age_from'] ?? 0));

                        for ($i = 0; $i < count($activeGroups); $i++) {
                            $current = $activeGroups[$i];
                            $from = (int) ($current['age_from'] ?? 0);
                            $to = (int) ($current['age_to'] ?? 0);

                            if ($from > $to) {
                                $validator->errors()->add("child_age_groups.{$i}.age_from", $isAr ? 'العمر من يجب أن يكون أقل من أو يساوي العمر إلى.' : 'Age From must be less than or equal to Age To.');
                            }
                        }
                    }
                }

                if ($isPedicureManicure) {
                    if ($this->filled('pricing_type') && $this->input('pricing_type') !== 'per_service') {
                        $validator->errors()->add('pricing_type', $isAr ? 'نوع التسعير لهذه الخدمة يجب أن يكون دائماً بالزيارة/الخدمة وليس بالساعة.' : 'For this service, the pricing type must always be per service.');
                    }
                    $genderOpts = $this->input('worker_gender_options', []);
                    if (empty($genderOpts)) {
                        $validator->errors()->add('worker_gender_options', $isAr ? 'خيار الجنس لهذه الخدمة يجب تحديده.' : 'Worker gender option must be selected.');
                    }
                } elseif ($this->filled('pricing_type') && ! $isSecondService && ! $isThirdService && $this->input('type') !== 'other' && ! $isPedicureManicure) {
                    $validator->errors()->add('pricing_type', $isAr ? 'تهيئة نوع التسعير متاحة فقط لخدمات الضيافة، وجليسة الأطفال، والخدمات الأخرى.' : 'Pricing type configuration is only allowed for the Hospitality, Babysitting, and Other services.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            // 1. البيانات الأساسية (Basic Data)
            'category_id.required' => __('validation.create_service.category_id_required'),
            'category_id.exists' => __('validation.create_service.category_id_exists'),
            'type.required' => __('validation.create_service.type_required'),
            'type.in' => __('validation.create_service.type_in'),
            'name_ar.required' => __('validation.create_service.name_ar_required'),
            'name_ar.string' => __('validation.create_service.name_ar_string'),
            'name_ar.max' => __('validation.create_service.name_ar_max'),
            'name_en.required' => __('validation.create_service.name_en_required'),
            'name_en.string' => __('validation.create_service.name_en_string'),
            'name_en.max' => __('validation.create_service.name_en_max'),
            'description_ar.string' => __('validation.create_service.description_ar_string'),
            'description_en.string' => __('validation.create_service.description_en_string'),
            'status.in' => __('validation.create_service.status_in'),
            'workers.required' => __('validation.create_service.workers_required'),
            'workers.array' => __('validation.create_service.workers_array'),
            'workers.min' => __('validation.create_service.workers_min'),
            'workers.*.integer' => __('validation.create_service.workers_integer'),
            'workers.*.exists' => __('validation.create_service.workers_exists'),
            // 2. القواعد (Rules)
            'rules.required' => __('validation.create_service.rules_required'),
            'rules.array' => __('validation.create_service.rules_array'),

            'rules.free_cancel_cutoff_hours.required' => __('validation.create_service.free_cancel_cutoff_hours_required'),
            'rules.free_cancel_cutoff_hours.integer' => __('validation.create_service.free_cancel_cutoff_hours_integer'),
            'rules.free_cancel_cutoff_hours.min' => __('validation.create_service.free_cancel_cutoff_hours_min'),

            'rules.reschedule_cutoff_hours.required' => __('validation.create_service.reschedule_cutoff_hours_required'),
            'rules.reschedule_cutoff_hours.integer' => __('validation.create_service.reschedule_cutoff_hours_integer'),
            'rules.reschedule_cutoff_hours.min' => __('validation.create_service.reschedule_cutoff_hours_min'),

            // 5. الصورة
            'image.image' => __('validation.create_category.image_image'),
            'image.mimes' => __('validation.create_category.image_mimes'),
            'image.max' => __('validation.create_category.image_max'),
        ];
    }
}
