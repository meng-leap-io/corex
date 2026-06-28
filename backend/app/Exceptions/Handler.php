<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException as LaravelAuthorizationException;
use Illuminate\Auth\AuthenticationException as LaravelAuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException as LaravelValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontReport = [
        AuthenticationException::class,
        AuthorizationException::class,
        ValidationException::class,
        NotFoundException::class,
        BusinessLogicException::class,
    ];

    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        $this->renderable(function (AuthenticationException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return $e->render();
            }
        });

        $this->renderable(function (AuthorizationException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return $e->render();
            }
        });

        $this->renderable(function (ValidationException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return $e->render();
            }
        });

        $this->renderable(function (NotFoundException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return $e->render();
            }
        });

        $this->renderable(function (BusinessLogicException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return $e->render();
            }
        });

        $this->renderable(function (IntegrationException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return $e->render();
            }
        });

        $this->renderable(function (LaravelAuthenticationException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return new JsonResponse([
                    'success' => false,
                    'message' => $e->getMessage() ?: 'Unauthenticated',
                    'code' => 401,
                ], 401);
            }
        });

        $this->renderable(function (LaravelAuthorizationException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return new JsonResponse([
                    'success' => false,
                    'message' => $e->getMessage() ?: 'Forbidden',
                    'code' => 403,
                ], 403);
            }
        });

        $this->renderable(function (ModelNotFoundException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Resource not found',
                    'code' => 404,
                ], 404);
            }
        });

        $this->renderable(function (NotFoundHttpException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return new JsonResponse([
                    'success' => false,
                    'message' => $e->getMessage() ?: 'Not found',
                    'code' => 404,
                ], 404);
            }
        });

        $this->renderable(function (LaravelValidationException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Validation failed',
                    'code' => 422,
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        $this->renderable(function (HttpException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return new JsonResponse([
                    'success' => false,
                    'message' => $e->getMessage() ?: 'Server error',
                    'code' => $e->getStatusCode(),
                ], $e->getStatusCode());
            }
        });

        $this->renderable(function (Throwable $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                $code = 500;
                $message = 'Internal server error';

                if ($e instanceof HttpException) {
                    $code = $e->getStatusCode();
                    $message = $e->getMessage() ?: 'Server error';
                }

                return new JsonResponse([
                    'success' => false,
                    'message' => $message,
                    'code' => $code,
                ], $code);
            }
        });
    }
}
