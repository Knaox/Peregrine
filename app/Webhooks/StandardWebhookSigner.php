<?php

declare(strict_types=1);

namespace App\Webhooks;

/**
 * Pure-PHP signer compliant with the Standard Webhooks spec
 * (standardwebhooks.com).
 *
 *   webhook-id        : <UUID v7>
 *   webhook-timestamp : <unix seconds>
 *   webhook-signature : v1,<base64-hmac-sha256("{id}.{ts}.{body}", secret)>
 *
 * The receiver MUST :
 *  - reject `abs(now - timestamp) > 300` (anti-replay window)
 *  - dedupe on `webhook-id`
 *  - constant-time compare the signature against its own re-computation
 *
 * The signer is stateless — feed it the (id, timestamp, body, secret)
 * tuple and it returns the header value. Plug it into Spatie's webhook
 * call via `withHeaders()` ; we bypass Spatie's own signer because its
 * interface only exposes the body, not the id/timestamp.
 */
final class StandardWebhookSigner
{
    public const SIGNATURE_VERSION = 'v1';

    public function sign(string $id, string $timestamp, string $body, string $secret): string
    {
        $signedContent = "{$id}.{$timestamp}.{$body}";
        $signature = base64_encode(
            hash_hmac('sha256', $signedContent, $secret, true)
        );
        return self::SIGNATURE_VERSION.','.$signature;
    }

    /**
     * Constant-time verification used by the SDK / receivers. Returns
     * true when ANY of the signatures in the header (space-separated)
     * matches the freshly-computed expected value.
     */
    public function verify(
        string $id,
        string $timestamp,
        string $body,
        string $secret,
        string $headerValue,
        int $toleranceSeconds = 300,
    ): bool {
        // Replay window check
        $now = time();
        $ts = (int) $timestamp;
        if ($ts <= 0 || abs($now - $ts) > $toleranceSeconds) {
            return false;
        }

        $expected = $this->sign($id, $timestamp, $body, $secret);

        // Header may carry multiple sigs (e.g. during rotation) separated
        // by spaces. ANY match wins.
        foreach (explode(' ', $headerValue) as $candidate) {
            if (hash_equals($expected, trim($candidate))) {
                return true;
            }
        }
        return false;
    }
}
