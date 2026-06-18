<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAnnouncementRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            // Recipients: either target every customer (send_to_all) or a list of customer IDs.
            'send_to_all' => 'nullable|boolean',
            'customer_ids' => 'nullable|array',
            'customer_ids.*' => [
                'integer',
                Rule::exists('users', 'id')->where('type', 'customer'),
            ],
        ];
    }

    /**
     * Require at least one recipient when not broadcasting to all customers.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->boolean('send_to_all') && empty($this->input('customer_ids'))) {
                $validator->errors()->add('customer_ids', __('validation.required'));
            }
        });
    }
}
