<?php

declare(strict_types=1);

namespace App\Helpers;

use InvalidArgumentException;

class AiCostHelper
{
    private const PRICING = [
        'gpt-4o' => ['input' => 0.0000025, 'output' => 0.00001],
        'gpt-4o-mini' => ['input' => 0.00000015, 'output' => 0.0000006],
        'gpt-4-turbo' => ['input' => 0.00001, 'output' => 0.00003],
        'gpt-4' => ['input' => 0.00003, 'output' => 0.00006],
        'gpt-3.5-turbo' => ['input' => 0.0000005, 'output' => 0.0000015],
        'claude-3-opus' => ['input' => 0.000015, 'output' => 0.000075],
        'claude-3-sonnet' => ['input' => 0.000003, 'output' => 0.000015],
        'claude-3-haiku' => ['input' => 0.00000025, 'output' => 0.00000125],
        'claude-3-5-sonnet' => ['input' => 0.000003, 'output' => 0.000015],
        'claude-3-5-haiku' => ['input' => 0.0000008, 'output' => 0.000004],
        'gemini-1.5-pro' => ['input' => 0.00000125, 'output' => 0.000005],
        'gemini-1.5-flash' => ['input' => 0.000000075, 'output' => 0.0000003],
        'deepseek-coder' => ['input' => 0.00000014, 'output' => 0.00000028],
        'deepseek-chat' => ['input' => 0.00000014, 'output' => 0.00000028],
        'mistral-large' => ['input' => 0.000004, 'output' => 0.000012],
        'mistral-medium' => ['input' => 0.00000275, 'output' => 0.0000081],
        'mistral-small' => ['input' => 0.000001, 'output' => 0.000003],
        'codestral' => ['input' => 0.000001, 'output' => 0.000003],
    ];

    public static function calculateCost(string $model, int $promptTokens, int $completionTokens): float
    {
        $model = strtolower($model);

        if (! isset(self::PRICING[$model])) {
            throw new InvalidArgumentException("Unknown model: {$model}");
        }

        $pricing = self::PRICING[$model];

        return ($promptTokens * $pricing['input']) + ($completionTokens * $pricing['output']);
    }

    public static function formatCost(float $cost): string
    {
        if ($cost < 0.000001) {
            return '$'.number_format($cost, 10, '.', '');
        }

        if ($cost < 0.001) {
            return '$'.number_format($cost, 6, '.', '');
        }

        return '$'.number_format($cost, 4, '.', '');
    }

    public static function getModelPricing(): array
    {
        return self::PRICING;
    }
}
