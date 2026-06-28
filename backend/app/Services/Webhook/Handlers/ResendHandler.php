<?php

declare(strict_types=1);

namespace App\Services\Webhook\Handlers;

use App\Models\WebhookLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ResendHandler
{
    public function handle(Request $request, WebhookLog $log): array
    {
        $payload = $log->payload;
        $eventType = $payload['type'] ?? 'unknown';

        return match ($eventType) {
            'email.sent' => $this->handleEmailSent($payload, $log),
            'email.delivered' => $this->handleEmailDelivered($payload, $log),
            'email.bounced' => $this->handleEmailBounced($payload, $log),
            'email.complained' => $this->handleEmailComplained($payload, $log),
            'email.opened' => $this->handleEmailOpened($payload, $log),
            'email.clicked' => $this->handleEmailClicked($payload, $log),
            default => ['status' => 200, 'response' => ['handled' => false]],
        };
    }

    public function process(WebhookLog $log): array
    {
        return $this->handle(Request::create('', 'POST', $log->payload ?? []), $log);
    }

    private function handleEmailSent(array $payload, WebhookLog $log): array
    {
        $email = $payload['data']['to'] ?? [];

        Log::info('webhook.resend.email_sent', [
            'email_id' => $payload['data']['id'] ?? null,
            'to' => $email,
        ]);

        return ['status' => 200, 'response' => ['handled' => true, 'event' => 'sent']];
    }

    private function handleEmailDelivered(array $payload, WebhookLog $log): array
    {
        Log::info('webhook.resend.email_delivered', [
            'email_id' => $payload['data']['id'] ?? null,
        ]);

        return ['status' => 200, 'response' => ['handled' => true, 'event' => 'delivered']];
    }

    private function handleEmailBounced(array $payload, WebhookLog $log): array
    {
        $email = $payload['data']['to'] ?? 'unknown';
        $reason = $payload['data']['bounce_reason'] ?? 'Unknown';

        Log::warning('webhook.resend.email_bounced', [
            'email' => $email,
            'reason' => $reason,
        ]);

        return ['status' => 200, 'response' => [
            'handled' => true,
            'event' => 'bounced',
            'email' => $email,
            'reason' => $reason,
        ]];
    }

    private function handleEmailComplained(array $payload, WebhookLog $log): array
    {
        $email = $payload['data']['to'] ?? 'unknown';

        Log::warning('webhook.resend.email_complained', [
            'email' => $email,
        ]);

        return ['status' => 200, 'response' => ['handled' => true, 'event' => 'complained']];
    }

    private function handleEmailOpened(array $payload, WebhookLog $log): array
    {
        return ['status' => 200, 'response' => ['handled' => true, 'event' => 'opened']];
    }

    private function handleEmailClicked(array $payload, WebhookLog $log): array
    {
        $link = $payload['data']['link'] ?? 'unknown';

        return [
            'status' => 200,
            'response' => ['handled' => true, 'event' => 'clicked', 'link' => $link],
        ];
    }
}
