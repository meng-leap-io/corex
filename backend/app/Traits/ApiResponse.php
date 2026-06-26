<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\Log;

trait ApiResponse
{
    protected function success(
        JsonResource|ResourceCollection|array|null $data = null,
        ?string $message = null,
        int $code = 200,
    ): JsonResponse {
        $response = [];

        if ($message) {
            $response['message'] = $message;
        }

        if (is_null($data)) {
            $response['data'] = null;
        } elseif ($data instanceof JsonResource || $data instanceof ResourceCollection) {
            return $data->additional(array_filter(['message' => $message]))
                ->response()
                ->setStatusCode($code);
        } else {
            $response['data'] = $data;
        }

        return response()->json($response, $code);
    }

    protected function error(string $message, int $code = 400, ?array $errors = null): JsonResponse
    {
        $response = ['message' => $message];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }

    protected function notFound(?string $resource = 'Resource'): JsonResponse
    {
        return $this->error("{$resource} not found.", 404);
    }

    protected function unauthenticated(?string $message = null): JsonResponse
    {
        return $this->error($message ?? 'Unauthenticated.', 401);
    }

    protected function forbidden(?string $message = null): JsonResponse
    {
        return $this->error($message ?? 'Forbidden.', 403);
    }

    protected function validationError(array $errors): JsonResponse
    {
        return $this->error('Validation failed.', 422, $errors);
    }

    protected function logAndError(
        string $logMessage,
        string $userMessage,
        \Throwable $e,
        int $code = 500,
        array $context = [],
    ): JsonResponse {
        Log::error($logMessage, array_merge([
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ], $context));

        return $this->error($userMessage, $code);
    }
}
