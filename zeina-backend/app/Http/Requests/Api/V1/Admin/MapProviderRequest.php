<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Http\Requests\Api\V1\CustomFormRequest;
class MapProviderRequest extends CustomFormRequest
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
        return [
            'service_ids'   => 'required|array|min:1', // BR-01: Must have at least one service
            'service_ids.*' => 'exists:services,id',
            'service_zone_ids'   => 'nullable|array',
            'service_zone_ids.*' => 'exists:service_zones,id',
        ];
    }
}
