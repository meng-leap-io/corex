<?php

declare(strict_types=1);

namespace App\Services\Webhook;

use Illuminate\Http\Request;

class WebhookSignature
{
    public function __construct(
        private readonly string $secret,
    ) {}

    public static function fromConfig(string $provider = 'default'): self
    {
        $secret = config("webhooks.signing.{$provider}", config('webhooks.signing.default'));

        return new self($secret);
    }

    public function sign(array $payload, ?int $timestamp = null): string
    {
        $timestamp = $timestamp ?? time();
        $data = $timestamp.'.'.json_encode($payload);

        return $timestamp.'.'.hash_hmac('sha256', $data, $this->secret);
    }

    public function verify(Request $request): bool
    {
        $signature = $request->header('X-Webhook-Signature')
            ?? $request->header('x-webhook-signature')
            ?? $request->input('signature');

        if (! $signature) {
            return false;
        }

        $timestamp = $request->header('X-Webhook-Timestamp')
            ?? $request->header('x-webhook-timestamp');

        if (! $this->isTimestampValid($timestamp)) {
            return false;
        }

        $payload = $request->getContent();

        return $this->verifyPayload($payload, $timestamp, $signature);
    }

    public function verifyPayload(string $payload, ?string $timestamp, string $signature): bool
    {
        $data = $timestamp.'.'.$payload;
        $expected = hash_hmac('sha256', $data, $this->secret);

        return hash_equals($expected, $signature);
    }

    public function verifyStripe(Request $request): bool
    {
        $signature = $request->header('stripe-signature');

        if (! $signature) {
            return false;
        }

        try {
            $payload = $request->getContent();
            $parts = explode(',', $signature);
            $timestamp = null;
            $sig = null;

            foreach ($parts as $part) {
                $kv = explode('=', $part, 2);
                if (count($kv) === 2) {
                    if ($kv[0] === 't') {
                        $timestamp = $kv[1];
                    } elseif ($kv[0] === 'v1') {
                        $sig = $kv[1];
                    }
                }
            }

            if (! $timestamp || ! $sig) {
                return false;
            }

            $signedPayload = "{$timestamp}.{$payload}";
            $expected = hash_hmac('sha256', $signedPayload, $this->secret);

            return hash_equals($expected, $sig);
        } catch (\Throwable) {
            return false;
        }
    }

    public function verifyResend(Request $request): bool
    {
        $signature = $request->header('svix-signature')
            ?? $request->header('webhook-signature');

        if (! $signature) {
            return false;
        }

        $payload = $request->getContent();
        $expected = hash_hmac('sha256', $payload, $this->secret);

        $parts = explode(',', $signature);
        foreach ($parts as $part) {
            $kv = explode('=', $part, 2);
            if (count($kv) === 2 && $kv[0] === 'v1') {
                if (hash_equals($expected, $kv[1])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function verifyGitHub(Request $request): bool
    {
        $signature = $request->header('x-hub-signature-256');

        if (! $signature) {
            return false;
        }

        $payload = $request->getContent();
        $expected = 'sha256='.hash_hmac('sha256', $payload, $this->secret);

        return hash_equals($expected, $signature);
    }

    public function isTimestampValid(?string $timestamp, int $maxAge = 300): bool
    {
        if (! $timestamp) {
            return false;
        }

        $parsed = (int) $timestamp;

        if ($parsed <= 0) {
            return false;
        }

        return abs(time() - $parsed) <= $maxAge;
    }

    public static function generateSecret(): string
    {
        return bin2hex(random_bytes(32));
    }
}
