<?php

namespace Tests\Unit\Services\Webhook;

use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Models\WebhookLog;
use App\Services\Supabase\SupabaseService;
use App\Services\Webhook\Handlers\StripeHandler;
use App\Services\Webhook\WebhookRouter;
use App\Services\Webhook\WebhookService;
use App\Services\Webhook\WebhookSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class WebhookServiceTest extends TestCase
{
    use RefreshDatabase;

    private WebhookService $service;

    private WebhookRouter $router;

    protected function setUp(): void
    {
        parent::setUp();

        $this->router = app(WebhookRouter::class);
        $signature = app(WebhookSignature::class);
        $supabase = app(SupabaseService::class);

        $this->service = new WebhookService($this->router, $signature, $supabase);
    }

    public function test_creates_webhook_log(): void
    {
        Bus::fake();

        $this->router->register('webhooks/test', '', [
            'verify_signature' => false,
            'rate_limit' => false,
        ]);

        $payload = ['type' => 'test.event', 'data' => ['key' => 'value']];
        $request = Request::create('/webhooks/test', 'POST', [], [], [], [], json_encode($payload));
        $request->headers->set('X-Webhook-Event', 'test.event');

        $result = $this->service->handleIncoming($request, 'test');

        $this->assertArrayHasKey('log_id', $result);

        $log = WebhookLog::find($result['log_id']);
        $this->assertNotNull($log);
        $this->assertEquals('test.event', $log->event_type);
        $this->assertEquals('test', $log->provider);
        $this->assertEquals('pending', $log->status);
    }

    public function test_handles_stripe_subscription_updated(): void
    {
        $handler = new StripeHandler;
        $user = User::factory()->create();
        $log = WebhookLog::factory()->create([
            'provider' => 'stripe',
            'event_type' => 'customer.subscription.updated',
            'payload' => [
                'type' => 'customer.subscription.updated',
                'data' => [
                    'object' => [
                        'id' => 'sub_123',
                        'customer' => 'cus_123',
                        'status' => 'active',
                        'metadata' => ['user_id' => $user->id],
                        'items' => [
                            'data' => [
                                ['price' => ['nickname' => 'pro', 'id' => 'price_123']],
                            ],
                        ],
                        'quantity' => 1,
                    ],
                ],
            ],
        ]);

        $result = $handler->process($log);

        $this->assertEquals(200, $result['status']);
        $this->assertTrue($result['response']['handled']);
    }

    public function test_handles_stripe_unknown_event(): void
    {
        $handler = new StripeHandler;
        $log = WebhookLog::factory()->create([
            'provider' => 'stripe',
            'event_type' => 'unknown.event',
            'payload' => ['type' => 'unknown.event', 'data' => ['object' => []]],
        ]);

        $result = $handler->process($log);

        $this->assertEquals(200, $result['status']);
        $this->assertFalse($result['response']['handled']);
    }

    public function test_returns_stats(): void
    {
        $stats = $this->service->getStats();

        $this->assertArrayHasKey('total', $stats);
        $this->assertArrayHasKey('pending', $stats);
        $this->assertArrayHasKey('completed', $stats);
        $this->assertArrayHasKey('failed', $stats);
        $this->assertArrayHasKey('endpoints', $stats);
    }

    public function test_creates_and_sends_to_endpoint(): void
    {
        $endpoint = WebhookEndpoint::factory()->create([
            'name' => 'Test Endpoint',
            'url' => 'https://example.com/webhooks',
            'events' => ['*'],
            'status' => 'active',
            'retry_count' => 1,
        ]);

        $log = WebhookLog::factory()->create([
            'event_type' => 'test.event',
            'status' => 'pending',
        ]);

        $result = $this->service->sendToEndpoint($endpoint, $log);

        $this->assertFalse($result);
    }

    public function test_retry_single_failed_log(): void
    {
        Bus::fake();

        $log = WebhookLog::factory()->create([
            'status' => 'failed',
            'attempts' => 1,
            'max_attempts' => 3,
        ]);

        $result = $this->service->retrySingle($log->id);

        $this->assertTrue($result);

        $log->refresh();
        $this->assertEquals(0, $log->attempts);
        $this->assertEquals('pending', $log->status);
    }

    public function test_retry_non_existent_log(): void
    {
        $result = $this->service->retrySingle('00000000-0000-0000-0000-000000000000');

        $this->assertFalse($result);
    }

    public function test_retry_completed_log_fails(): void
    {
        $log = WebhookLog::factory()->create([
            'status' => 'completed',
        ]);

        $result = $this->service->retrySingle($log->id);

        $this->assertFalse($result);
    }

    public function test_invoke_edge_function_handles_error(): void
    {
        $result = $this->service->invokeEdgeFunction('nonexistent', ['test' => true]);

        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('body', $result);
        $this->assertArrayHasKey('success', $result);
    }
}
