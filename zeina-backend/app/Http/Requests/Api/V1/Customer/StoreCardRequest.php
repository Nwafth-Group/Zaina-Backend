<?php

namespace App\Http\Requests\Api\V1\Customer;

use Illuminate\Foundation\Http\FormRequest;

class StoreCardRequest extends FormRequest
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
            'card_token' => 'required|string',
            'last_four' => 'required|string|size:4',
            'card_brand' => 'required|string',
            'card_holder_name' => 'nullable|string',
        ];
    }

    public function messages(){
        return [
            'card_token.required' => __('validation.card.card_token_required'),
            'last_four.required' => __('validation.card.last_four_required'),
            'last_four.size' => __('validation.card.last_four_size'),
            'card_brand.required' => __('validation.card.card_brand_required'),
        ];
    }
}
