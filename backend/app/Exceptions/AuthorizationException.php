<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class AuthorizationException extends Exception
{
    public function __construct(
        string $message = 'Forbidden',
        int $code = 403,
        private readonly ?array $context = null,
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

        if ($this->context !== null) {
            $response['context'] = $this->context;
        }

        return new JsonResponse($response, $this->getCode());
    }

    public function report(): void
    {
        Log::warning($this->getMessage(), [
            'exception' => get_class($this),
            'code' => $this->getCode(),
            'context' => $this->context,
        ]);
    }
}
