<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Represents the supported webhook event types from external services.
 */
enum WebhookEvent: string
{
    case STRIPE_INVOICE_PAID = 'stripe.invoice.paid';
    case STRIPE_INVOICE_PAYMENT_FAILED = 'stripe.invoice.payment_failed';
    case RESEND_EMAIL_DELIVERED = 'resend.email.delivered';
    case RESEND_EMAIL_BOUNCED = 'resend.email.bounced';
    case RESEND_EMAIL_COMPLAINED = 'resend.email.complained';
    case GITHUB_PUSH = 'github.push';
    case GITHUB_PULL_REQUEST = 'github.pull_request';
    case SUPABASE_DB_CHANGE = 'supabase.db.change';
    case SUPABASE_AUTH_USER = 'supabase.auth.user';

    /**
     * Get the human-readable label for this event.
     */
    public function label(): string
    {
        return match ($this) {
            self::STRIPE_INVOICE_PAID => 'Stripe Invoice Paid',
            self::STRIPE_INVOICE_PAYMENT_FAILED => 'Stripe Invoice Payment Failed',
            self::RESEND_EMAIL_DELIVERED => 'Resend Email Delivered',
            self::RESEND_EMAIL_BOUNCED => 'Resend Email Bounced',
            self::RESEND_EMAIL_COMPLAINED => 'Resend Email Complained',
            self::GITHUB_PUSH => 'GitHub Push',
            self::GITHUB_PULL_REQUEST => 'GitHub Pull Request',
            self::SUPABASE_DB_CHANGE => 'Supabase Database Change',
            self::SUPABASE_AUTH_USER => 'Supabase Auth User',
        };
    }

    /**
     * Get the provider name for this event.
     */
    public function provider(): string
    {
        return match ($this) {
            self::STRIPE_INVOICE_PAID, self::STRIPE_INVOICE_PAYMENT_FAILED => 'stripe',
            self::RESEND_EMAIL_DELIVERED, self::RESEND_EMAIL_BOUNCED, self::RESEND_EMAIL_COMPLAINED => 'resend',
            self::GITHUB_PUSH, self::GITHUB_PULL_REQUEST => 'github',
            self::SUPABASE_DB_CHANGE, self::SUPABASE_AUTH_USER => 'supabase',
        };
    }
}
