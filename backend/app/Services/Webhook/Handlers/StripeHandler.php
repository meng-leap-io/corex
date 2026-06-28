<?php

declare(strict_types=1);

namespace App\Services\Webhook\Handlers;

use App\Models\Subscription;
use App\Models\User;
use App\Models\WebhookLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StripeHandler
{
    public function handle(Request $request, WebhookLog $log): array
    {
        $payload = $log->payload;
        $eventType = $payload['type'] ?? 'unknown';
        $object = $payload['data']['object'] ?? [];

        return match ($eventType) {
            'customer.subscription.created',
            'customer.subscription.updated' => $this->handleSubscriptionUpdated($object, $log),

            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($object, $log),

            'invoice.payment_succeeded' => $this->handlePaymentSucceeded($object, $log),

            'invoice.payment_failed' => $this->handlePaymentFailed($object, $log),

            'customer.created',
            'customer.updated' => $this->handleCustomerUpdated($object, $log),

            default => ['status' => 200, 'response' => ['handled' => false, 'event' => $eventType]],
        };
    }

    public function process(WebhookLog $log): array
    {
        return $this->handle(Request::create('', 'POST', $log->payload ?? []), $log);
    }

    private function handleSubscriptionUpdated(array $object, WebhookLog $log): array
    {
        $userId = $object['metadata']['user_id'] ?? null;
        $customerEmail = $object['customer_email'] ?? null;

        if (!$userId && $customerEmail) {
            $user = User::where('email', $customerEmail)->first();
            $userId = $user?->id;
        }

        if (!$userId) {
            Log::warning('webhook.stripe.no_user', [
                'customer' => $object['customer'],
                'subscription' => $object['id'],
            ]);

            return ['status' => 200, 'response' => ['warning' => 'No user found']];
        }

        Subscription::updateOrCreate(
            ['stripe_id' => $object['id']],
            [
                'user_id' => $userId,
                'plan' => $object['items']['data'][0]['price']['nickname'] ?? 'pro',
                'status' => $this->mapStatus($object['status'] ?? 'active'),
                'stripe_status' => $object['status'],
                'stripe_price' => $object['items']['data'][0]['price']['id'] ?? null,
                'quantity' => $object['quantity'] ?? 1,
                'trial_ends_at' => $object['trial_end']
                    ? now()->setTimestamp($object['trial_end'])
                    : null,
                'ends_at' => $object['cancel_at_period_end']
                    ? now()->setTimestamp($object['current_period_end'])
                    : null,
            ],
        );

        if (isset($object['metadata']['plan'])) {
            User::where('id', $userId)->update(['plan' => $object['metadata']['plan']]);
        }

        Log::info('webhook.stripe.subscription_updated', [
            'user_id' => $userId,
            'subscription' => $object['id'],
            'status' => $object['status'],
        ]);

        return ['status' => 200, 'response' => ['handled' => true, 'subscription' => $object['id']]];
    }

    private function handleSubscriptionDeleted(array $object, WebhookLog $log): array
    {
        $subscription = Subscription::where('stripe_id', $object['id'])->first();

        if ($subscription) {
            $subscription->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);

            User::where('id', $subscription->user_id)->update(['plan' => 'free']);
        }

        Log::info('webhook.stripe.subscription_deleted', [
            'subscription' => $object['id'],
        ]);

        return ['status' => 200, 'response' => ['handled' => true, 'deleted' => true]];
    }

    private function handlePaymentSucceeded(array $object, WebhookLog $log): array
    {
        Log::info('webhook.stripe.payment_succeeded', [
            'invoice' => $object['id'],
            'amount' => $object['amount_paid'] ?? 0,
            'customer' => $object['customer'],
        ]);

        return ['status' => 200, 'response' => ['handled' => true]];
    }

    private function handlePaymentFailed(array $object, WebhookLog $log): array
    {
        Log::warning('webhook.stripe.payment_failed', [
            'invoice' => $object['id'],
            'customer' => $object['customer'],
            'attempts' => $object['attempt_count'] ?? 1,
        ]);

        return ['status' => 200, 'response' => ['handled' => true, 'warning' => 'Payment failed']];
    }

    private function handleCustomerUpdated(array $object, WebhookLog $log): array
    {
        if (isset($object['email'])) {
            User::where('email', $object['email'])->update([
                'stripe_id' => $object['id'],
            ]);
        }

        return ['status' => 200, 'response' => ['handled' => true]];
    }

    private function mapStatus(string $stripeStatus): string
    {
        return match ($stripeStatus) {
            'active', 'trialing' => 'active',
            'past_due', 'incomplete' => 'past_due',
            'canceled', 'unpaid', 'incomplete_expired' => 'cancelled',
            default => 'unknown',
        };
    }
}
