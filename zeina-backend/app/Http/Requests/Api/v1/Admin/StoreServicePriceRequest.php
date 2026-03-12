<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreServicePriceRequest extends FormRequest
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
            'service_id'    => 'required|integer|exists:services,id',
            'pricing_scope' => 'required|in:city,area',
            // إذا كان النطاق منطقة، يجب إرسال منطقة موجودة، وإلا يجب إرسال مدينة
            'city_id'       => 'required_if:pricing_scope,city|integer|exists:cities,id',
            'area_id'       => 'required_if:pricing_scope,area|integer|exists:areas,id',
            'base_price'    => 'required|numeric|min:0',
            'price_per_hour'=> 'required|numeric|min:1', // سعر الساعة يجب أن يكون أكبر من 0
            'currency'      => 'required|string|size:3', // مثل EGP
            'effective_start' => 'nullable|date|after_or_equal:today',
            'effective_end'   => 'nullable|date|after:effective_start',
        ];
    }

    public function messages(){
        return [
            'service_id.required' => __('validation.store_service_price.service_id_required'),
            'service_id.exists' => __('validation.store_service_price.service_id_exists'),
            'pricing_scope.required' => __('validation.store_service_price.pricing_scope_required'),
            'pricing_scope.in' => __('validation.store_service_price.pricing_scope_in'),
            'city_id.required_if' => __('validation.store_service_price.city_id_required_if'),
            'city_id.exists' => __('validation.store_service_price.city_id_exists'),
            'area_id.required_if' => __('validation.store_service_price.area_id_required_if'),
            'area_id.exists' => __('validation.store_service_price.area_id_exists'),
            'base_price.required' => __('validation.store_service_price.base_price_required'),
            'base_price.numeric' => __('validation.store_service_price.base_price_numeric'),
            'base_price.min' => __('validation.store_service_price.base_price_min'),
            'price_per_hour.required' => __('validation.store_service_price.price_per_hour_required'),
            'price_per_hour.numeric' => __('validation.store_service_price.price_per_hour_numeric'),
            'price_per_hour.min' => __('validation.store_service_price.price_per_hour_min'),
            'currency.required' => __('validation.store_service_price.currency_required'),
            'currency.string' => __('validation.store_service_price.currency_string'),
            'currency.size' => __('validation.store_service_price.currency_size'),
            'effective_start.date' => __('validation.store_service_price.effective_start_date'),
            'effective_start.after_or_equal' => __('validation.store_service_price.effective_start_after_or_equal'),
            'effective_end.date' => __('validation.store_service_price.effective_end_date'),
            'effective_end.after' => __('validation.store_service_price.effective_end_after'),

        ];
    }
}
