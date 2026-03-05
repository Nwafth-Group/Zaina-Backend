<?php

namespace App\Traits;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\AbstractPaginator;

trait ApiResponseTrait
{
    /**
     * Send a success response.
     */
    protected function successResponse($data, ?string $message = null, int $statusCode = 200, ?array $params = null): JsonResponse
    {
        $message = $message ?? __('auth.success');

        $finalResponse = [
            'status'  => $statusCode,
            'message' => $message,
        ];

        if ($data instanceof JsonResource && $data->resource instanceof AbstractPaginator) {
            $paginatedData = $data->response()->getData(true);

            $finalResponse['data'] = [
                'items' => $paginatedData['data'],
                'links' => $paginatedData['links'],
                'meta'  => $paginatedData['meta'],
            ];
        }
        else if ($data instanceof ResourceCollection) {
            $finalResponse['data'] = [
                'items' => $data
            ];
        }
        else if ($data instanceof JsonResource) {
            $finalResponse['data'] = $data;
        }
        else {
            $finalResponse['data'] = $data;
        }

        if ($params) {
            $finalResponse['params'] = $params;
        }

        return response()->json($finalResponse, $statusCode);
    }

    /**
     * Send an error response (Instance method).
     */
    protected function errorResponse(?string $message = null, int $statusCode = 500)
    {
        $message = $message ?? __('auth.something_went_wrong');

        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null
        ], $statusCode);
    }

    /**
     * Send an error response (Static method).
     */
    public static function handlingErrorResponse(?string $message = null, int $statusCode = 500)
    {
        $message = $message ?? __('auth.something_went_wrong');

        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null
        ], $statusCode);
    }

    /**
     * Handle failed validation.
     */
    public static function handlingFailedValidation($validator)
    {
        $errorMessages = $validator->errors();

        $params = collect($errorMessages)->map(function ($messages, $key) {
            return $messages[0];
        });

        throw new HttpResponseException(response()->json([
            'status' => 422,
            'success' => false,
            'message' => __('auth.validation_failed'),
            'data' => null,
            'params' => $params,
        ], 422));
    }
}
