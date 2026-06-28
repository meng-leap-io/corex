<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class IntegrationException extends Exception
{
    public function __construct(
        string $message = 'External service error',
        int $code = 502,
        private readonly ?string $service = null,
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

        if ($this->service !== null) {
            $response['service'] = $this->service;
        }

        return new JsonResponse($response, $this->getCode());
    }

    public function report(): void
    {
        Log::error($this->getMessage(), [
            'exception' => get_class($this),
            'service' => $this->service,
            'code' => $this->getCode(),
            'previous' => $this->getPrevious()?->getMessage(),
        ]);
    }
}
