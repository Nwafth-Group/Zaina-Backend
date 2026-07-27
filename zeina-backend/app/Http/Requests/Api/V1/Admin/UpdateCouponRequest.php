<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Http\Requests\Api\V1\CustomFormRequest;
use Illuminate\Validation\Rule;

class UpdateCouponRequest extends CustomFormRequest
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
        $coupon = $this->route('coupon');
        $couponId = $coupon ? (is_object($coupon) ? $coupon->id : $coupon) : null;

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('coupons', 'name')->ignore($couponId)],
            'code' => ['required', 'string', 'max:10', 'alpha_dash', Rule::unique('coupons', 'code')->ignore($couponId)],
            'type' => ['required', 'in:fixed,percentage'],
            'value' => [
                'required',
                'numeric',
                'min:0.01',
                function ($attribute, $value, $fail) {
                    if ($this->input('type') === 'percentage' && $value > 100) {
                        $fail(__('validation.percentage_max_value') ?: 'Percentage discount cannot exceed 100%.');
                    }
                },
            ],
            'start_date' => ['required', 'date'], // Note: during update, we may allow editing a coupon that started in the past.
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],

            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'user_usage_limit' => [
                'required',
                'integer',
                'min:1',
                function ($attribute, $value, $fail) {
                    $usageLimit = $this->input('usage_limit');
                    if (!is_null($usageLimit) && $value > $usageLimit) {
                        $fail('The user usage limit field must not be greater than ' . $usageLimit . '.');
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
}
