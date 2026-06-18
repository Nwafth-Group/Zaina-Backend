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
        return [
            // البيانات الأساسية (ضفنا sometimes عشان يقبل يتعدل حقل واحد بس لو لزم الأمر)
            'category_id' => 'sometimes|required|exists:categories,id',
            'type' => 'sometimes|required|in:hourly,moqema,both',
            'name_ar' => 'sometimes|required|string|max:100',
            'name_en' => 'sometimes|required|string|max:100',
            'description_ar' => 'nullable|string',
            'description_en' => 'nullable|string',
            'features' => 'nullable|array',
            'features.*.ar' => 'nullable|string|max:255',
            'features.*.en' => 'nullable|string|max:255',
            'status' => 'sometimes|required|in:draft,active,hidden,inactive',

            // 🌟 المدد الزمنية (هنا استخدمنا sometimes كدرع حماية رئيسي) 🌟
            'durations' => 'sometimes|array',
            // 'durations.allowed_durations'          => 'sometimes|required_if:type,hourly,both|array',
            // 'durations.min_duration_hours'         => 'sometimes|required_if:type,hourly,both|integer|min:1',
            'durations.service_period_hours' => 'sometimes|required_if:type,hourly,both|integer|min:1',
            'durations.allowed_contract_durations' => 'sometimes|required_if:type,moqema,both|array',
            'durations.min_contract_months' => 'sometimes|required_if:type,moqema,both|integer|min:1',

            'workers' => 'sometimes|nullable|array',
            'workers.*' => 'integer|exists:workers,id',

            // قواعد الخدمة
            'rules' => 'sometimes|array',
            'rules.free_cancel_cutoff_hours' => 'nullable|integer|min:0',
            'rules.reschedule_cutoff_hours' => 'nullable|integer|min:0',
            'rules.lead_time_min_hours' => 'nullable|integer|min:0',
            'rules.lead_time_max_days' => 'nullable|integer|min:1',
            'rules.late_cancel_action' => 'nullable|in:fee,block,manual',
            'rules.late_cancel_fee_type' => 'nullable|in:percent,fixed',
            'rules.late_cancel_fee_value' => 'nullable|numeric|min:0',
            'rules.no_show_policy' => 'nullable|in:compensate,consumed',
            'rules.max_changes_per_booking' => 'nullable|integer|min:0',

            // التغطية الجغرافية
            'coverages' => 'sometimes|required|array|min:1',
            'coverages.*.city_id' => 'required_with:coverages|exists:cities,id',
            'coverages.*.area_id' => 'nullable|exists:areas,id',
            'coverages.*.status' => 'nullable|in:active,inactive,coming_soon',

            // التسعير
            'prices' => 'sometimes|required|array|min:1',
            'prices.*.pricing_scope' => 'required_with:prices|in:city,area',
            'prices.*.city_id' => 'nullable|exists:cities,id',
            'prices.*.area_id' => 'nullable|exists:areas,id',
            'prices.*.price_per_service_period' => 'required_with:prices|numeric|min:0',
            'prices.*.currency' => 'nullable|string|max:10',
            'prices.*.effective_start' => 'nullable|date',
            'prices.*.effective_end' => 'nullable|date|after_or_equal:prices.*.effective_start',

            // جدول العمل الأسبوعي (Weekly schedule)
            'schedules' => 'sometimes|nullable|array',
            'schedules.*.day' => 'required_with:schedules|in:saturday,sunday,monday,tuesday,wednesday,thursday,friday',
            'schedules.*.start_time' => 'required_with:schedules|date_format:H:i',
            'schedules.*.end_time' => 'required_with:schedules|date_format:H:i',
            'schedules.*.is_active' => 'nullable|boolean',

            // الصور
            'image' => 'sometimes|nullable|image|mimes:jpeg,png,jpg,webp,svg|max:2048',
            // 'cover' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:4096',
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
