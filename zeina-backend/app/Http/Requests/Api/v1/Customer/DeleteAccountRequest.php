<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Http\Requests\Api\V1\CustomFormRequest;
use Illuminate\Foundation\Http\FormRequest;

class DeleteAccountRequest extends CustomFormRequest
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
            'otp'          => ['required', 'numeric', 'digits:6'],
            'reason'       => ['nullable','string','in:no_longer_need,data_concern,dissatisfied,complicated,prefer_not,other'],
            'other_reason' => ['required_if:reason,other','string','max:250'], 
            'confirm'      => ['required','accepted'] // للتأكد أنه قام بوضع علامة (صح) على التحذير
        ];
    }

    public function messages(): array
    {
        return [
            'otp.required' => __('validation.delete_account.otp_required'),
            'otp.numeric' => __('validation.delete_account.otp_numeric'),
            'otp.digits' => __('validation.delete_account.otp_digits'),
            'reason.string' => __('validation.delete_account.reason_string'),
            'reason.in' => __('validation.delete_account.reason_in'),
            'other_reason.required_if' => __('validation.delete_account.other_reason_required_if'),
            'other_reason.string' => __('validation.delete_account.other_reason_string'),
            'other_reason.max' => __('validation.delete_account.other_reason_max'),
            'confirm.required' => __('validation.delete_account.confirm_required'),
            'confirm.accepted' => __('validation.delete_account.confirm_accepted'),
        ];
    }
}
