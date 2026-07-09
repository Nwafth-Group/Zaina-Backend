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
        $workerId = $this->route('worker') instanceof \App\Models\Worker 
            ? $this->route('worker')->id 
            : $this->route('worker');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone_number' => ['sometimes', 'required', 'string', 'unique:workers,phone_number,' . $workerId, 'max:255'],
            'nationality' => ['sometimes', 'required', 'string', 'max:255'],
            'id_number' => ['sometimes', 'required', 'string', 'unique:workers,id_number,' . $workerId, 'max:255'],
            'address' => ['sometimes', 'required', 'string'],
            'gender' => ['sometimes', 'required', 'string', 'in:male,female'],
            'status' => ['sometimes', 'nullable', 'in:active,inactive,suspended'],
            'services' => ['sometimes', 'nullable', 'array', 'max:1'],
            'services.*' => ['exists:services,id'],
            'image' => ['sometimes', 'nullable', 'image', 'mimes:jpeg,png,jpg,gif,svg', 'max:2048'],
        ];
    }

    public function messages()
    {
        return [
            'name.required' => 'Please fill all required worker information before saving.',
            'nationality.required' => 'Please fill all required worker information before saving.',
            'phone_number.required' => 'Please fill all required worker information before saving.',
            'phone_number.unique' => 'Phone number is already used by another worker.',
            'id_number.required' => 'Please fill all required worker information before saving.',
            'id_number.unique' => 'ID number is already used by another worker.',
            'address.required' => 'Please fill all required worker information before saving.',
            'gender.required' => 'Please fill all required worker information before saving.',
            'services.max' => 'This worker is already assigned to a service. A worker can only be assigned to one service.',
            'status.in' => __('validation.store_worker.status_in'),
            'services.array' => __('validation.store_worker.services_array'),
            'services.*.exists' => __('validation.store_worker.services_exists'),
            'image.image' => __('validation.store_worker.image_image'),
            'image.mimes' => __('validation.store_worker.image_mimes'),
            'image.max' => __('validation.store_worker.image_max'),
        ];
    }
}
