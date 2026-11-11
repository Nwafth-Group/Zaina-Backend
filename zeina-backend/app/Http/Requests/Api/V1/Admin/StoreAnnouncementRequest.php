<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use App\Http\Requests\Api\V1\CustomFormRequest;

class StoreAnnouncementRequest extends CustomFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare input data before validation.
     */
    protected function prepareForValidation(): void
    {
        $mergeData = [];

        if ($this->filled('title_ar') && ! $this->filled('title')) {
            $mergeData['title'] = $this->input('title_ar');
        } elseif ($this->filled('title') && ! $this->filled('title_ar')) {
            $mergeData['title_ar'] = $this->input('title');
        }

        if ($this->filled('title_en') && ! $this->filled('title')) {
            $mergeData['title'] = $this->input('title_en');
        } elseif ($this->filled('title') && ! $this->filled('title_en')) {
            $mergeData['title_en'] = $this->input('title');
        }

        if ($this->filled('body_ar') && ! $this->filled('body')) {
            $mergeData['body'] = $this->input('body_ar');
        } elseif ($this->filled('body') && ! $this->filled('body_ar')) {
            $mergeData['body_ar'] = $this->input('body');
        }

        if ($this->filled('body_en') && ! $this->filled('body')) {
            $mergeData['body'] = $this->input('body_en');
        } elseif ($this->filled('body') && ! $this->filled('body_en')) {
            $mergeData['body_en'] = $this->input('body');
        }

        if (! empty($mergeData)) {
            $this->merge($mergeData);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title_ar' => 'required|string|max:255',
            'title_en' => 'required|string|max:255',
            'body_ar' => 'required|string',
            'body_en' => 'required|string',
            'title' => 'nullable|string|max:255',
            'body' => 'nullable|string',
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
                $validator->errors()->add('customer_ids', __('validation.announcement.recipients_required'));
            }
        });
    }
}
