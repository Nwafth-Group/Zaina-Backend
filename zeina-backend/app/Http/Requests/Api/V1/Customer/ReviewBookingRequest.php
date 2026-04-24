<?php

namespace App\Http\Requests\Api\V1\Customer;

use Illuminate\Foundation\Http\FormRequest;

class ReviewBookingRequest extends FormRequest
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
            'service_rating'   => 'required|integer|min:1|max:5',
            'service_comment'  => 'nullable|string|max:500',
            'workers'          => 'required|array',
            'workers.*.worker_id' => 'required|exists:workers,id',
            'workers.*.rating' => 'required|integer|min:1|max:5',
            'workers.*.comment'=> 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'service_rating.required'      => __('validation.review.service_rating_required'),
            'service_rating.integer'       => __('validation.review.service_rating_integer'),
            'service_rating.min'           => __('validation.review.service_rating_min'),
            'service_rating.max'           => __('validation.review.service_rating_max'),
            'service_comment.string'       => __('validation.review.service_comment_string'),
            'service_comment.max'          => __('validation.review.service_comment_max'),
            'workers.required'             => __('validation.review.workers_required'),
            'workers.array'                => __('validation.review.workers_array'),
            'workers.*.worker_id.required' => __('validation.review.worker_id_required'),
            'workers.*.worker_id.exists'   => __('validation.review.worker_id_exists'),
            'workers.*.rating.required'    => __('validation.review.worker_rating_required'),
            'workers.*.rating.integer'     => __('validation.review.worker_rating_integer'),
            'workers.*.rating.min'         => __('validation.review.worker_rating_min'),
            'workers.*.rating.max'         => __('validation.review.worker_rating_max'),
            'workers.*.comment.string'     => __('validation.review.worker_comment_string'),
            'workers.*.comment.max'        => __('validation.review.worker_comment_max'),
        ];
    }
}
