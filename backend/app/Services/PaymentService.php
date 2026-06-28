<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    public function __construct(
        private readonly array $planPrices = [
            User::PLAN_PRO => ['monthly' => 'price_pro_monthly', 'yearly' => 'price_pro_yearly'],
            User::PLAN_TEAM => ['monthly' => 'price_team_monthly', 'yearly' => 'price_team_yearly'],
        ],
    ) {}

    public function createSubscription(User $user, string $plan, string $paymentMethodId, string $interval = 'monthly'): Subscription
    {
        $stripePriceId = $this->getStripePriceId($plan, $interval);

        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan' => $plan,
            'status' => Subscription::STATUS_ACTIVE,
            'stripe_id' => 'sub_mock_'.uniqid(),
            'stripe_status' => 'active',
            'stripe_price' => $stripePriceId,
            'quantity' => 1,
        ]);

        Log::info('payment.subscription_created', [
            'user_id' => $user->id,
            'plan' => $plan,
            'subscription_id' => $subscription->id,
        ]);

        return $subscription;
    }

    public function cancelSubscription(Subscription $subscription): void
    {
        $subscription->cancel();

        Log::info('payment.subscription_cancelled', [
            'user_id' => $subscription->user_id,
            'subscription_id' => $subscription->id,
        ]);
    }

    public function resumeSubscription(Subscription $subscription): void
    {
        $subscription->resume();

        Log::info('payment.subscription_resumed', [
            'user_id' => $subscription->user_id,
            'subscription_id' => $subscription->id,
        ]);
    }

    public function changePlan(Subscription $subscription, string $newPlan, string $interval = 'monthly'): Subscription
    {
        $stripePriceId = $this->getStripePriceId($newPlan, $interval);

        $subscription->update([
            'plan' => $newPlan,
            'stripe_price' => $stripePriceId,
        ]);

        Log::info('payment.subscription_plan_changed', [
            'user_id' => $subscription->user_id,
            'subscription_id' => $subscription->id,
            'new_plan' => $newPlan,
        ]);

        return $subscription->fresh();
    }

    public function handleWebhook(array $payload): void
    {
        $type = $payload['type'] ?? '';
        $data = $payload['data']['object'] ?? [];

        match ($type) {
            'customer.subscription.updated' => $this->handleSubscriptionUpdated($data),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($data),
            'invoice.payment_succeeded' => $this->handlePaymentSucceeded($data),
            'invoice.payment_failed' => $this->handlePaymentFailed($data),
            default => Log::info('payment.webhook_unhandled', ['type' => $type]),
        };
    }

    private function handleSubscriptionUpdated(array $data): void
    {
        $stripeId = $data['id'] ?? null;
        $status = $data['status'] ?? null;

        $subscription = Subscription::where('stripe_id', $stripeId)->first();

        if ($subscription) {
            $subscription->update([
                'stripe_status' => $status,
                'status' => $this->mapStripeStatus($status),
                'ends_at' => isset($data['current_period_end']) ? now()->createFromTimestamp($data['current_period_end']) : null,
            ]);

            Log::info('payment.subscription_updated', [
                'stripe_id' => $stripeId,
                'status' => $status,
            ]);
        }
    }

    private function handleSubscriptionDeleted(array $data): void
    {
        $stripeId = $data['id'] ?? null;

        $subscription = Subscription::where('stripe_id', $stripeId)->first();

        if ($subscription) {
            $subscription->markExpired();

            Log::info('payment.subscription_deleted', [
                'stripe_id' => $stripeId,
                'user_id' => $subscription->user_id,
            ]);
        }
    }

    private function handlePaymentSucceeded(array $data): void
    {
        $subscriptionId = $data['subscription'] ?? null;

        if ($subscriptionId) {
            $subscription = Subscription::where('stripe_id', $subscriptionId)->first();

            if ($subscription && $subscription->status === Subscription::STATUS_PAST_DUE) {
                $subscription->update(['status' => Subscription::STATUS_ACTIVE]);
            }
        }

        Log::info('payment.payment_succeeded', [
            'invoice_id' => $data['id'] ?? null,
            'subscription_id' => $subscriptionId,
        ]);
    }

    private function handlePaymentFailed(array $data): void
    {
        $subscriptionId = $data['subscription'] ?? null;

        if ($subscriptionId) {
            $subscription = Subscription::where('stripe_id', $subscriptionId)->first();

            if ($subscription) {
                $subscription->update(['status' => Subscription::STATUS_PAST_DUE]);
            }
        }

        Log::warning('payment.payment_failed', [
            'invoice_id' => $data['id'] ?? null,
            'subscription_id' => $subscriptionId,
        ]);
    }

    public function getPlanPrices(): array
    {
        return [
            User::PLAN_FREE => ['monthly' => 0, 'yearly' => 0],
            User::PLAN_PRO => ['monthly' => 29, 'yearly' => 290],
            User::PLAN_TEAM => ['monthly' => 99, 'yearly' => 990],
        ];
    }

    private function getStripePriceId(string $plan, string $interval): ?string
    {
        return $this->planPrices[$plan][$interval] ?? null;
    }

    private function mapStripeStatus(string $stripeStatus): string
    {
        return match ($stripeStatus) {
            'active', 'trialing' => Subscription::STATUS_ACTIVE,
            'past_due' => Subscription::STATUS_PAST_DUE,
            'canceled' => Subscription::STATUS_CANCELLED,
            'unpaid', 'incomplete' => Subscription::STATUS_INACTIVE,
            default => Subscription::STATUS_INACTIVE,
        };
    }

    public function getSubscriptionSummary(User $user): array
    {
        $subscription = $user->subscription;

        return [
            'has_active_subscription' => ! is_null($subscription),
            'current_plan' => $user->plan,
            'plan' => $subscription ? [
                'id' => $subscription->id,
                'plan' => $subscription->plan,
                'status' => $subscription->status,
                'is_active' => $subscription->isActive(),
                'is_on_trial' => $subscription->isOnTrial(),
                'days_remaining' => $subscription->daysRemaining(),
                'trial_days_remaining' => $subscription->trialDaysRemaining(),
                'cancelled_at' => $subscription->cancelled_at?->toISOString(),
            ] : null,
            'api_usage' => [
                'limit' => $user->api_usage_limit,
                'current' => $user->api_usage_current,
                'remaining' => $user->api_usage_limit - $user->api_usage_current,
                'percentage' => $user->api_usage_limit > 0
                    ? round(($user->api_usage_current / $user->api_usage_limit) * 100, 1)
                    : 0,
            ],
            'features' => [
                'advanced_analytics' => $user->canAccessFeature('advanced_analytics'),
                'team_members' => $user->canAccessFeature('team_members'),
                'priority_support' => $user->canAccessFeature('priority_support'),
                'unlimited_projects' => $user->canAccessFeature('unlimited_projects'),
                'api_access' => $user->canAccessFeature('api_access'),
            ],
        ];
    }
}
