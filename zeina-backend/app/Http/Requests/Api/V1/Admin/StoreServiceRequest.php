<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Http\Requests\Api\V1\CustomFormRequest;
use Illuminate\Foundation\Http\FormRequest;

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

    public function messages(): array
    {
        return [
            // 1. البيانات الأساسية (Basic Data)
            'category_id.required'     => __('validation.create_service.category_id_required'),
            'category_id.exists'       => __('validation.create_service.category_id_exists'),
            
            'type.required'            => __('validation.create_service.type_required'),
            'type.in'                  => __('validation.create_service.type_in'),
            
            'name_ar.required'         => __('validation.create_service.name_ar_required'),
            'name_ar.string'           => __('validation.create_service.name_ar_string'),
            'name_ar.max'              => __('validation.create_service.name_ar_max'),
            
            'name_en.required'         => __('validation.create_service.name_en_required'),
            'name_en.string'           => __('validation.create_service.name_en_string'),
            'name_en.max'              => __('validation.create_service.name_en_max'),
            
            'description_ar.string'    => __('validation.create_service.description_ar_string'),
            'description_en.string'    => __('validation.create_service.description_en_string'),
            
            'status.in'                => __('validation.create_service.status_in'),

            // 2. المدد (Durations)
            'duration.required'                               => __('validation.create_service.duration_required'),
            'duration.array'                                  => __('validation.create_service.duration_array'),
            
            'duration.allowed_durations.required_if'          => __('validation.create_service.allowed_durations_required_if'),
            'duration.allowed_durations.array'                => __('validation.create_service.allowed_durations_array'),
            'duration.allowed_durations.*.numeric'            => __('validation.create_service.allowed_durations_numeric'),
            
            'duration.min_duration_hours.required_if'         => __('validation.create_service.min_duration_hours_required_if'),
            'duration.min_duration_hours.numeric'             => __('validation.create_service.min_duration_hours_numeric'),
            'duration.min_duration_hours.lte'                 => __('validation.create_service.min_duration_hours_lte'),
            
            'duration.allowed_contract_durations.required_if' => __('validation.create_service.allowed_contract_durations_required_if'),
            'duration.allowed_contract_durations.array'       => __('validation.create_service.allowed_contract_durations_array'),
            'duration.allowed_contract_durations.*.numeric'   => __('validation.create_service.allowed_contract_durations_numeric'),
            
            'duration.min_contract_months.required_if'        => __('validation.create_service.min_contract_months_required_if'),
            'duration.min_contract_months.numeric'            => __('validation.create_service.min_contract_months_numeric'),
            'duration.min_contract_months.lte'                => __('validation.create_service.min_contract_months_lte'),

            // 3. القواعد (Rules)
            'rules.required'                          => __('validation.create_service.rules_required'),
            'rules.array'                             => __('validation.create_service.rules_array'),
            
            'rules.free_cancel_cutoff_hours.required' => __('validation.create_service.free_cancel_cutoff_hours_required'),
            'rules.free_cancel_cutoff_hours.integer'  => __('validation.create_service.free_cancel_cutoff_hours_integer'),
            'rules.free_cancel_cutoff_hours.min'      => __('validation.create_service.free_cancel_cutoff_hours_min'),
            
            'rules.reschedule_cutoff_hours.required'  => __('validation.create_service.reschedule_cutoff_hours_required'),
            'rules.reschedule_cutoff_hours.integer'   => __('validation.create_service.reschedule_cutoff_hours_integer'),
            'rules.reschedule_cutoff_hours.min'       => __('validation.create_service.reschedule_cutoff_hours_min'),
            
            'rules.lead_time_min_hours.required'      => __('validation.create_service.lead_time_min_hours_required'),
            'rules.lead_time_min_hours.integer'       => __('validation.create_service.lead_time_min_hours_integer'),
            'rules.lead_time_min_hours.min'           => __('validation.create_service.lead_time_min_hours_min'),
            
            'rules.lead_time_max_days.required'       => __('validation.create_service.lead_time_max_days_required'),
            'rules.lead_time_max_days.integer'        => __('validation.create_service.lead_time_max_days_integer'),
            'rules.lead_time_max_days.min'            => __('validation.create_service.lead_time_max_days_min'),
            
            'rules.late_cancel_action.required'       => __('validation.create_service.late_cancel_action_required'),
            'rules.late_cancel_action.in'             => __('validation.create_service.late_cancel_action_in'),
            
            'rules.late_cancel_fee_type.required_if'  => __('validation.create_service.late_cancel_fee_type_required_if'),
            'rules.late_cancel_fee_type.in'           => __('validation.create_service.late_cancel_fee_type_in'),
            
            'rules.late_cancel_fee_value.required_if' => __('validation.create_service.late_cancel_fee_value_required_if'),
            'rules.late_cancel_fee_value.numeric'     => __('validation.create_service.late_cancel_fee_value_numeric'),
            'rules.late_cancel_fee_value.min'         => __('validation.create_service.late_cancel_fee_value_min'),
            
            'rules.no_show_policy.required'           => __('validation.create_service.no_show_policy_required'),
            'rules.no_show_policy.in'                 => __('validation.create_service.no_show_policy_in'),
            
            'rules.max_changes_per_booking.required'  => __('validation.create_service.max_changes_per_booking_required'),
            'rules.max_changes_per_booking.integer'   => __('validation.create_service.max_changes_per_booking_integer'),
            'rules.max_changes_per_booking.min'       => __('validation.create_service.max_changes_per_booking_min'),

            // 4. التغطية الجغرافية (Coverage)
            'coverages.required'           => __('validation.create_service.coverages_required'),
            'coverages.array'              => __('validation.create_service.coverages_array'),
            'coverages.min'                => __('validation.create_service.coverages_min'),
            
            'coverages.*.city_id.required' => __('validation.create_service.coverages_city_id_required'),
            'coverages.*.city_id.exists'   => __('validation.create_service.coverages_city_id_exists'),
            
            'coverages.*.area_id.exists'   => __('validation.create_service.coverages_area_id_exists'),
            
            'coverages.*.status.required'  => __('validation.create_service.coverages_status_required'),
            'coverages.*.status.in'        => __('validation.create_service.coverages_status_in'),
        ];
    }
}
