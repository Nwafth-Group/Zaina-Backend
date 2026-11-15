<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\Category;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\ValidationRule;
use App\Http\Requests\Api\V1\CustomFormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UpdateServiceRequest extends CustomFormRequest
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
            'is_team_based' => 'sometimes|nullable|boolean',
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
            'worker_gender_options' => 'nullable|array',
            'worker_gender_options.*' => 'in:Male,Female,male,female',

            // Available Visit Type & Pricing
            'available_visit_types' => 'sometimes|required|array|min:1',
            'available_visit_types.*.type' => 'required_with:available_visit_types|string|in:single_visit,3_days_per_week',
            'available_visit_types.*.discount' => 'nullable|numeric|min:0|max:100',
            'base_price' => 'sometimes|nullable|numeric|min:0|max:99999999.99',

            // Service Sub-Items / Options
            'options' => 'sometimes|nullable|array',
            'options.*.name_ar' => 'required_with:options|string|max:100',
            'options.*.name_en' => 'required_with:options|string|max:100',
            'options.*.description_ar' => 'nullable|string|max:255',
            'options.*.description_en' => 'nullable|string|max:255',
            'options.*.unit_price' => 'required_with:options|numeric|min:0|max:99999999.99',
            'options.*.min_qty' => 'nullable|integer|min:0|max:999999',
            'options.*.max_qty' => 'nullable|integer|min:0|max:999999',
            'options.*.status' => 'nullable|in:active,inactive',

            // Service Image
            'image' => 'sometimes|nullable|file|mimes:svg|max:2048',

            // Geo-Fenced Service Zones
            'service_zone_ids' => 'sometimes|nullable|array',
            'service_zone_ids.*' => 'integer|exists:service_zones,id',

            // Service Duration & Time
            'start_time' => 'sometimes|nullable|date_format:H:i',
            'end_time' => 'sometimes|nullable|date_format:H:i|after:start_time',
            'default_duration' => 'sometimes|required|integer|min:1|max:1440',
            'has_duration_range' => 'sometimes|nullable|boolean',
            'min_duration' => 'sometimes|nullable|integer|min:1|max:1440',
            'max_duration' => 'sometimes|nullable|integer|min:1|max:1440|gte:min_duration',
            'min_workers' => [
                'sometimes',
                Rule::requiredIf(function () use ($service) {
                    $isTeamBased = $this->has('is_team_based')
                        ? $this->boolean('is_team_based')
                        : ($service instanceof Service ? $service->isTeamBased() : false);
                    return ! $isTeamBased;
                }),
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
            'max_workers' => [
                'sometimes',
                Rule::requiredIf(function () use ($service) {
                    $isTeamBased = $this->has('is_team_based')
                        ? $this->boolean('is_team_based')
                        : ($service instanceof Service ? $service->isTeamBased() : false);
                    return ! $isTeamBased;
                }),
                'nullable',
                'integer',
                'min:1',
                'max:100',
                'gte:min_workers',
            ],
            'min_children' => 'sometimes|nullable|integer|min:1|max:100',
            'max_children' => 'sometimes|nullable|integer|min:1|max:100|gte:min_children',
            'covered_persons_per_worker' => 'sometimes|nullable|integer|min:1|max:100',
            'child_age_groups' => 'sometimes|nullable|array',
            'child_age_groups.*.name_ar' => 'required_with:child_age_groups|string|max:100',
            'child_age_groups.*.name_en' => 'required_with:child_age_groups|string|max:100',
            'child_age_groups.*.age_from' => 'required_with:child_age_groups|integer|min:0',
            'child_age_groups.*.age_to' => 'required_with:child_age_groups|integer|min:0',
            'child_age_groups.*.calculation_type' => 'required_with:child_age_groups|in:per_child,ratio_based',
            'child_age_groups.*.children_count' => 'required_with:child_age_groups|integer|min:1',
            'child_age_groups.*.required_worker_count' => 'required_with:child_age_groups|integer|min:1',
            'child_age_groups.*.status' => 'required_with:child_age_groups|in:active,inactive',

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
            'rules.arrival_buffer_minutes' => 'nullable|integer|min:0',
            'rules.late_arrival_grace_minutes' => 'nullable|integer|min:0',

            // Terms & Conditions
            'terms_ar' => 'sometimes|required|string|max:5000',
            'terms_en' => 'sometimes|required|string|max:5000',
        ];

        $service = $this->route('service');
        if (is_numeric($service)) {
            $service = Service::find($service);
        }
        $serviceCode = $this->input('service_code') ?? ($service ? (is_object($service) ? $service->service_code : Service::find($service)?->service_code) : null);
        $categoryId = $this->input('category_id') ?? ($service ? (is_object($service) ? $service->category_id : Service::find($service)?->category_id) : null);

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
                $isAr = app()->getLocale() === 'ar';
                $service = $this->route('service');
                if (is_numeric($service)) {
                    $service = Service::find($service);
                }
                if ($this->has('service_code') && $service && $this->input('service_code') !== $service->service_code) {
                    $validator->errors()->add('service_code', $isAr ? 'لا يمكن تعديل كود الخدمة.' : 'The service code cannot be updated.');
                }
                $startTime = $this->input('start_time') ?? ($service ? $service->start_time : null);
                $endTime = $this->input('end_time') ?? ($service ? $service->end_time : null);
                $shifts = $this->input('shifts', []);
                $defaultDuration = (int) ($this->input('default_duration') ?? ($service ? $service->default_duration : 1));

                $categoryId = $this->input('category_id') ?? ($service ? $service->category_id : null);
                $serviceCode = $this->input('service_code') ?? ($service ? $service->service_code : null);
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
                                        $isAr
                                            ? "مواعيد الشفتات يجب أن تقع ضمن نافذة وقت طلب الخدمة ({$startTime} إلى {$endTime})."
                                            : "The shift schedule times must be within the service request window ({$startTime} to {$endTime})."
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

                if ($service && ($service->id == 2 || $service->service_code === 'HOSPITALITY_WORKER_HOURLY')) {
                    $isSecondService = true;
                }
                if ($service && ($service->id == 3 || $service->service_code === 'BABYSITTER_HOURLY')) {
                    $isThirdService = true;
                }

                if ($isSecondService) {
                    $genders = $this->input('worker_gender_options');
                    $genders = is_array($genders) ? array_filter($genders) : [];
                    if (empty($genders)) {
                        $validator->errors()->add('worker_gender_options', $isAr 
                            ? 'يجب اختيار جنس عمالة واحد على الأقل (ذكور أو إناث) لخدمة الضيافة.' 
                            : 'At least one worker gender option must be selected (Male or Female) for the Hourly Hospitality Worker service.');
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

                // Check if any selected worker is already assigned to another service
                $workersInput = $this->input('workers');
                $isTargetTeamBased = $this->has('is_team_based')
                    ? $this->boolean('is_team_based')
                    : ($service instanceof Service ? $service->isTeamBased() : false);

                if (is_array($workersInput) && !empty($workersInput) && !$isTargetTeamBased) {
                    $isSpecialized = in_array(strtoupper($serviceCode ?? ''), array_map('strtoupper', Service::getTeamBasedCodes()), true);

                    if ($isSpecialized) {
                        $conflictingStandard = DB::table('service_workers')
                            ->join('workers', 'workers.id', '=', 'service_workers.worker_id')
                            ->join('services', 'services.id', '=', 'service_workers.service_id')
                            ->whereIn('service_workers.worker_id', $workersInput)
                            ->whereNotIn('services.service_code', Service::getTeamBasedCodes())
                            ->where('service_workers.service_id', '!=', $service->id)
                            ->select(
                                'workers.id as worker_id',
                                'workers.name as worker_name',
                                'services.name_ar as service_name_ar',
                                'services.name_en as service_name_en'
                            )
                            ->get();

                        if ($conflictingStandard->isNotEmpty()) {
                            $details = $conflictingStandard->map(function ($cw) use ($isAr) {
                                $serviceName = $isAr ? ($cw->service_name_ar ?: $cw->service_name_en) : ($cw->service_name_en ?: $cw->service_name_ar);
                                return "«{$cw->worker_name}» (مسند لخدمة: {$serviceName})";
                            })->implode('، ');

                            $detailsEn = $conflictingStandard->map(function ($cw) {
                                $serviceName = $cw->service_name_en ?: $cw->service_name_ar;
                                return "\"{$cw->worker_name}\" (assigned to: {$serviceName})";
                            })->implode(', ');

                            $validator->errors()->add('workers', $isAr
                                ? "العمال التالية مسندون لخدمات بالساعة (1-5): {$details}، ولا يمكن إسنادهم لخدمات تخصصية."
                                : "The following worker(s) are assigned to hourly services (1-5): {$detailsEn}, and cannot be assigned to specialized services.");
                        }
                    } else {
                        $conflictingWorkers = DB::table('service_workers')
                            ->join('workers', 'workers.id', '=', 'service_workers.worker_id')
                            ->join('services', 'services.id', '=', 'service_workers.service_id')
                            ->whereIn('service_workers.worker_id', $workersInput)
                            ->where('service_workers.service_id', '!=', $service->id)
                            ->select(
                                'workers.id as worker_id',
                                'workers.name as worker_name',
                                'services.name_ar as service_name_ar',
                                'services.name_en as service_name_en'
                            )
                            ->get();

                        if ($conflictingWorkers->isNotEmpty()) {
                            $details = $conflictingWorkers->map(function ($cw) use ($isAr) {
                                $serviceName = $isAr ? ($cw->service_name_ar ?: $cw->service_name_en) : ($cw->service_name_en ?: $cw->service_name_ar);
                                return "«{$cw->worker_name}» (مسند لخدمة: {$serviceName})";
                            })->implode('، ');

                            $detailsEn = $conflictingWorkers->map(function ($cw) {
                                $serviceName = $cw->service_name_en ?: $cw->service_name_ar;
                                return "\"{$cw->worker_name}\" (assigned to: {$serviceName})";
                            })->implode(', ');

                            $validator->errors()->add('workers', $isAr
                                ? "لا يمكن حفظ التعديل: العمال التالية مسندون لخدمات أخرى بالفعل: {$details}. في الخدمات العادية، يمكن إسناد العامل لخدمة واحدة فقط."
                                : "Cannot save: the following worker(s) are already assigned to other services: {$detailsEn}. In standard services, each worker can only belong to one service.");
                        }
                    }
                }

                // Check workers gender compatibility with the service
                if (is_array($workersInput) && !empty($workersInput) && $service) {
                    $selectedWorkers = \App\Models\Worker::whereIn('id', $workersInput)->get();
                    $invalidWorkers = $selectedWorkers->filter(fn($w) => ! $service->acceptsGender($w->gender));

                    if ($invalidWorkers->isNotEmpty()) {
                        $allowedGenders = $service->getAllowedGenders();
                        $allowedAr = implode(' أو ', array_map(fn($g) => $g === 'Female' ? 'إناث' : 'ذكور', $allowedGenders));
                        $allowedEn = implode(' or ', $allowedGenders);

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
                        $validator->errors()->add('min_children', $isAr ? 'الحد الأدنى لعدد الأطفال يجب أن يكون 1 على الأقل.' : 'Minimum number of children must be at least 1.');
                    }
                    if ($minChildren !== null && $maxChildren !== null && $minChildren > $maxChildren) {
                        $validator->errors()->add('max_children', $isAr ? 'الحد الأقصى لعدد الأطفال يجب أن يكون أكبر من أو يساوي الحد الأدنى.' : 'Maximum number of children must be greater than or equal to minimum.');
                    }

                    $hasAgeGroupsInRequest = $this->has('child_age_groups') || array_key_exists('child_age_groups', $this->all());

                    if ($hasAgeGroupsInRequest) {
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
                    } else {
                        // If child_age_groups was not submitted, ensure the existing service has at least one active child age group
                        $existingActiveCount = $service ? $service->childAgeGroups()->where('status', 'active')->count() : 0;
                        if ($existingActiveCount === 0) {
                            $validator->errors()->add('child_age_groups', $isAr ? 'يجب تهيئة وتفعيل فئة عمرية واحدة للأطفال على الأقل لخدمة جليسة الأطفال.' : 'At least one active child age group must be configured for the Babysitter service.');
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
                    if ($this->filled('default_duration')) {
                        $personDuration = (int) $this->input('default_duration');
                        $allowedHours = (int) ($this->input('max_duration') ?: ($service->max_duration ?? 8));
                        if ($personDuration > $allowedHours) {
                            $validator->errors()->add('default_duration', $isAr ? 'مدة خدمة الشخص لا يمكن أن تتجاوز ساعات دوام العاملة المسموحة.' : 'Person service duration cannot exceed allowed worker hours.');
                        }
                    }
                } elseif ($this->filled('pricing_type') && ! $isSecondService && ! $isThirdService && $this->input('type') !== 'other' && ! $isPedicureManicure) {
                    $validator->errors()->add('pricing_type', $isAr ? 'تهيئة نوع التسعير متاحة فقط لخدمات الضيافة، وجليسة الأطفال، والخدمات الأخرى.' : 'Pricing type configuration is only allowed for the Hospitality, Babysitting, and Other services.');
                }

                // Services 1 to 5: base_price cannot be 0 or less
                $isServiceOneToFive = ($service && $service->id >= 1 && $service->id <= 5) || in_array($serviceCode, [
                    'DOMESTIC_HELPER_HOURLY', 'HOSPITALITY_WORKER_HOURLY', 'BABYSITTER_HOURLY', 'PEDICURE_MANICURE', 'DEEP_CLEANING'
                ], true) || ($this->input('type') === 'hourly');

                if ($isServiceOneToFive) {
                    if ($this->has('base_price')) {
                        $basePrice = $this->input('base_price');
                        if ($basePrice === null || $basePrice === '' || (float) $basePrice <= 0) {
                            $validator->errors()->add(
                                'base_price',
                                $isAr ? 'السعر الأساسي يجب أن يكون أكبر من 0 للخدمات من 1 إلى 5 ولا يمكن أن يكون 0.' : 'The base price must be greater than 0 for services 1 to 5 and cannot be 0.'
                            );
                        }
                    }
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

            // قواعد الخدمة
            'rules.array' => __('validation.update_service.rules_array'),
            'rules.free_cancel_cutoff_hours.integer' => __('validation.update_service.free_cancel_cutoff_hours_integer'),
            'rules.free_cancel_cutoff_hours.min' => __('validation.update_service.free_cancel_cutoff_hours_min'),
            'rules.reschedule_cutoff_hours.integer' => __('validation.update_service.reschedule_cutoff_hours_integer'),
            'rules.reschedule_cutoff_hours.min' => __('validation.update_service.reschedule_cutoff_hours_min'),

            // الصور
            'image.image' => __('validation.update_service.image_invalid'),
            'image.mimes' => __('validation.update_service.image_mimes'),
            'image.max' => __('validation.update_service.image_max'),

            // الأسعار والخيارات
            'base_price.numeric' => __('validation.update_service.base_price_numeric'),
            'base_price.min' => __('validation.update_service.base_price_min'),
            'base_price.max' => __('validation.update_service.base_price_max'),
            'options.*.unit_price.numeric' => __('validation.update_service.unit_price_numeric'),
            'options.*.unit_price.min' => __('validation.update_service.unit_price_min'),
            'options.*.unit_price.max' => __('validation.update_service.unit_price_max'),
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
}
