<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Http\Requests\Api\V1\CustomFormRequest;

class UpdateMobileNotificationRequest extends CustomFormRequest
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
            'title'        => ['required', 'string', 'max:255'],
            'title_en'     => ['nullable', 'string', 'max:255'],
            'content_en'   => ['nullable', 'string'],
            'title_ar'     => ['nullable', 'string', 'max:255'],
            'content_ar'   => ['nullable', 'string'],
            'audience'     => ['required', 'in:all,active_customers,inactive_customers'],
            'send_date'    => ['required', 'date', 'date_format:Y-m-d'],
            'send_time'    => ['required', 'date_format:H:i'],
            'platforms'    => ['required', 'array', 'min:1'],
            'platforms.*'  => ['required', 'in:ios,android'],
            'languages'    => ['required', 'array', 'min:1'],
            'languages.*'  => ['required', 'in:ar,en'],
        ];
    }

    /**
     * Add multilingual title/content dependency validation and past date protection checks.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $languages = $this->input('languages', []);
            
            if (in_array('ar', $languages, true)) {
                if (!$this->filled('title_ar')) {
                    $validator->errors()->add('title_ar', __('messages.notification_arabic_required'));
                }
                if (!$this->filled('content_ar')) {
                    $validator->errors()->add('content_ar', __('messages.notification_arabic_required'));
                }
            }

            if (in_array('en', $languages, true)) {
                if (!$this->filled('title_en')) {
                    $validator->errors()->add('title_en', __('messages.notification_english_required'));
                }
                if (!$this->filled('content_en')) {
                    $validator->errors()->add('content_en', __('messages.notification_english_required'));
                }
            }

            $sendDate = $this->input('send_date');
            $sendTime = $this->input('send_time');
            if ($sendDate && $sendTime) {
                try {
                    $scheduledAt = \Carbon\Carbon::parse($sendDate . ' ' . $sendTime);
                    if ($scheduledAt->isPast()) {
                        $validator->errors()->add('send_time', __('messages.notification_invalid_schedule'));
                    }
                } catch (\Exception $e) {
                    $validator->errors()->add('send_date', __('messages.notification_invalid_format'));
                }
            }
        });
    }
}
