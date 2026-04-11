<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkerRequest extends FormRequest
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
           'name' => ['sometimes','required' ,'string', 'max:255'],
           'nationality' => ['sometimes','required' ,'string', 'max:255'],
           'status' => ['sometimes','nullable' , 'in:active,inactive'],
           'services' => ['sometimes','nullable', 'array'],
           'services.*' => ['exists:services,id'],
           'image' => ['sometimes','nullable', 'image', 'mimes:jpeg,png,jpg,gif,svg', 'max:2048'],
        ];
    }

    public function messages(){
        return [
          'name.required' => __('validation.store_worker.name_required'),
          'name.string' => __('validation.store_worker.name_string'),
          'name.max' => __('validation.store_worker.name_max'),
          'nationality.required' => __('validation.store_worker.nationality_required'),
          'nationality.string' => __('validation.store_worker.nationality_string'),
          'nationality.max' => __('validation.store_worker.nationality_max'),
          'status.in' => __('validation.store_worker.status_in'),
            'services.array' => __('validation.store_worker.services_array'),
            'services.*.exists' => __('validation.store_worker.services_exists'),
            'image.image' => __('validation.store_worker.image_image'),
            'image.mimes' => __('validation.store_worker.image_mimes'),
            'image.max' => __('validation.store_worker.image_max'),

        ];
    }
}
