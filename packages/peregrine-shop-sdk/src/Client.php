<?php

declare(strict_types=1);

namespace Peregrine\ShopSdk;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Thin Bearer-token HTTP client for the public Peregrine API v1.
 * Returns decoded arrays (no DTO layer in MVP — keeps the SDK
 * dependency-light). Throws `\RuntimeException` with the response body
 * on non-2xx responses ; consumers can re-decode the body for the
 * structured `error.code` / `error.message`.
 */
final class Client
{
    private readonly GuzzleClient $http;

    public function __construct(
        string $baseUrl,
        private readonly string $apiKey,
        ?GuzzleClient $http = null,
    ) {
        $this->http = $http ?? new GuzzleClient([
            'base_uri' => rtrim($baseUrl, '/').'/api/v1/',
            'http_errors' => false,
            'timeout' => 30,
        ]);
    }

    /** @return array<string, mixed> */
    public function shopMe(): array
    {
        return $this->get('shop/me');
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function configurations(array $query = []): array
    {
        return $this->get('configurations', $query);
    }

    /** @return array<string, mixed> */
    public function configuration(int $id): array
    {
        return $this->get("configurations/{$id}");
    }

    /** @return array<string, mixed> */
    public function order(string $externalOrderId): array
    {
        return $this->get('orders/'.rawurlencode($externalOrderId));
    }

    /**
     * Create a webhook endpoint. Returns the endpoint payload AND the
     * one-shot signing_secret (in `meta.signing_secret`). Store the
     * secret immediately — it is never returned again.
     *
     * @param  array<int, string>  $subscribedEvents
     * @return array<string, mixed>
     */
    public function createWebhookEndpoint(string $name, string $url, array $subscribedEvents): array
    {
        return $this->post('webhooks/endpoints', [
            'name' => $name,
            'url' => $url,
            'subscribed_events' => $subscribedEvents,
        ]);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function get(string $path, array $query = []): array
    {
        try {
            $response = $this->http->get($path, [
                'headers' => $this->headers(),
                'query' => $query,
            ]);
        } catch (GuzzleException $e) {
            throw new \RuntimeException("HTTP error: {$e->getMessage()}", 0, $e);
        }
        return $this->decode($response->getStatusCode(), (string) $response->getBody());
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function post(string $path, array $body): array
    {
        try {
            $response = $this->http->post($path, [
                'headers' => $this->headers(),
                'json' => $body,
            ]);
        } catch (GuzzleException $e) {
            throw new \RuntimeException("HTTP error: {$e->getMessage()}", 0, $e);
        }
        return $this->decode($response->getStatusCode(), (string) $response->getBody());
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->apiKey,
            'Accept' => 'application/json',
            'User-Agent' => 'peregrine-shop-sdk/0.1',
        ];
    }

    /** @return array<string, mixed> */
    private function decode(int $status, string $body): array
    {
        $decoded = json_decode($body, true);
        if (! is_array($decoded)) {
            throw new \RuntimeException("Non-JSON response (HTTP {$status}): ".substr($body, 0, 200));
        }
        if ($status >= 400) {
            $code = $decoded['error']['code'] ?? 'unknown';
            $message = $decoded['error']['message'] ?? 'unknown';
            throw new \RuntimeException("API error (HTTP {$status} / {$code}): {$message}");
        }
        return $decoded;
    }
}
