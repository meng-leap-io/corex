<?php

namespace Tests\Unit\Services\Webhook;

use App\Services\Webhook\WebhookSignature;
use Illuminate\Http\Request;
use Tests\TestCase;

class WebhookSignatureTest extends TestCase
{
    private WebhookSignature $signature;

    private string $testSecret = 'test_secret_key_12345';

    protected function setUp(): void
    {
        parent::setUp();
        $this->signature = new WebhookSignature($this->testSecret);
    }

    public function test_signs_and_verifies_payload(): void
    {
        $payload = ['event' => 'test', 'data' => ['key' => 'value']];
        $signed = $this->signature->sign($payload);

        $parts = explode('.', $signed);
        $this->assertCount(2, $parts);

        $request = Request::create('/webhooks/test', 'POST', [], [], [], [], json_encode($payload));
        $request->headers->set('X-Webhook-Signature', $parts[1]);
        $request->headers->set('X-Webhook-Timestamp', $parts[0]);

        $this->assertTrue($this->signature->verify($request));
    }

    public function test_rejects_invalid_signature(): void
    {
        $payload = ['event' => 'test'];

        $request = Request::create('/webhooks/test', 'POST', [], [], [], [], json_encode($payload));
        $request->headers->set('X-Webhook-Signature', 'invalid_signature');
        $request->headers->set('X-Webhook-Timestamp', (string) time());

        $this->assertFalse($this->signature->verify($request));
    }

    public function test_rejects_expired_timestamp(): void
    {
        $payload = ['event' => 'test'];
        $oldTimestamp = (string) (time() - 600);

        $request = Request::create('/webhooks/test', 'POST', [], [], [], [], json_encode($payload));
        $request->headers->set('X-Webhook-Timestamp', $oldTimestamp);

        $this->assertFalse($this->signature->verify($request));
    }

    public function test_rejects_missing_signature(): void
    {
        $payload = ['event' => 'test'];

        $request = Request::create('/webhooks/test', 'POST', [], [], [], [], json_encode($payload));

        $this->assertFalse($this->signature->verify($request));
    }

    public function test_verifies_stripe_signature(): void
    {
        $timestamp = time();
        $payload = json_encode(['type' => 'test']);
        $signedPayload = "{$timestamp}.{$payload}";
        $expected = hash_hmac('sha256', $signedPayload, $this->testSecret);

        $request = Request::create('/webhooks/stripe', 'POST', [], [], [], [], $payload);
        $request->headers->set('stripe-signature', "t={$timestamp},v1={$expected}");

        $this->assertTrue($this->signature->verifyStripe($request));
    }

    public function test_verifies_github_signature(): void
    {
        $payload = json_encode(['action' => 'test']);
        $expected = 'sha256='.hash_hmac('sha256', $payload, $this->testSecret);

        $request = Request::create('/webhooks/github', 'POST', [], [], [], [], $payload);
        $request->headers->set('x-hub-signature-256', $expected);

        $this->assertTrue($this->signature->verifyGitHub($request));
    }

    public function test_verifies_resend_signature(): void
    {
        $payload = json_encode(['type' => 'email.sent']);
        $expected = hash_hmac('sha256', $payload, $this->testSecret);

        $request = Request::create('/webhooks/resend', 'POST', [], [], [], [], $payload);
        $request->headers->set('svix-signature', "v1={$expected}");

        $this->assertTrue($this->signature->verifyResend($request));
    }

    public function test_timestamp_validation(): void
    {
        $now = (string) time();
        $old = (string) (time() - 400);
        $future = (string) (time() + 400);

        $this->assertTrue($this->signature->isTimestampValid($now));
        $this->assertFalse($this->signature->isTimestampValid($old));
        $this->assertFalse($this->signature->isTimestampValid($future));
        $this->assertFalse($this->signature->isTimestampValid(null));
        $this->assertFalse($this->signature->isTimestampValid('invalid'));
    }

    public function test_generates_secret(): void
    {
        $secret = WebhookSignature::generateSecret();
        $this->assertNotEmpty($secret);
        $this->assertEquals(64, strlen($secret));
    }

    public function test_verify_payload_direct(): void
    {
        $payload = '{"event":"test"}';
        $timestamp = (string) time();
        $data = $timestamp.'.'.$payload;
        $signature = hash_hmac('sha256', $data, $this->testSecret);

        $this->assertTrue(
            $this->signature->verifyPayload($payload, $timestamp, $signature)
        );

        $this->assertFalse(
            $this->signature->verifyPayload($payload, $timestamp, 'bad_signature')
        );
    }
}
