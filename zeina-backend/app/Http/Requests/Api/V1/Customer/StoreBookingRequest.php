<?php

namespace App\Http\Requests\Api\V1\Customer;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
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
            'service_id'    => 'required|exists:services,id',
            'address_id'    => 'required|exists:customer_addresses,id',
            'date'          => 'required|date_format:Y-m-d',
            'start_time'    => 'required|date_format:H:i',
            
            // 🌟 التعديلات الجديدة 🌟
            'periods_count' => 'required|integer|min:1', // عدد الفترات المطلوبة
            'worker_ids'    => 'required|array|min:1',   // مصفوفة العمال المختارين
            'worker_ids.*'  => 'required|exists:workers,id',
        ];
    }

    public function messages(): array
    {
        return [
            'service_id.required'    => __('validation.create_booking.service_id_required'),
            'service_id.exists'      => __('validation.create_booking.service_id_exists'),
            'address_id.required'    => __('validation.create_booking.address_id_required'),
            'address_id.exists'      => __('validation.create_booking.address_id_exists'),
            'date.required'          => __('validation.create_booking.date_required'),
            'date.date_format'       => __('validation.create_booking.date_date_format'),
            'start_time.required'    => __('validation.create_booking.start_time_required'),
            'start_time.date_format' => __('validation.create_booking.start_time_date_format'),
            
            // رسائل التعديلات الجديدة (يمكنك إضافتها لملف الترجمة لاحقاً)
            'periods_count.required' => __('validation.create_booking.periods_count_required'),
            'periods_count.integer'  => __('validation.create_booking.periods_count_integer'),
            'periods_count.min'      => __('validation.create_booking.periods_count_min'),
            'worker_ids.required'    => __('validation.create_booking.worker_ids_required'),
            'worker_ids.array'       => __('validation.create_booking.worker_ids_array'),
            'worker_ids.min'         => __('validation.create_booking.worker_ids_min'),
            'worker_ids.*.exists'    => __('validation.create_booking.worker_ids_exists'),
        ];
    }
}