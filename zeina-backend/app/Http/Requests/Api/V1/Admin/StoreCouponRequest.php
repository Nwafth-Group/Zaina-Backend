<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreCouponRequest extends FormRequest
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
            'code' => 'required|string|unique:coupons,code',
            'type' => 'required|in:fixed,percentage',
            'value' => 'required|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'min_booking_amount' => 'nullable|numeric|min:0',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after:start_date',
            // محددات النطاق
            'is_for_all_users' => 'required|boolean',
            'users' => 'required_if:is_for_all_users,false|array', // مصفوفة IDs للمستخدمين
            'users.*' => 'exists:users,id',
            'is_for_all_services' => 'required|boolean',
            'services' => 'required_if:is_for_all_services,false|array', // مصفوفة IDs للخدمات
            'services.*' => 'exists:services,id',
        ];
    }
}
