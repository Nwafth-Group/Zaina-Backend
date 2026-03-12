<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreServiceRequest extends FormRequest
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
            // 1. البيانات الأساسية
            'category_id'      => ['required', 'exists:categories,id'],
            'type'             => ['required', 'in:hourly,moqema,both'],
            'name_ar'          => ['required', 'string', 'max:100'],
            'name_en'          => ['required', 'string', 'max:100'],
            'description_ar'   => ['nullable', 'string'],
            'description_en'   => ['nullable', 'string'],
            'status'           => ['nullable', 'in:draft,active,hidden,inactive'],

            // 2. المدد (Durations)
            'duration'                        => ['required', 'array'],
            'duration.allowed_durations'      => ['required_if:type,hourly', 'array'],
            'duration.allowed_durations.*'    => ['numeric'], // مثل [2, 4, 6]
            'duration.min_duration_hours'     => ['required_if:type,hourly', 'numeric', 'lte:duration.allowed_durations.0'], // BR-03: يجب أن تكون أقل أو تساوي أصغر مدة مسموحة
            'duration.allowed_contract_durations' => ['required_if:type,moqema', 'array'],
            'duration.allowed_contract_durations.*' => ['numeric'], // مثل [30, 60, 90],
            'duration.min_contract_months' => ['required_if:type,moqema', 'numeric', 'lte:duration.allowed_contract_durations.0'], // BR-03: يجب أن تكون أقل أو تساوي أصغر مدة عقد مسموحة
            // 3. القواعد (Rules)
            'rules'                           => ['required', 'array'],
            'rules.free_cancel_cutoff_hours'  => ['required', 'integer', 'min:0'],
            'rules.reschedule_cutoff_hours'   => ['required', 'integer', 'min:0'],
            'rules.lead_time_min_hours'       => ['required', 'integer', 'min:0'],
            'rules.lead_time_max_days'        => ['required', 'integer', 'min:1'],
            'rules.late_cancel_action'        => ['required', 'in:fee,block,manual'],
            'rules.late_cancel_fee_type'      => ['required_if:rules.late_cancel_action,fee', 'in:percent,fixed'],
            'rules.late_cancel_fee_value'     => ['required_if:rules.late_cancel_action,fee', 'numeric', 'min:0'],
            'rules.no_show_policy'            => ['required', 'in:compensate,consumed'],
            'rules.max_changes_per_booking'   => ['required', 'integer', 'min:0'],

            // 4. التغطية الجغرافية (Coverage)
            'coverages'           => ['required', 'array', 'min:1'], // يجب أن تدعم منطقة واحدة على الأقل
            'coverages.*.city_id' => ['required', 'exists:cities,id'],
            'coverages.*.area_id' => ['nullable', 'exists:areas,id'], // null تعني كل المدينة
            'coverages.*.status'  => ['required', 'in:active,inactive,coming_soon'],
        ];
    }

    public function messages(){
        return [
            'category_id.exists' => __('validation.create_service.category_id_exists'),
            'type' => __('validation.create_service.type_required'),
            'name_ar.required' => __('validation.create_service.name_ar_required'),
            'name_en.required' => __('validation.create_service.name_en_required'),
            'duration.allowed_durations.required_if' => __('validation.create_service.allowed_durations_required_if'),
            'duration.min_duration_hours.required_if' => __('validation.create_service.min_duration_hours_required_if'),
            'duration.allowed_contract_durations.required_if' => __('validation.create_service.allowed_contract_durations_required_if'),
            'duration.min_contract_months.required_if' => __('validation.create_service.min_contract_months_required_if'),
            'rules.free_cancel_cutoff_hours.required' => __('validation.create_service.free_cancel_cutoff_hours_required'),
            'rules.reschedule_cutoff_hours.required' => __('validation.create_service.reschedule_cutoff_hours_required'),
            'rules.lead_time_min_hours.required' => __('validation.create_service.lead_time_min_hours_required'),
            'rules.lead_time_max_days.required' => __('validation.create_service.lead_time_max_days_required'),
            'rules.late_cancel_action.required' => __('validation.create_service.late_cancel_action_required'),
            'rules.late_cancel_fee_type.required_if' => __('validation.create_service.late_cancel_fee_type_required_if'),
            'rules.late_cancel_fee_value.required_if' => __('validation.create_service.late_cancel_fee_value_required_if'),
            'rules.no_show_policy.required' => __('validation.create_service.no_show_policy_required'),
            'rules.max_changes_per_booking.required' => __('validation.create_service.max_changes_per_booking_required'),
            'coverages.required' => __('validation.create_service.coverages_required'),
            'coverages.*.city_id.required' => __('validation.create_service.coverages_city_id_required'),
            'coverages.*.city_id.exists' => __('validation.create_service.coverages_city_id_exists'),
            'coverages.*.area_id.exists' => __('validation.create_service.coverages_area_id_exists'),
            'coverages.*.status.required' => __('validation.create_service.coverages_status_required'),
        ];
    }
}
