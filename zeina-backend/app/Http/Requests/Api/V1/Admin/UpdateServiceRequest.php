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
            'category_id'      => 'sometimes|required|exists:categories,id',
            'type'             => 'sometimes|required|in:hourly,moqema,both',
            'name_ar'          => 'sometimes|required|string|max:100',
            'name_en'          => 'sometimes|required|string|max:100',
            'description_ar'   => 'nullable|string',
            'description_en'   => 'nullable|string',
            'features'         => 'nullable|array',
            'status'           => 'sometimes|required|in:draft,active,hidden,inactive',
            
            // 🌟 المدد الزمنية (هنا استخدمنا sometimes كدرع حماية رئيسي) 🌟
            'durations'                            => 'sometimes|array',
            'durations.allowed_durations'          => 'sometimes|required_if:type,hourly,both|array',
            'durations.min_duration_hours'         => 'sometimes|required_if:type,hourly,both|integer|min:1',
            'durations.allowed_contract_durations' => 'sometimes|required_if:type,moqema,both|array',
            'durations.min_contract_months'        => 'sometimes|required_if:type,moqema,both|integer|min:1',

            'workers'         => 'sometimes|required|array|min:1',
            'workers.*'       => 'integer|exists:workers,id',


            // قواعد الخدمة
            'rules'                           => 'sometimes|array',
            'rules.free_cancel_cutoff_hours'  => 'nullable|integer|min:0',
            'rules.reschedule_cutoff_hours'   => 'nullable|integer|min:0',
            'rules.lead_time_min_hours'       => 'nullable|integer|min:0',
            'rules.lead_time_max_days'        => 'nullable|integer|min:1',
            'rules.late_cancel_action'        => 'nullable|in:fee,block,manual',
            'rules.late_cancel_fee_type'      => 'nullable|in:percent,fixed',
            'rules.late_cancel_fee_value'     => 'nullable|numeric|min:0',
            'rules.no_show_policy'            => 'nullable|in:compensate,consumed',
            'rules.max_changes_per_booking'   => 'nullable|integer|min:0',

            // التغطية الجغرافية 
            'coverages'             => 'sometimes|required|array|min:1',
            'coverages.*.city_id'   => 'required_with:coverages|exists:cities,id',
            'coverages.*.area_id'   => 'nullable|exists:areas,id',
            'coverages.*.status'    => 'nullable|in:active,inactive,coming_soon',

            // التسعير
            'prices'                  => 'sometimes|required|array|min:1',
            'prices.*.pricing_scope'  => 'required_with:prices|in:city,area',
            'prices.*.city_id'        => 'nullable|exists:cities,id',
            'prices.*.area_id'        => 'nullable|exists:areas,id',
            'prices.*.base_price'     => 'required_with:prices|numeric|min:0',
            'prices.*.price_per_hour' => 'required_with:prices|numeric|min:0',
            'prices.*.currency'       => 'nullable|string|max:10',
            'prices.*.effective_start'=> 'nullable|date',
            'prices.*.effective_end'  => 'nullable|date|after_or_equal:prices.*.effective_start',
            
            // الصور
            'image'  => 'sometimes|nullable|image|mimes:jpeg,png,jpg,webp,svg|max:2048',
            // 'cover' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:4096',
        ];
    }



}
