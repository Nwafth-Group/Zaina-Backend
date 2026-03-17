<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Http\Requests\Api\V1\CustomFormRequest;
use Illuminate\Foundation\Http\FormRequest;

class CustomerRegisterRequest extends CustomFormRequest
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
            'first_name' => ['required','string','max:30'],
            'last_name' => ['required','string','max:30'],
            'mobile_number' => ['required','numeric','unique:users,phone_number'],
            'preferred_language' =>['required','in:en,ar'],
            'terms_accepted' => ['required','boolean','accepted']
        ];
    }
}
