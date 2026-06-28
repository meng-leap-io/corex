<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\Supabase\RlsContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RlsContextServiceTest extends TestCase
{
    use RefreshDatabase;

    private RlsContextService $rlsService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rlsService = app(RlsContextService::class);
    }

    public function test_sets_user_context(): void
    {
        if (! $this->canConnectToPgsql()) {
            $this->markTestSkipped('PostgreSQL connection not available');
        }

        $user = User::factory()->create();

        $this->rlsService->setUserContext($user, '127.0.0.1');

        $this->assertEquals($user->id, $this->rlsService->getCurrentUserId());
        $this->assertEquals('user', $this->rlsService->getCurrentUserRole());
        $this->assertTrue($this->rlsService->isContextSet());
    }

    public function test_sets_admin_context(): void
    {
        if (! $this->canConnectToPgsql()) {
            $this->markTestSkipped('PostgreSQL connection not available');
        }

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->rlsService->setUserContext($admin);

        $this->assertEquals('admin', $this->rlsService->getCurrentUserRole());
        $this->assertTrue($this->rlsService->isAdmin());
    }

    public function test_sets_guest_context(): void
    {
        if (! $this->canConnectToPgsql()) {
            $this->markTestSkipped('PostgreSQL connection not available');
        }

        $this->rlsService->setGuestContext('10.0.0.1');

        $this->assertEquals('anonymous', $this->rlsService->getCurrentUserRole());
        $this->assertNull($this->rlsService->getCurrentUserId());
        $this->assertFalse($this->rlsService->isAdmin());
    }

    public function test_sets_system_context(): void
    {
        if (! $this->canConnectToPgsql()) {
            $this->markTestSkipped('PostgreSQL connection not available');
        }

        $this->rlsService->setSystemContext();

        $this->assertEquals('system', $this->rlsService->getCurrentUserRole());
        $this->assertNotNull($this->rlsService->getCurrentUserId());
    }

    public function test_clears_context(): void
    {
        if (! $this->canConnectToPgsql()) {
            $this->markTestSkipped('PostgreSQL connection not available');
        }

        $user = User::factory()->create();
        $this->rlsService->setUserContext($user);
        $this->assertTrue($this->rlsService->isContextSet());

        $this->rlsService->clearContext();
        $this->assertFalse($this->rlsService->isContextSet());
    }

    public function test_executes_callback_with_context(): void
    {
        if (! $this->canConnectToPgsql()) {
            $this->markTestSkipped('PostgreSQL connection not available');
        }

        $user = User::factory()->create();
        $userId = null;

        $this->rlsService->executeWithContext($user, function () use (&$userId) {
            $userId = $this->rlsService->getCurrentUserId();
        });

        $this->assertEquals($user->id, $userId);
        $this->assertFalse($this->rlsService->isContextSet());
    }

    public function test_executes_as_system(): void
    {
        if (! $this->canConnectToPgsql()) {
            $this->markTestSkipped('PostgreSQL connection not available');
        }

        $role = null;

        $this->rlsService->executeAsSystem(function () use (&$role) {
            $role = $this->rlsService->getCurrentUserRole();
        });

        $this->assertEquals('system', $role);
    }

    public function test_resolves_admin_from_config_email(): void
    {
        config(['app.admin_email' => 'admin@corex.dev']);

        $admin = User::factory()->make(['email' => 'admin@corex.dev', 'role' => 'user']);

        $reflection = new \ReflectionMethod($this->rlsService, 'resolveRole');
        $reflection->setAccessible(true);

        $this->assertEquals('admin', $reflection->invoke($this->rlsService, $admin));
    }

    public function test_resolves_role_from_user_attribute(): void
    {
        $admin = User::factory()->make(['role' => User::ROLE_ADMIN]);
        $user = User::factory()->make(['role' => User::ROLE_USER]);

        $reflection = new \ReflectionMethod($this->rlsService, 'resolveRole');
        $reflection->setAccessible(true);

        $this->assertEquals('admin', $reflection->invoke($this->rlsService, $admin));
        $this->assertEquals('user', $reflection->invoke($this->rlsService, $user));
    }

    public function test_handles_missing_connection_gracefully(): void
    {
        config(['supabase.rls.db_connection' => 'nonexistent']);

        $connection = $this->rlsService->getConnection();
        $this->assertNotNull($connection);
    }

    private function canConnectToPgsql(): bool
    {
        try {
            DB::connection('pgsql')->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
