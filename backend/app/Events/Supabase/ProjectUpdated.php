<?php

declare(strict_types=1);

namespace App\Events\Supabase;

use App\Models\Project;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class ProjectUpdated extends BroadcastEvent implements ShouldBroadcastNow
{
    public function __construct(
        string $channel,
        string $event,
        array $payload,
        private readonly Project $project,
    ) {
        parent::__construct($channel, $event, $payload);
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("project.{$this->project->id}"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'type' => 'project_update',
            'project_id' => $this->project->id,
            'changes' => $this->payload['changes'] ?? [],
            'user' => $this->payload['user'] ?? [],
            'timestamp' => now()->toIso8601String(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'project.updated';
    }

    public static function fromProject(Project $project, array $changes, array $user): self
    {
        return new self(
            channel: "project:{$project->id}",
            event: 'project.updated',
            payload: [
                'changes' => $changes,
                'user' => $user,
            ],
            project: $project,
        );
    }
}
