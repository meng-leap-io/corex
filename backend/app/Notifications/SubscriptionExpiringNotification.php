<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionExpiringNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $plan,
        public readonly string $expiresAt,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'plan' => $this->plan,
            'expires_at' => $this->expiresAt,
            'message' => "Your {$this->plan} subscription is expiring on {$this->expiresAt}.",
            'created_at' => now()->toIso8601String(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Subscription Expiring Soon')
            ->greeting('Subscription Expiring Soon')
            ->line("Your {$this->plan} subscription is expiring on {$this->expiresAt}.")
            ->line('Please renew to continue enjoying all features.')
            ->action('Renew Now', url('/billing'));
    }
}
