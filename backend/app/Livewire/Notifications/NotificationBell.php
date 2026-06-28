<?php

declare(strict_types=1);

namespace App\Livewire\Notifications;

use App\Models\Notification;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class NotificationBell extends Component
{
    use WithPagination;

    public int $unreadCount = 0;

    public bool $showDropdown = false;

    public array $recentNotifications = [];

    protected $listeners = [
        'echo:notifications.{user.id},notification.created' => 'handleRealtimeNotification',
        'notificationReceived' => 'addNotification',
        'markAsRead' => 'markNotificationRead',
        'refreshNotifications' => '$refresh',
    ];

    public function mount(): void
    {
        $this->loadUnreadCount();
        $this->loadRecent();
    }

    public function loadUnreadCount(): void
    {
        $this->unreadCount = Notification::where('user_id', auth()->id())
            ->unread()
            ->count();
    }

    public function loadRecent(): void
    {
        $this->recentNotifications = Notification::where('user_id', auth()->id())
            ->recent(10)
            ->get()
            ->toArray();
    }

    public function toggleDropdown(): void
    {
        $this->showDropdown = ! $this->showDropdown;

        if ($this->showDropdown) {
            $this->loadRecent();
        }
    }

    public function handleRealtimeNotification(array $payload): void
    {
        $this->addNotification($payload['notification'] ?? $payload);
    }

    public function addNotification(array $notification): void
    {
        $this->unreadCount++;
        array_unshift($this->recentNotifications, $notification);
        $this->recentNotifications = array_slice($this->recentNotifications, 0, 10);

        $this->dispatch('notificationAdded');
    }

    public function markNotificationRead(string $notificationId): void
    {
        $notification = Notification::find($notificationId);

        if ($notification) {
            $notification->markAsRead();
            $this->loadUnreadCount();
            $this->loadRecent();
        }
    }

    public function markAllAsRead(): void
    {
        Notification::where('user_id', auth()->id())
            ->unread()
            ->update(['read_at' => now()]);

        $this->unreadCount = 0;

        foreach ($this->recentNotifications as &$n) {
            $n['read_at'] = now()->toIso8601String();
        }

        $this->dispatch('notificationsCleared');
    }

    public function dismiss(string $notificationId): void
    {
        $notification = Notification::find($notificationId);

        if ($notification) {
            $notification->markAsDismissed();
            $this->loadUnreadCount();
            $this->loadRecent();
        }
    }

    public function render(): View
    {
        return view('livewire.notifications.notification-bell');
    }
}
