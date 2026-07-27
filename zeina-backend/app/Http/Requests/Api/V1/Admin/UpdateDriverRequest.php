<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDriverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('driver') ?? $this->route('id');
        return [
            'name'   => ['required', 'string', 'max:255'],
            'mobile' => ['required', 'string', 'max:30', 'unique:drivers,mobile,' . $id],
            'email'  => ['nullable', 'email', 'max:255'],
            'gender' => ['required', 'in:male,female'],
            'status' => ['required', 'in:active,inactive'],
            'image'  => ['nullable', 'image', 'max:5120'],
        ];
    }
}
