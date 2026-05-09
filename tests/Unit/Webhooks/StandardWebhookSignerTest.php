<?php

declare(strict_types=1);

namespace Tests\Unit\Webhooks;

use App\Webhooks\StandardWebhookSigner;
use PHPUnit\Framework\TestCase;

class StandardWebhookSignerTest extends TestCase
{
    public function test_sign_produces_v1_prefixed_base64_signature(): void
    {
        $signer = new StandardWebhookSigner();
        $sig = $signer->sign('msg_01', '1747300000', '{"hello":"world"}', 'whsec_test');

        $this->assertStringStartsWith('v1,', $sig);

        // Recompute manually to validate format.
        $expected = 'v1,'.base64_encode(hash_hmac('sha256', 'msg_01.1747300000.{"hello":"world"}', 'whsec_test', true));
        $this->assertSame($expected, $sig);
    }

    public function test_verify_accepts_fresh_correctly_signed_payload(): void
    {
        $signer = new StandardWebhookSigner();
        $now = (string) time();
        $body = '{"hello":"world"}';
        $sig = $signer->sign('msg_01', $now, $body, 's3cr3t');

        $this->assertTrue($signer->verify('msg_01', $now, $body, 's3cr3t', $sig));
    }

    public function test_verify_rejects_tampered_body(): void
    {
        $signer = new StandardWebhookSigner();
        $now = (string) time();
        $sig = $signer->sign('msg_01', $now, '{"hello":"world"}', 's3cr3t');

        $this->assertFalse($signer->verify('msg_01', $now, '{"tampered":true}', 's3cr3t', $sig));
    }

    public function test_verify_rejects_tampered_id(): void
    {
        $signer = new StandardWebhookSigner();
        $now = (string) time();
        $sig = $signer->sign('msg_01', $now, '{}', 's3cr3t');

        $this->assertFalse($signer->verify('msg_02', $now, '{}', 's3cr3t', $sig));
    }

    public function test_verify_rejects_expired_timestamp(): void
    {
        $signer = new StandardWebhookSigner();
        $tenMinAgo = (string) (time() - 600);
        $sig = $signer->sign('msg_01', $tenMinAgo, '{}', 's3cr3t');

        $this->assertFalse($signer->verify('msg_01', $tenMinAgo, '{}', 's3cr3t', $sig));
    }

    public function test_verify_accepts_signature_within_tolerance(): void
    {
        $signer = new StandardWebhookSigner();
        $threeMinAgo = (string) (time() - 180);
        $sig = $signer->sign('msg_01', $threeMinAgo, '{}', 's3cr3t');

        $this->assertTrue($signer->verify('msg_01', $threeMinAgo, '{}', 's3cr3t', $sig));
    }

    public function test_verify_supports_multiple_space_separated_signatures(): void
    {
        $signer = new StandardWebhookSigner();
        $now = (string) time();
        $bodyA = '{}';
        $sigA = $signer->sign('msg_01', $now, $bodyA, 'old_secret');
        $sigB = $signer->sign('msg_01', $now, $bodyA, 'new_secret');

        // Receiver tries new_secret ; gets header with both sigs.
        $this->assertTrue($signer->verify('msg_01', $now, $bodyA, 'new_secret', $sigA.' '.$sigB));
    }
}
