<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AlertNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $type,
        public readonly string $message,
        public readonly array $data = [],
        public readonly ?string $severity = 'warning',
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => $this->type,
            'message' => $this->message,
            'data' => $this->data,
            'severity' => $this->severity,
            'created_at' => now()->toIso8601String(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mailMessage = (new MailMessage)
            ->subject("Alert: {$this->type}")
            ->greeting("Alert: {$this->type}")
            ->line($this->message)
            ->line("Severity: {$this->severity}");

        if (! empty($this->data)) {
            $mailMessage->line('Details: '.json_encode($this->data));
        }

        return $mailMessage;
    }
}
