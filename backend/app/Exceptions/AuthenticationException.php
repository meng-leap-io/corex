<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class AuthenticationException extends Exception
{
    public function __construct(
        string $message = 'Authentication failed',
        int $code = 401,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function render(): JsonResponse
    {
        return new JsonResponse([
            'success' => false,
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
        ], $this->getCode());
    }

    public function report(): void
    {
        Log::warning($this->getMessage(), [
            'exception' => get_class($this),
            'code' => $this->getCode(),
        ]);
    }
}
