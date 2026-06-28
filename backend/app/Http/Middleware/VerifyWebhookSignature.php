<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Webhook\WebhookSignature;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyWebhookSignature
{
    public function __construct(
        private readonly WebhookSignature $signature,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $provider = $request->route('provider') ?? $request->input('provider', 'default');
        $verifier = WebhookSignature::fromConfig($provider);

        $isValid = match ($provider) {
            'stripe' => $verifier->verifyStripe($request),
            'resend' => $verifier->verifyResend($request),
            'github' => $verifier->verifyGitHub($request),
            default => $verifier->verify($request),
        };

        if (! $isValid) {
            return response()->json([
                'message' => 'Invalid webhook signature',
            ], 401);
        }

        return $next($request);
    }
}
