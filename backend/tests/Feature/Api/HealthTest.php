<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_returns_ok(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'ok',
                'service' => 'laravel-api',
            ]);
    }

    public function test_health_endpoint_has_timestamp(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertJsonStructure([
            'status',
            'service',
            'timestamp',
        ]);
    }

    public function test_health_endpoint_is_unauthenticated(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200);
    }

    public function test_root_redirects_or_returns_info(): void
    {
        $response = $this->get('/');
        $this->assertTrue(in_array($response->getStatusCode(), [200, 302]));
    }
}
