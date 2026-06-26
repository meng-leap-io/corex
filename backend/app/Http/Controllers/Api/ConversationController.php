<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ConversationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $conversations = $request->user()
                ->conversations()
                ->when($request->project_id, fn($q, $v) => $q->byProject($v))
                ->when($request->model, fn($q, $v) => $q->byModel($v))
                ->orderBy('created_at', 'desc')
                ->paginate($request->input('per_page', 20));

            return $this->success(
                data: ConversationResource::collection($conversations),
                message: 'Conversations retrieved successfully.',
            );
        } catch (\Throwable $e) {
            return $this->logAndError(
                'conversations_index_failed',
                'Failed to retrieve conversations.',
                $e,
                500,
            );
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'project_id' => ['nullable', 'string', 'exists:projects,id'],
                'title' => ['nullable', 'string', 'max:255'],
                'model_used' => ['nullable', 'string', 'max:100'],
                'messages' => ['nullable', 'array'],
                'messages.*.role' => ['required_with:messages', 'string', 'in:user,assistant,system'],
                'messages.*.content' => ['required_with:messages', 'string'],
            ]);

            $conversation = $request->user()->conversations()->create([
                'project_id' => $validated['project_id'] ?? null,
                'title' => $validated['title'] ?? 'New Conversation',
                'model_used' => $validated['model_used'] ?? null,
                'messages' => $validated['messages'] ?? [],
            ]);

            Log::info('conversation_created', [
                'conversation_id' => $conversation->id,
                'user_id' => $request->user()->id,
            ]);

            return $this->success(
                data: new ConversationResource($conversation),
                message: 'Conversation created successfully.',
                code: 201,
            );
        } catch (\Throwable $e) {
            return $this->logAndError(
                'conversation_create_failed',
                'Failed to create conversation.',
                $e,
                500,
            );
        }
    }

    public function show(Conversation $conversation): JsonResponse
    {
        try {
            $conversation->load('project');

            return $this->success(
                data: new ConversationResource($conversation),
                message: 'Conversation retrieved successfully.',
            );
        } catch (\Throwable $e) {
            return $this->logAndError(
                'conversation_show_failed',
                'Failed to retrieve conversation.',
                $e,
                500,
                ['conversation_id' => $conversation->id],
            );
        }
    }

    public function destroy(Conversation $conversation): JsonResponse
    {
        try {
            $conversation->delete();

            Log::info('conversation_deleted', ['conversation_id' => $conversation->id]);

            return $this->success(message: 'Conversation deleted successfully.');
        } catch (\Throwable $e) {
            return $this->logAndError(
                'conversation_delete_failed',
                'Failed to delete conversation.',
                $e,
                500,
                ['conversation_id' => $conversation->id],
            );
        }
    }
}
