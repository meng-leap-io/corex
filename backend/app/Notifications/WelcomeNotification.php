<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $name,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Welcome to Corex')
            ->greeting("Hello {$this->name}!")
            ->line('Welcome to Corex! We are excited to have you on board.')
            ->line('Get started by exploring your dashboard and setting up your first project.')
            ->action('Go to Dashboard', url('/dashboard'));
    }
}
