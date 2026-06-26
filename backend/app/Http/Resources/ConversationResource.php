<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'model_used' => $this->model_used,
            'tokens_used' => $this->tokens_used,
            'total_cost' => (float) $this->total_cost,
            'message_count' => $this->message_count,
            'last_message' => $this->last_message,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            'user' => new UserResource($this->whenLoaded('user')),
            'project' => new ProjectResource($this->whenLoaded('project')),
        ];
    }
}
