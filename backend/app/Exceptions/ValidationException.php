<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class ValidationException extends Exception
{
    public function __construct(
        string $message = 'Validation failed',
        int $code = 422,
        private readonly ?array $errors = null,
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

        if ($this->errors !== null) {
            $response['errors'] = $this->errors;
        }

        return new JsonResponse($response, $this->getCode());
    }

    public function report(): bool
    {
        return false;
    }
}
