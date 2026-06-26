<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'slug' => $this->slug,
            'language' => $this->language,
            'framework' => $this->framework,
            'status' => $this->status,
            'files' => $this->files,
            'structure' => $this->structure,
            'file_count' => $this->file_count,
            'language_label' => $this->language_label,
            'last_accessed_at' => $this->last_accessed_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            'user' => new UserResource($this->whenLoaded('user')),
            'conversations' => ConversationResource::collection($this->whenLoaded('conversations')),
        ];
    }
}
