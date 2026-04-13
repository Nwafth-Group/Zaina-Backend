<?php

namespace App\Http\Requests\Api\V1\Customer;

use Illuminate\Foundation\Http\FormRequest;

class GetAvailableWorker extends FormRequest
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
            'date'       => 'required|date_format:Y-m-d',
            'start_time' => 'required|date_format:H:i',
            'duration'   => 'required|integer|min:1',
        ];
    }

    public function messages(){ 
        return [
        'date.reqiured' => __('validation.getAvaliable.date_required'),
        'date.date_format' => __('validation.getAvaliable.date_format'),
        'start_time.required' => __('validation.getAvaliable.start_time_required'),
        'start_time.date_format' => __('validation.getAvaliable.start_time_format'),
        'duration.required' => __('validation.getAvaliable.duration_required'),
        'duration.integer' => __('validation.getAvaliable.duration_integer'),
        'duration.min' => __('validation.getAvaliable.duration_min'),
        ];
       
    }
}
