<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Http\Requests\Api\V1\CustomFormRequest;
use Illuminate\Foundation\Http\FormRequest;

class StoreProviderRequest extends CustomFormRequest
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
    public function rules(): array {
        $providerId = $this->route('provider') ? $this->route('provider')->id : null;
        return [
            'fullname'      => 'required|string|min:2|max:100',
            'type'          => 'nullable|in:individual,company',
            'phone_number'   => 'required|string|unique:providers,phone_number,' . $providerId,
            'phone_code'     => 'nullable|string',
            'code'           => 'nullable|string',
            'gender'         => 'required|in:Male,Female',
            'status'         => 'required|in:active,inactive',
            'internal_notes' => 'nullable|string|max:1000',
        ];
    }

    
}
