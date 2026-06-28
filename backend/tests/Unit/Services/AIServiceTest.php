<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\AIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AIServiceTest extends TestCase
{
    use RefreshDatabase;

    private AIService $aiService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aiService = $this->app->make(AIService::class);
    }

    public function test_can_calculate_cost_for_gpt4(): void
    {
        $cost = $this->aiService->calculateCost('gpt-4o', 1000, 500);

        $this->assertIsFloat($cost);
        $this->assertGreaterThan(0, $cost);
    }

    public function test_returns_zero_cost_for_unknown_model(): void
    {
        $cost = $this->aiService->calculateCost('unknown-model', 1000, 500);

        $this->assertEquals(0.0, $cost);
    }

    public function test_cost_scales_with_token_count(): void
    {
        $small = $this->aiService->calculateCost('gpt-4o', 100, 50);
        $large = $this->aiService->calculateCost('gpt-4o', 10000, 5000);

        $this->assertGreaterThan($small, $large);
    }

    public function test_can_get_usage_stats(): void
    {
        $user = User::factory()->hasAiUsageLogs(10)->create();

        $stats = $this->aiService->getUsageStats($user);

        $this->assertArrayHasKey('total_requests', $stats);
        $this->assertArrayHasKey('total_tokens', $stats);
        $this->assertArrayHasKey('total_cost', $stats);
        $this->assertArrayHasKey('requests_by_model', $stats);
    }

    public function test_usage_stats_returns_zero_for_new_user(): void
    {
        $user = User::factory()->create();

        $stats = $this->aiService->getUsageStats($user);

        $this->assertEquals(0, $stats['total_requests']);
        $this->assertEquals(0, $stats['total_tokens']);
        $this->assertEquals(0.0, $stats['total_cost']);
    }

    public function test_can_check_usage_limit(): void
    {
        $user = User::factory()->create([
            'plan' => 'free',
            'api_usage_current' => 500,
            'api_usage_limit' => 1000,
        ]);

        $this->assertTrue($this->aiService->checkUsageLimit($user));
        $this->assertLessThan($user->api_usage_limit, $user->api_usage_current);
    }

    public function test_returns_false_when_over_limit(): void
    {
        $user = User::factory()->create([
            'plan' => 'free',
            'api_usage_current' => 1000,
            'api_usage_limit' => 1000,
        ]);

        $this->assertFalse($this->aiService->checkUsageLimit($user));
    }
}
