<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Represents the supported AI provider services.
 */
enum AiProvider: string
{
    case OPENAI = 'openai';
    case ANTHROPIC = 'anthropic';
    case GROQ = 'groq';
    case DEEPSEEK = 'deepseek';
    case OLLAMA = 'ollama';

    /**
     * Get the human-readable label for this provider.
     */
    public function label(): string
    {
        return match ($this) {
            self::OPENAI => 'OpenAI',
            self::ANTHROPIC => 'Anthropic',
            self::GROQ => 'Groq',
            self::DEEPSEEK => 'DeepSeek',
            self::OLLAMA => 'Ollama',
        };
    }

    /**
     * Get the list of default models for this provider.
     *
     * @return list<string>
     */
    public function defaultModels(): array
    {
        return match ($this) {
            self::OPENAI => ['gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo'],
            self::ANTHROPIC => ['claude-sonnet-4-20250514', 'claude-haiku-3-5-20241022'],
            self::GROQ => ['llama-3.3-70b-versatile', 'mixtral-8x7b-32768'],
            self::DEEPSEEK => ['deepseek-chat', 'deepseek-coder'],
            self::OLLAMA => ['llama3.2', 'mistral', 'codellama'],
        };
    }
}
