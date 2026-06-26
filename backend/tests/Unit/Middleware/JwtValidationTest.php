<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\JwtValidation;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;

class JwtValidationTest extends TestCase
{
    private JwtValidation $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new JwtValidation();
    }

    public function test_passes_with_valid_jwt(): void
    {
        $request = Request::create('/api/test', 'GET');
        $request->headers->set('Authorization', 'Bearer ' . $this->generateValidToken());

        $response = $this->middleware->handle($request, function ($req) {
            return new Response('OK', 200);
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_blocks_request_without_token(): void
    {
        $request = Request::create('/api/test', 'GET');

        $response = $this->middleware->handle($request, function ($req) {
            return new Response('OK', 200);
        });

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertStringContainsString('unauthenticated', $response->getContent());
    }

    public function test_blocks_request_with_malformed_token(): void
    {
        $request = Request::create('/api/test', 'GET');
        $request->headers->set('Authorization', 'Bearer invalid-token');

        $response = $this->middleware->handle($request, function ($req) {
            return new Response('OK', 200);
        });

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function test_blocks_request_with_expired_token(): void
    {
        $request = Request::create('/api/test', 'GET');
        $request->headers->set('Authorization', 'Bearer ' . $this->generateExpiredToken());

        $response = $this->middleware->handle($request, function ($req) {
            return new Response('OK', 200);
        });

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function test_extracts_user_from_valid_token(): void
    {
        $request = Request::create('/api/test', 'GET');
        $request->headers->set('Authorization', 'Bearer ' . $this->generateValidToken());

        $this->middleware->handle($request, function ($req) {
            $this->assertNotNull($req->attributes->get('jwt_user'));
            return new Response('OK', 200);
        });
    }

    private function generateValidToken(): string
    {
        $payload = [
            'sub' => 'user-uuid-123',
            'iat' => time(),
            'exp' => time() + 3600,
            'scope' => 'api',
        ];

        return 'eyJhbGciOiJIUzI1NiJ9.' . base64_encode(json_encode($payload)) . '.test-signature';
    }

    private function generateExpiredToken(): string
    {
        $payload = [
            'sub' => 'user-uuid-123',
            'iat' => time() - 7200,
            'exp' => time() - 3600,
            'scope' => 'api',
        ];

        return 'eyJhbGciOiJIUzI1NiJ9.' . base64_encode(json_encode($payload)) . '.test-signature';
    }
}
