<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Http\Requests\Api\V1\CustomFormRequest;

class StoreCouponRequest extends CustomFormRequest
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
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:coupons,name'],
            'code' => ['required', 'string', 'min:2', 'max:30', 'alpha_dash', 'unique:coupons,code'],
            'type' => ['required', 'in:fixed,percentage'],
            'value' => [
                'required',
                'numeric',
                'min:0.01',
                function ($attribute, $value, $fail) {
                    if ($this->input('type') === 'percentage' && $value > 100) {
                        $fail(__('validation.coupon.percentage_max_value'));
                    }
                },
            ],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],

            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'user_usage_limit' => [
                'required',
                'integer',
                'min:1',
                function ($attribute, $value, $fail) {
                    $usageLimit = $this->input('usage_limit');
                    if (!is_null($usageLimit) && $value > $usageLimit) {
                        $fail(__('validation.coupon.user_usage_limit_max', ['limit' => $usageLimit]));
                    }
                },
            ],

            'is_for_all_users' => ['required', 'boolean'],
            'users' => ['required_if:is_for_all_users,false', 'array'],
            'users.*' => ['exists:users,id'],

            'is_for_all_cities' => ['required', 'boolean'],
            'service_zone_ids' => ['required_if:is_for_all_cities,false', 'array'],
            'service_zone_ids.*' => ['exists:service_zones,id'],
            'cities' => ['nullable', 'array'],

            'completed_bookings_count' => ['nullable', 'integer', 'min:0'],
            'min_workers' => ['nullable', 'integer', 'min:0'],

            'is_for_all_services' => ['required', 'boolean'],

            // Validation when all services are selected but restricted by visit type/shifts
            'allowed_visit_types' => ['nullable', 'array'],

            // Validation when specific services are selected
            'services' => ['required_if:is_for_all_services,false', 'array'],
            'services.*.id' => ['required_with:services', 'exists:services,id'],
            'services.*.allowed_visit_types' => ['nullable', 'array'],
            'services.*.min_workers' => ['nullable', 'integer', 'min:0'],

            'status' => ['required', 'in:active,inactive'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => __('validation.coupon.name_required'),
            'name.string' => __('validation.coupon.name_string'),
            'name.max' => __('validation.coupon.name_max'),
            'name.unique' => __('validation.coupon.name_unique'),
            'code.required' => __('validation.coupon.code_required'),
            'code.string' => __('validation.coupon.code_string'),
            'code.min' => __('validation.coupon.code_min'),
            'code.max' => __('validation.coupon.code_max'),
            'code.alpha_dash' => __('validation.coupon.code_alpha_dash'),
            'code.unique' => __('validation.coupon.code_unique'),
            'type.required' => __('validation.coupon.type_required'),
            'type.in' => __('validation.coupon.type_in'),
            'value.required' => __('validation.coupon.value_required'),
            'value.numeric' => __('validation.coupon.value_numeric'),
            'value.min' => __('validation.coupon.value_min'),
            'start_date.required' => __('validation.coupon.start_date_required'),
            'start_date.date' => __('validation.coupon.start_date_date'),
            'start_date.after_or_equal' => __('validation.coupon.start_date_after_or_equal'),
            'end_date.required' => __('validation.coupon.end_date_required'),
            'end_date.date' => __('validation.coupon.end_date_date'),
            'end_date.after_or_equal' => __('validation.coupon.end_date_after_or_equal'),
            'usage_limit.integer' => __('validation.coupon.usage_limit_integer'),
            'usage_limit.min' => __('validation.coupon.usage_limit_min'),
            'user_usage_limit.required' => __('validation.coupon.user_usage_limit_required'),
            'user_usage_limit.integer' => __('validation.coupon.user_usage_limit_integer'),
            'user_usage_limit.min' => __('validation.coupon.user_usage_limit_min'),
            'status.required' => __('validation.coupon.status_required'),
            'status.in' => __('validation.coupon.status_in'),
        ];
    }
}
