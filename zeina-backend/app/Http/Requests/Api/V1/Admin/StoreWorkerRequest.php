<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Http\Requests\Api\V1\CustomFormRequest;

class StoreWorkerRequest extends CustomFormRequest
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone_number' => ['required', 'string', 'unique:workers,phone_number', 'max:255'],
            'nationality' => ['required', 'string', 'max:255'],
            'id_number' => ['required', 'string', 'unique:workers,id_number', 'max:255'],
            'address' => ['required', 'string'],
            'gender' => ['required', 'string', 'in:male,female'],
            'status' => ['nullable', 'in:active,inactive,suspended'],
            'team_id' => ['nullable', 'exists:teams,id'],
            'services' => ['nullable', 'array'],
            'services.*' => ['exists:services,id'],
            'work_days' => ['nullable', 'array', 'min:1'],
            'work_days.*' => ['string', 'in:saturday,sunday,monday,tuesday,wednesday,thursday,friday'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,svg', 'max:2048'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Check team_id: Teams only male
            if ($this->filled('team_id')) {
                if (strtolower($this->input('gender', '')) !== 'male') {
                    $validator->errors()->add('team_id', __('validation.store_worker.team_male_only'));
                }
            }

            // Check services assignment rules
            $serviceIds = $this->input('services', []);
            if (!empty($serviceIds) && is_array($serviceIds)) {
                $services = \App\Models\Service::whereIn('id', $serviceIds)->get();
                $teamCodes = \App\Models\Service::getTeamBasedCodes();

                $nonTeamServices = $services->filter(fn($s) => !in_array($s->service_code, $teamCodes));
                $teamServices = $services->filter(fn($s) => in_array($s->service_code, $teamCodes));

                // If any non-team service (Services 1-5) is selected, max allowed service is 1
                if ($nonTeamServices->isNotEmpty() && $services->count() > 1) {
                    $validator->errors()->add(
                        'services',
                        __('validation.store_worker.services_single_allowed')
                    );
                }

                // Check worker gender matches service allowed genders
                $workerGender = $this->input('gender', '');
                if (!empty($workerGender)) {
                    $isAr = app()->getLocale() === 'ar';
                    foreach ($services as $srv) {
                        if (! $srv->acceptsGender($workerGender)) {
                            $allowedAr = implode(' أو ', array_map(fn($g) => $g === 'Female' ? 'إناث' : 'ذكور', $srv->getAllowedGenders()));
                            $allowedEn = implode(' or ', $srv->getAllowedGenders());
                            $workerGenderAr = strtolower($workerGender) === 'female' ? 'أنثى' : 'ذكر';

                            $msg = $isAr
                                ? "الخدمة ({$srv->name_ar}) مخصصة للعمالة ({$allowedAr}) فقط، ولا يمكن إسناد عامل من جنس ({$workerGenderAr}) إليها."
                                : "The service ({$srv->name_en}) only accepts ({$allowedEn}) workers, and cannot be assigned to a ({$workerGender}) worker.";

                            $validator->errors()->add('services', $msg);
                        }
                    }
                }
            }

            // Check status inactive/suspended when assigned to services
            if ($this->has('status') && in_array($this->input('status'), ['inactive', 'suspended'])) {
                $isAr = app()->getLocale() === 'ar';
                $assignedDirectServices = collect();
                $serviceIds = $this->input('services', []);
                if (is_array($serviceIds) && !empty($serviceIds)) {
                    $assignedDirectServices = \App\Models\Service::whereIn('id', $serviceIds)->get();
                }

                $assignedTeamServices = collect();
                if ($this->filled('team_id')) {
                    $team = \App\Models\Team::find($this->input('team_id'));
                    $assignedTeamServices = $team ? $team->services()->get() : collect();
                }

                $allAssignedServices = $assignedDirectServices->concat($assignedTeamServices)->unique('id');

                if ($allAssignedServices->isNotEmpty()) {
                    $serviceNames = $allAssignedServices->map(fn($s) => $isAr ? ($s->name_ar ?: $s->name_en) : ($s->name_en ?: $s->name_ar))->join('، ');
                    $msg = $isAr
                        ? "لا يمكن تعيين حالة العامل إلى غير نشط لأنه مسند إلى خدمة: ({$serviceNames}). يرجى إلغاء إسناد العامل من الخدمة أولاً."
                        : "Cannot set worker status to inactive because they are assigned to service: ({$serviceNames}). Please unassign the worker from the service first.";

                    $validator->errors()->add('status', $msg);
                }
            }
        });
    }

    public function messages()
    {
        return [
            'name.required' => __('validation.store_worker.name_required'),
            'nationality.required' => __('validation.store_worker.nationality_required'),
            'phone_number.required' => __('validation.store_worker.phone_number_required'),
            'phone_number.unique' => __('validation.store_worker.phone_number_unique'),
            'id_number.required' => __('validation.store_worker.id_number_required'),
            'id_number.unique' => __('validation.store_worker.id_number_unique'),
            'address.required' => __('validation.store_worker.address_required'),
            'gender.required' => __('validation.store_worker.gender_required'),
            'gender.in' => __('validation.store_worker.gender_in'),
            'services.max' => __('validation.store_worker.services_max'),
            'status.in' => __('validation.store_worker.status_in'),
            'services.array' => __('validation.store_worker.services_array'),
            'services.*.exists' => __('validation.store_worker.services_exists'),
            'image.image' => __('validation.store_worker.image_image'),
            'image.mimes' => __('validation.store_worker.image_mimes'),
            'image.max' => __('validation.store_worker.image_max'),
        ];
    }
}
