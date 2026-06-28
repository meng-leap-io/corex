<?php

declare(strict_types=1);

namespace App\Listeners\Analytics;

use App\Events\Analytics\AlertTriggered;
use App\Models\User;
use App\Notifications\AlertNotification;
use Illuminate\Support\Facades\Log;

class SendAlertNotification
{
    public function handle(AlertTriggered $event): void
    {
        Log::info('Alert triggered', [
            'type' => $event->type,
            'severity' => $event->severity,
            'message' => $event->message,
        ]);

        $userIds = $event->data['user_ids'] ?? [];

        $users = User::whereIn('id', $userIds)->get();

        foreach ($users as $user) {
            $user->notify(new AlertNotification(
                type: $event->type,
                message: $event->message,
                data: $event->data,
                severity: $event->severity,
            ));
        }
    }
}
