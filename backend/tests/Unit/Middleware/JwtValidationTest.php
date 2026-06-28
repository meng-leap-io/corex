<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\JwtValidation;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;

class JwtValidationTest extends TestCase
{
    private JwtValidation $middleware;

    private string $secret;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new JwtValidation;
        $this->secret = config('jwt.secret', 'test-jwt-secret-key');
    }

    public function test_passes_with_valid_jwt(): void
    {
        $request = Request::create('/api/test', 'GET');
        $request->headers->set('Authorization', 'Bearer '.$this->generateValidToken());

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
        $this->assertStringContainsString('Missing authentication token.', $response->getContent());
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
        $request->headers->set('Authorization', 'Bearer '.$this->generateExpiredToken());

        $response = $this->middleware->handle($request, function ($req) {
            return new Response('OK', 200);
        });

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function test_extracts_user_from_valid_token(): void
    {
        $request = Request::create('/api/test', 'GET');
        $request->headers->set('Authorization', 'Bearer '.$this->generateValidToken());

        $response = $this->middleware->handle($request, function ($req) {
            $this->assertNotNull($req->input('jwt_payload'));

            return new Response('OK', 200);
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    private function generateValidToken(): string
    {
        return JWT::encode(
            payload: [
                'sub' => 'user-uuid-123',
                'iat' => time(),
                'exp' => time() + 3600,
                'scope' => 'api',
            ],
            key: $this->secret,
            alg: 'HS256',
        );
    }

    private function generateExpiredToken(): string
    {
        return JWT::encode(
            payload: [
                'sub' => 'user-uuid-123',
                'iat' => time() - 7200,
                'exp' => time() - 3600,
                'scope' => 'api',
            ],
            key: $this->secret,
            alg: 'HS256',
        );
    }
}
