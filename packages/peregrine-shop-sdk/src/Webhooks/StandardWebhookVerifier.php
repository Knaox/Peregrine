<?php

declare(strict_types=1);

namespace Peregrine\ShopSdk\Webhooks;

/**
 * Verify a Standard Webhooks signature on the receiving end.
 *
 * Headers expected on the inbound request :
 *   webhook-id        : <UUID>
 *   webhook-timestamp : <unix seconds>
 *   webhook-signature : v1,<base64-hmac-sha256("{id}.{ts}.{body}", secret)>
 *
 * The verifier MUST :
 *   - reject `abs(now - timestamp) > $toleranceSeconds` (anti-replay)
 *   - constant-time compare against the freshly-recomputed signature
 *   - support multiple space-separated signatures in the header (rotation)
 *
 * Receivers SHOULD additionally dedupe on `webhook-id` (their own table)
 * — Peregrine generates one UUID per emission and never replays the
 * same id even on retries.
 */
final class StandardWebhookVerifier
{
    public function __construct(private readonly int $toleranceSeconds = 300) {}

    public function verify(
        string $id,
        string $timestamp,
        string $body,
        string $signatureHeader,
        string $secret,
    ): bool {
        $now = time();
        $ts = (int) $timestamp;
        if ($ts <= 0 || abs($now - $ts) > $this->toleranceSeconds) {
            return false;
        }

        $expected = 'v1,'.base64_encode(
            hash_hmac('sha256', "{$id}.{$timestamp}.{$body}", $secret, true)
        );

        foreach (explode(' ', $signatureHeader) as $candidate) {
            if (hash_equals($expected, trim($candidate))) {
                return true;
            }
        }
        return false;
    }
}
