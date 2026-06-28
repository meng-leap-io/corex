<?php

declare(strict_types=1);

namespace App\Livewire\Project;

use App\Models\Project;
use Illuminate\View\View;
use Livewire\Component;

class ProjectCollaborators extends Component
{
    public ?string $projectId = null;

    public array $onlineUsers = [];

    public array $projectUsers = [];

    public int $onlineCount = 0;

    protected $listeners = [
        'echo:project.{projectId},presence.updated' => 'handlePresenceUpdate',
        'echo:project.{projectId},project.updated' => 'handleProjectUpdate',
    ];

    public function mount(?string $projectId = null): void
    {
        $this->projectId = $projectId;

        if ($projectId) {
            $this->loadProjectUsers();
        }
    }

    public function loadProjectUsers(): void
    {
        $project = Project::with('user')->find($this->projectId);

        if ($project) {
            $this->projectUsers = [
                [
                    'id' => $project->user->id,
                    'name' => $project->user->name,
                    'avatar' => $project->user->avatar_url,
                    'role' => 'owner',
                ],
            ];
        }
    }

    public function handlePresenceUpdate(array $payload): void
    {
        $this->onlineUsers = $payload['users'] ?? [];
        $this->onlineCount = $payload['online_count'] ?? 0;
    }

    public function handleProjectUpdate(array $payload): void
    {
        $this->dispatch('projectUpdated', changes: $payload['changes'] ?? []);
    }

    public function isOnline(string $userId): bool
    {
        return in_array($userId, array_column($this->onlineUsers, 'user_id'));
    }

    public function render(): View
    {
        return view('livewire.project.project-collaborators');
    }
}
