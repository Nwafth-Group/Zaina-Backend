<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Http\Requests\Api\V1\CustomFormRequest;

class DepositWalletRequest extends CustomFormRequest
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
            'amount' => ['required','numeric'],
        ];
    }

    public function messages(){
        return [
            'amount.required' => __('validation.deposit.amount_required'),
            'amount.numeric' => __('validation.deposit.amount_numeric'),
        ];
    }
}
