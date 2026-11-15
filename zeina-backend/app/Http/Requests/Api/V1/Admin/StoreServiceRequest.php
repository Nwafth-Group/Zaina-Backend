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

    protected function prepareForValidation(): void
    {
        if ($this->has('workers') || array_key_exists('workers', $this->all())) {
            $workers = $this->input('workers');
            if (empty($workers) || $workers === '' || $workers === 'null' || $workers === '[]') {
                $this->merge(['workers' => []]);
            }
        }

        if ($this->has('teams') || array_key_exists('teams', $this->all())) {
            $teams = $this->input('teams');
            if (empty($teams) || $teams === '' || $teams === 'null' || $teams === '[]') {
                $this->merge(['teams' => []]);
            }
        }

        if ($this->has('service_zone_ids') || array_key_exists('service_zone_ids', $this->all())) {
            $zones = $this->input('service_zone_ids');
            if (empty($zones) || $zones === '' || $zones === 'null' || $zones === '[]') {
                $this->merge(['service_zone_ids' => []]);
            }
        }

        if ($this->has('child_age_groups') || array_key_exists('child_age_groups', $this->all())) {
            $groups = $this->input('child_age_groups');
            if (empty($groups) || $groups === '' || $groups === 'null' || $groups === '[]') {
                $this->merge(['child_age_groups' => []]);
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
            'base_price' => 'nullable|numeric|min:0|max:99999999.99',

            // Service Sub-Items / Options
            'options' => 'nullable|array',
            'options.*.name_ar' => 'required_with:options|string|max:100',
            'options.*.name_en' => 'required_with:options|string|max:100',
            'options.*.description_ar' => 'nullable|string|max:255',
            'options.*.description_en' => 'nullable|string|max:255',
            'options.*.unit_price' => 'required_with:options|numeric|min:0|max:99999999.99',
            'options.*.min_qty' => 'nullable|integer|min:0|max:999999',
            'options.*.max_qty' => 'nullable|integer|min:0|max:999999',
            'options.*.status' => 'nullable|in:active,inactive',

            // Service Image
            'image' => 'nullable|file|mimes:svg|max:2048',

            // Geo-Fenced Service Zones
            'service_zone_ids' => 'sometimes|nullable|array',
            'service_zone_ids.*' => 'integer|exists:service_zones,id',

            // Service Duration & Time
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i|after:start_time',
            'default_duration' => 'required|integer|min:1|max:1440',
            'has_duration_range' => 'nullable|boolean',
            'min_duration' => 'nullable|integer|min:1|max:1440',
            'max_duration' => 'nullable|integer|min:1|max:1440|gte:min_duration',
            'min_workers' => [
                Rule::requiredIf(fn () => ! $this->boolean('is_team_based')),
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
            'max_workers' => [
                Rule::requiredIf(fn () => ! $this->boolean('is_team_based')),
                'nullable',
                'integer',
                'min:1',
                'max:100',
                'gte:min_workers',
            ],
            'min_children' => 'nullable|integer|min:1|max:100',
            'max_children' => 'nullable|integer|min:1|max:100|gte:min_children',
            'covered_persons_per_worker' => 'nullable|integer|min:1|max:100',
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

        $isServiceWithoutShifts = Service::isServiceWithoutShifts($serviceCode, $categoryId ? (int)$categoryId : null);

        if ($isServiceWithoutShifts) {
            $rules['shifts'] = 'nullable|array';
            $rules['shifts.*.start_time'] = 'nullable';
            $rules['shifts.*.end_time'] = 'nullable';
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

                $categoryId = $this->input('category_id');
                $serviceCode = $this->input('service_code');
                $isServiceWithoutShifts = Service::isServiceWithoutShifts($serviceCode, $categoryId ? (int)$categoryId : null);

                // Only validate discrete shifts against service window if the service actually uses shifts
                if (! $isServiceWithoutShifts && $startTime && $endTime) {
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
                    if (is_array($workersInput) && !empty($workersInput)) {
                        $count = count($workersInput);
                        if ($minWorkers !== null && $maxWorkers !== null && (int)$minWorkers === (int)$maxWorkers && (int)$minWorkers > 0) {
                            if ($count !== (int)$minWorkers) {
                                $msg = $isAr
                                    ? "يجب أن يكون عدد العمال المسندين ({$count}) مساوياً للحد الأدنى والأقصى للعمال ({$minWorkers} عمال بالضبط)."
                                    : "Assigned workers count ({$count}) must be equal to min and max workers ({$minWorkers} exactly).";
                                $validator->errors()->add('workers', $msg);
                            }
                        } else {
                            if ($maxWorkers !== null && $maxWorkers > 0 && $count > $maxWorkers) {
                                $msg = $isAr
                                    ? "عدد العمال المحددين ({$count}) يتجاوز الحد الأقصى المسموح به للعمال لهذا الخدمة ({$maxWorkers})."
                                    : "Selected workers count ({$count}) exceeds the maximum allowed limit for this service ({$maxWorkers}).";
                                $validator->errors()->add('workers', $msg);
                            }
                            if ($minWorkers !== null && $minWorkers > 0 && $count < $minWorkers) {
                                $msg = $isAr
                                    ? "عدد العمال المحددين ({$count}) أقل من الحد الأدنى المسموح به للعمال لهذا الخدمة ({$minWorkers})."
                                    : "Selected workers count ({$count}) is less than the minimum required limit for this service ({$minWorkers}).";
                                $validator->errors()->add('workers', $msg);
                            }
                        }
                    }

                    // Check workers gender compatibility with the service
                    if (is_array($workersInput) && !empty($workersInput)) {
                        $selectedWorkers = \App\Models\Worker::whereIn('id', $workersInput)->get();
                        $allowedGenders = [];
                        if ($serviceCode === 'DEEP_CLEANING' || $this->boolean('is_team_based')) {
                            $allowedGenders = ['male'];
                        } elseif (in_array($serviceCode, ['DOMESTIC_HELPER_HOURLY', 'BABYSITTER_HOURLY', 'PEDICURE_MANICURE'], true)) {
                            $allowedGenders = ['female'];
                        } else {
                            $opts = $this->input('worker_gender_options', []);
                            $allowedGenders = !empty($opts) ? array_map('strtolower', (array) $opts) : ['male', 'female'];
                        }

                        $invalidWorkers = $selectedWorkers->filter(fn($w) => !in_array(strtolower($w->gender ?? ''), $allowedGenders, true));

                        if ($invalidWorkers->isNotEmpty()) {
                            $allowedAr = implode(' أو ', array_map(fn($g) => $g === 'female' ? 'إناث' : 'ذكور', $allowedGenders));
                            $allowedEn = implode(' or ', array_map('ucfirst', $allowedGenders));

                            $invalidNames = $invalidWorkers->map(function($w) use ($isAr) {
                                $gAr = strtolower($w->gender ?? '') === 'female' ? 'أنثى' : 'ذكر';
                                return "«{$w->name}» ({$gAr})";
                            })->implode('، ');

                            $validator->errors()->add(
                                'workers',
                                $isAr
                                    ? "هذه الخدمة مخصصة لعمالة ({$allowedAr}) فقط. العمال التالية لا تتطابق مع جنس الخدمة: {$invalidNames}."
                                    : "This service only accepts ({$allowedEn}) workers. The following workers do not match: {$invalidNames}."
                            );
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
                    if (!is_array($ageGroups)) {
                        $ageGroups = [];
                    }

                    $activeGroups = [];
                    foreach ($ageGroups as $origIdx => $group) {
                        if (($group['status'] ?? 'active') === 'active') {
                            $activeGroups[] = [
                                'orig_index' => $origIdx,
                                'name_ar' => $group['name_ar'] ?? '',
                                'name_en' => $group['name_en'] ?? '',
                                'from' => isset($group['age_from']) ? (int) $group['age_from'] : null,
                                'to' => isset($group['age_to']) ? (int) $group['age_to'] : null,
                            ];
                        }
                    }

                    if (empty($activeGroups)) {
                        $validator->errors()->add('child_age_groups', $isAr ? 'يجب تهيئة وتفعيل فئة عمرية واحدة للأطفال على الأقل لخدمة جليسة الأطفال.' : 'At least one active child age group must be configured for the Babysitter service.');
                    } else {
                        // 1. Validate that age_from <= age_to for each group
                        foreach ($activeGroups as $g) {
                            if ($g['from'] !== null && $g['to'] !== null && $g['from'] > $g['to']) {
                                $validator->errors()->add(
                                    "child_age_groups.{$g['orig_index']}.age_from",
                                    $isAr ? 'العمر (من) يجب أن يكون أقل من أو يساوي العمر (إلى).' : 'Age From must be less than or equal to Age To.'
                                );
                            }
                        }

                        // 2. Validate that no two active groups overlap
                        $hasOverlap = false;
                        $count = count($activeGroups);
                        for ($i = 0; $i < $count; $i++) {
                            for ($j = $i + 1; $j < $count; $j++) {
                                $gA = $activeGroups[$i];
                                $gB = $activeGroups[$j];

                                if ($gA['from'] !== null && $gA['to'] !== null && $gB['from'] !== null && $gB['to'] !== null) {
                                    if ($gA['from'] <= $gA['to'] && $gB['from'] <= $gB['to']) {
                                        // Overlap occurs if max(fromA, fromB) <= min(toA, toB)
                                        if ($gA['from'] <= $gB['to'] && $gB['from'] <= $gA['to']) {
                                            $hasOverlap = true;
                                            $msgB = $isAr
                                                ? "تداخل في الفئات العمرية: النطاق ({$gB['from']} إلى {$gB['to']} شهر) يتعارض مع النطاق ({$gA['from']} إلى {$gA['to']} شهر)."
                                                : "Age group overlap: range ({$gB['from']} to {$gB['to']} months) conflicts with range ({$gA['from']} to {$gA['to']} months).";

                                            $validator->errors()->add("child_age_groups.{$gB['orig_index']}.age_from", $msgB);
                                            $validator->errors()->add("child_age_groups.{$gB['orig_index']}.age_to", $msgB);
                                        }
                                    }
                                }
                            }
                        }

                        if ($hasOverlap) {
                            $validator->errors()->add(
                                'child_age_groups',
                                $isAr
                                    ? 'توجد فئات عمرية متداخلة في النطاق العمري بالأشهر. يجب ألا تتداخل الفئات العمرية مع بعضها البعض.'
                                    : 'There are overlapping age ranges in months. Child age groups must not overlap with each other.'
                            );
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

                // Services 1 to 5: base_price cannot be 0 or less
                $isServiceOneToFive = ($this->input('type') === 'hourly') || $isSecondService || $isThirdService || ($isPedicureManicure && $this->input('type') !== 'other') || in_array($serviceCode, [
                    'DOMESTIC_HELPER_HOURLY', 'HOSPITALITY_WORKER_HOURLY', 'BABYSITTER_HOURLY', 'PEDICURE_MANICURE', 'DEEP_CLEANING'
                ], true);

                if ($isServiceOneToFive) {
                    $basePrice = $this->input('base_price');
                    if ($basePrice === null || $basePrice === '' || (float) $basePrice <= 0) {
                        $validator->errors()->add(
                            'base_price',
                            $isAr ? 'السعر الأساسي يجب أن يكون أكبر من 0 للخدمات من 1 إلى 5 ولا يمكن أن يكون 0.' : 'The base price must be greater than 0 for services 1 to 5 and cannot be 0.'
                        );
                    }
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

            // 6. الأسعار والخيارات
            'base_price.numeric' => __('validation.create_service.base_price_numeric'),
            'base_price.min' => __('validation.create_service.base_price_min'),
            'base_price.max' => __('validation.create_service.base_price_max'),
            'options.*.unit_price.numeric' => __('validation.create_service.unit_price_numeric'),
            'options.*.unit_price.min' => __('validation.create_service.unit_price_min'),
            'options.*.unit_price.max' => __('validation.create_service.unit_price_max'),
        ];
    }

    public function attributes(): array
    {
        $isAr = app()->getLocale() === 'ar';

        return [
            'base_price' => $isAr ? 'السعر الأساسي' : 'base price',
            'options.*.unit_price' => $isAr ? 'سعر الوحدة' : 'unit price',
            'options.*.min_qty' => $isAr ? 'الحد الأدنى للكمية' : 'minimum quantity',
            'options.*.max_qty' => $isAr ? 'الحد الأقصى للكمية' : 'maximum quantity',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $isPedicure = ($this->input('service_code') === 'PEDICURE_MANICURE' || (int) $this->input('category_id') === 4);
            if ($isPedicure) {
                $personDuration = (int) $this->input('default_duration');
                $allowedHours = (int) ($this->input('max_duration') ?: 8);
                if ($personDuration > $allowedHours) {
                    $validator->errors()->add('default_duration', __('مدة خدمة الشخص لا يمكن أن تتجاوز ساعات دوام العاملة المسموحة.'));
                }
            }
        });
    }
}
