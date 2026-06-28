<?php

declare(strict_types=1);

namespace App\Livewire\Team;

use Illuminate\View\View;
use Livewire\Component;

class TeamStatus extends Component
{
    public ?string $teamId = null;

    public array $members = [];

    public array $onlineMembers = [];

    public int $totalMembers = 0;

    public int $onlineCount = 0;

    protected $listeners = [
        'echo:team.{teamId},presence.updated' => 'handlePresence',
        'echo:team.{teamId},member.joined' => 'handleMemberJoined',
        'echo:team.{teamId},member.left' => 'handleMemberLeft',
    ];

    public function mount(?string $teamId = null): void
    {
        $this->teamId = $teamId;

        if ($teamId) {
            $this->loadMembers();
        }
    }

    public function loadMembers(): void
    {
        $this->totalMembers = count($this->members);
    }

    public function handlePresence(array $payload): void
    {
        $this->onlineMembers = $payload['users'] ?? [];
        $this->onlineCount = $payload['online_count'] ?? 0;
    }

    public function handleMemberJoined(array $payload): void
    {
        $member = $payload['member'] ?? [];

        if (! empty($member)) {
            $this->members[] = $member;
            $this->totalMembers = count($this->members);
        }

        $this->dispatch('memberJoined', member: $member);
    }

    public function handleMemberLeft(array $payload): void
    {
        $userId = $payload['user_id'] ?? null;

        if ($userId) {
            $this->members = array_values(array_filter(
                $this->members,
                fn ($m) => ($m['id'] ?? $m['user_id']) !== $userId,
            ));
            $this->totalMembers = count($this->members);
        }

        $this->dispatch('memberLeft', userId: $userId);
    }

    public function getStatusColor(string $userId): string
    {
        return $this->isOnline($userId) ? 'bg-green-500' : 'bg-gray-400';
    }

    public function isOnline(string $userId): bool
    {
        return in_array($userId, array_column($this->onlineMembers, 'user_id'));
    }

    public function render(): View
    {
        return view('livewire.team.team-status');
    }
}
