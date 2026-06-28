<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Webhook\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function __construct(
        private readonly WebhookService $webhookService,
    ) {}

    public function handle(Request $request, string $provider): JsonResponse
    {
        $result = $this->webhookService->handleIncoming($request, $provider);

        return response()->json(
            ['message' => $result['message']],
            $result['status'],
        );
    }

    public function stripe(Request $request): JsonResponse
    {
        return $this->handle($request, 'stripe');
    }

    public function resend(Request $request): JsonResponse
    {
        return $this->handle($request, 'resend');
    }

    public function github(Request $request): JsonResponse
    {
        return $this->handle($request, 'github');
    }

    public function supabase(Request $request): JsonResponse
    {
        return $this->handle($request, 'supabase');
    }

    public function generic(Request $request, string $channel): JsonResponse
    {
        return $this->handle($request, $channel);
    }

    public function invokeFunction(Request $request): JsonResponse
    {
        $request->validate([
            'function' => ['required', 'string', 'min:1'],
            'payload' => ['required', 'array'],
        ]);

        $result = $this->webhookService->invokeEdgeFunction(
            $request->input('function'),
            $request->input('payload'),
        );

        return response()->json(
            $result['body'],
            $result['status'],
        );
    }

    public function retry(Request $request, string $logId): JsonResponse
    {
        $result = $this->webhookService->retrySingle($logId);

        if (!$result) {
            return response()->json(['message' => 'Log not found or not failed'], 404);
        }

        return response()->json(['message' => 'Queued for retry']);
    }

    public function retryAll(Request $request): JsonResponse
    {
        $count = $this->webhookService->retryFailed();

        return response()->json(['message' => "Queued {$count} failed webhooks for retry"]);
    }

    public function stats(Request $request): JsonResponse
    {
        return response()->json($this->webhookService->getStats());
    }
}
