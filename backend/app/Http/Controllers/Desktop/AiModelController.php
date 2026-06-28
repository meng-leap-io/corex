<?php

declare(strict_types=1);

namespace App\Http\Controllers\Desktop;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiModelController extends Controller
{
    private const OLLAMA_API = 'http://127.0.0.1:11434';

    public function index(): JsonResponse
    {
        $models = cache()->get('_native_ai_models', $this->defaultModels());

        $localModels = [];
        try {
            $response = \Http::timeout(3)->get(self::OLLAMA_API.'/api/tags');
            if ($response->successful()) {
                $localModels = collect($response->json('models', []))
                    ->map(fn ($m) => [
                        'name' => $m['name'],
                        'size' => $m['size'] ?? 0,
                        'modified' => $m['modified_at'] ?? null,
                        'source' => 'ollama',
                        'local' => true,
                    ])
                    ->toArray();
            }
        } catch (\Throwable) {
            // Ollama not running
        }

        return response()->json([
            'models' => $models,
            'local_models' => $localModels,
            'ollama_running' => count($localModels) > 0,
        ]);
    }

    public function pull(Request $request): JsonResponse
    {
        $model = $request->input('model');
        if (! $model) {
            return response()->json(['error' => 'Model name required'], 422);
        }

        try {
            \Http::timeout(5)->post(self::OLLAMA_API.'/api/pull', [
                'name' => $model,
                'stream' => false,
            ]);

            return response()->json(['pulled' => true, 'model' => $model]);
        } catch (\Throwable $e) {
            return response()->json(['error' => "Failed to pull model: {$e->getMessage()}"], 500);
        }
    }

    public function remove(Request $request): JsonResponse
    {
        $model = $request->input('model');
        if (! $model) {
            return response()->json(['error' => 'Model name required'], 422);
        }

        try {
            \Http::timeout(5)->delete(self::OLLAMA_API.'/api/delete', [
                'name' => $model,
            ]);

            return response()->json(['removed' => true, 'model' => $model]);
        } catch (\Throwable $e) {
            return response()->json(['error' => "Failed to remove model: {$e->getMessage()}"], 500);
        }
    }

    public function status(): JsonResponse
    {
        $ollamaRunning = false;
        $ollamaVersion = null;

        try {
            $response = \Http::timeout(3)->get(self::OLLAMA_API.'/api/version');
            if ($response->successful()) {
                $ollamaRunning = true;
                $ollamaVersion = $response->json('version');
            }
        } catch (\Throwable) {
        }

        return response()->json([
            'ollama_running' => $ollamaRunning,
            'ollama_version' => $ollamaVersion,
            'gateway_reachable' => $this->checkGateway(),
        ]);
    }

    private function checkGateway(): bool
    {
        try {
            $gatewayUrl = config('services.ai_gateway.url', 'http://127.0.0.1:8000');
            $response = \Http::timeout(2)->get("{$gatewayUrl}/health");

            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    private function defaultModels(): array
    {
        return [
            ['id' => 'gpt-4o', 'name' => 'GPT-4o', 'provider' => 'openai', 'local' => false],
            ['id' => 'gpt-4o-mini', 'name' => 'GPT-4o Mini', 'provider' => 'openai', 'local' => false],
            ['id' => 'claude-3-opus-20240229', 'name' => 'Claude 3 Opus', 'provider' => 'anthropic', 'local' => false],
            ['id' => 'claude-3-5-sonnet-20240620', 'name' => 'Claude 3.5 Sonnet', 'provider' => 'anthropic', 'local' => false],
            ['id' => 'llama3.2', 'name' => 'Llama 3.2 (Ollama)', 'provider' => 'ollama', 'local' => true],
            ['id' => 'codellama', 'name' => 'CodeLlama (Ollama)', 'provider' => 'ollama', 'local' => true],
            ['id' => 'deepseek-coder', 'name' => 'DeepSeek Coder (Ollama)', 'provider' => 'ollama', 'local' => true],
            ['id' => 'mistral', 'name' => 'Mistral (Ollama)', 'provider' => 'ollama', 'local' => true],
        ];
    }
}
