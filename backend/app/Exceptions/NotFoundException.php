<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class NotFoundException extends Exception
{
    public function __construct(
        string $message = 'Resource not found',
        int $code = 404,
        private readonly ?string $resourceType = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function render(): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
        ];

        if ($this->resourceType !== null) {
            $response['resource'] = $this->resourceType;
        }

        return new JsonResponse($response, $this->getCode());
    }

    public function report(): void
    {
        Log::info($this->getMessage(), [
            'exception' => get_class($this),
            'resource_type' => $this->resourceType,
            'code' => $this->getCode(),
        ]);
    }
}
