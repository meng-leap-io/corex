<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\CheckAdminRole;
use App\Http\Middleware\SetRlsContext;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class RlsMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_set_rls_context_sets_user_context(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);

        $middleware = app(SetRlsContext::class);
        $response = $middleware->handle($request, fn () => response('ok'));

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_set_rls_context_sets_guest_context(): void
    {
        $request = Request::create('/test', 'GET');

        $middleware = app(SetRlsContext::class);
        $response = $middleware->handle($request, fn () => response('ok'));

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_admin_middleware_allows_admin_user(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin);

        $request = Request::create('/admin/test', 'GET');
        $request->setUserResolver(fn () => $admin);

        $middleware = app(CheckAdminRole::class);
        $response = $middleware->handle($request, fn () => response('ok'));

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_admin_middleware_denies_regular_user(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_USER]);
        $this->actingAs($user);

        $request = Request::create('/admin/test', 'GET');
        $request->setUserResolver(fn () => $user);
        $request->headers->set('Accept', 'application/json');

        $middleware = app(CheckAdminRole::class);
        $response = $middleware->handle($request, fn () => response('ok'));

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_admin_middleware_denies_unauthenticated(): void
    {
        $request = Request::create('/admin/test', 'GET');
        $request->headers->set('Accept', 'application/json');

        $middleware = app(CheckAdminRole::class);
        $response = $middleware->handle($request, fn () => response('ok'));

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_admin_middleware_respects_admin_email_config(): void
    {
        config(['app.admin_email' => 'admin@corex.dev']);

        $admin = User::factory()->create([
            'email' => 'admin@corex.dev',
            'role' => 'user',
        ]);
        $this->actingAs($admin);

        $this->assertTrue(CheckAdminRole::isAdmin($admin));

        $request = Request::create('/admin/test', 'GET');
        $request->setUserResolver(fn () => $admin);

        $middleware = app(CheckAdminRole::class);
        $response = $middleware->handle($request, fn () => response('ok'));

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_check_admin_role_static_method(): void
    {
        $admin = User::factory()->make(['role' => User::ROLE_ADMIN]);
        $user = User::factory()->make(['role' => User::ROLE_USER]);
        $noUser = null;

        $this->assertTrue(CheckAdminRole::isAdmin($admin));
        $this->assertFalse(CheckAdminRole::isAdmin($user));
        $this->assertFalse(CheckAdminRole::isAdmin($noUser));
    }

    public function test_rls_middleware_skips_when_no_pgsql_connection(): void
    {
        config(['supabase.rls.db_connection' => 'sqlite']);

        $request = Request::create('/test', 'GET');

        $middleware = app(SetRlsContext::class);
        $response = $middleware->handle($request, fn () => response('ok'));

        $this->assertEquals(200, $response->getStatusCode());
    }
}
