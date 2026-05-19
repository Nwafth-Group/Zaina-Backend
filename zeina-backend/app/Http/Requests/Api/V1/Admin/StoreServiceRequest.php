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
            // البيانات الأساسية
            'category_id'      => 'required|exists:categories,id',
            'type'             => 'required|in:hourly,moqema,both',
            'name_ar'          => 'required|string|max:100',
            'name_en'          => 'required|string|max:100',
            'description_ar'   => 'nullable|string',
            'description_en'   => 'nullable|string',
            'features'         => 'nullable|array',
            'status'           => 'required|in:draft,active,hidden,inactive',
            'workers'         => 'required|array|min:1',
            'workers.*'       => 'integer|exists:workers,id',
            // المدد الزمنية (Service Durations) - بناءً على نوع الخدمة
            // 'durations.allowed_durations'          => 'required_if:type,hourly,both|array',
            // 'durations.min_duration_hours'         => 'required_if:type,hourly,both|integer|min:1',
            'durations.service_period_hours' => 'required_if:type,hourly,both|integer|min:1',
            'durations.allowed_contract_durations' => 'required_if:type,moqema,both|array',
            'durations.min_contract_months'        => 'required_if:type,moqema,both|integer|min:1',
            // قواعد الخدمة (Service Rules)
            'rules.free_cancel_cutoff_hours'  => 'nullable|integer|min:0',
            'rules.reschedule_cutoff_hours'   => 'nullable|integer|min:0',
            'rules.lead_time_min_hours'       => 'nullable|integer|min:0',
            'rules.lead_time_max_days'        => 'nullable|integer|min:1',
            'rules.late_cancel_action'        => 'nullable|in:fee,block,manual',
            'rules.late_cancel_fee_type'      => 'nullable|in:percent,fixed',
            'rules.late_cancel_fee_value'     => 'nullable|numeric|min:0',
            'rules.no_show_policy'            => 'nullable|in:compensate,consumed',
            'rules.max_changes_per_booking'   => 'nullable|integer|min:0',

            // التغطية الجغرافية (Coverages) - مصفوفة
            'coverages'             => 'required|array|min:1',
            'coverages.*.city_id'   => 'required|exists:cities,id',
            'coverages.*.area_id'   => 'nullable|exists:areas,id',
            'coverages.*.status'    => 'nullable|in:active,inactive,coming_soon',

            // التسعير (Prices) - مصفوفة
            'prices'                  => 'required|array|min:1',
            'prices.*.pricing_scope'  => 'required|in:city,area',
            'prices.*.city_id'        => 'nullable|exists:cities,id',
            'prices.*.area_id'        => 'nullable|exists:areas,id',
            'prices.*.price_per_service_period'     => 'required|numeric|min:0',
            // 'prices.*.price_per_hour' => 'required|numeric|min:0',
            'prices.*.currency'       => 'nullable|string|max:10',
            'prices.*.effective_start'=> 'nullable|date',
            'prices.*.effective_end'  => 'nullable|date|after_or_equal:prices.*.effective_start',
            
            // الصور (اختياري)
            'image'  => 'nullable|image|mimes:jpeg,png,jpg,webp,svg|max:2048',
            // 'cover' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:4096',
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
            'workers.required'         => __('validation.create_service.workers_required'),
            'workers.array'            => __('validation.create_service.workers_array'),
            'workers.min'              => __('validation.create_service.workers_min'),
            'workers.*.integer'       => __('validation.create_service.workers_integer'),
            'workers.*.exists'        => __('validation.create_service.workers_exists'),
            // 2. المدد (Durations)
            'duration.required'                               => __('validation.create_service.duration_required'),
            'duration.array'                                  => __('validation.create_service.duration_array'),
                // 'duration.allowed_durations.required_if'          => __('validation.create_service.allowed_durations_required_if'),
                // 'duration.allowed_durations.array'                => __('validation.create_service.allowed_durations_array'),
                // 'duration.allowed_durations.*.numeric'            => __('validation.create_service.allowed_durations_numeric'),
                
                // 'duration.min_duration_hours.required_if'         => __('validation.create_service.min_duration_hours_required_if'),
                // 'duration.min_duration_hours.numeric'             => __('validation.create_service.min_duration_hours_numeric'),
                // 'duration.min_duration_hours.lte'                 => __('validation.create_service.min_duration_hours_lte'),
            'duration.service_period_hours.required_if'        => __('validation.create_service.service_period_hours_required_if'),
            'duration.service_period_hours.integer'           => __('validation.create_service.service_period_hours_integer'),
            'duration.service_period_hours.min'               => __('validation.create_service.service_period_hours_min'),
            
                
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

            // 5. الصورة 
            'image.image' => __('validation.create_category.image_image'),
            'image.mimes' => __('validation.create_category.image_mimes'),
            'image.max' => __('validation.create_category.image_max'),
        ];
    }
}
