<?php

namespace App\Services\Pelican;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Shared HTTP helper for the Pelican Application API clients.
 *
 * Holds the auth + base URL resolution in one place and exposes two
 * primitives used by every domain client: a configured `request()` and
 * a `fetchAllPages()` helper that materializes paginated endpoints into
 * an array of DTOs.
 */
class PelicanHttpClient
{
    public function request(): PendingRequest
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey(),
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])
            ->retry(3, 100)
            ->baseUrl($this->baseUrl());
    }

    /**
     * Fetch all pages for a paginated endpoint and map each item through a DTO.
     *
     * @template T
     *
     * @param class-string<T> $dtoClass
     * @param array<string, mixed> $query
     *
     * @return T[]
     *
     * @throws RequestException
     */
    public function fetchAllPages(string $endpoint, string $dtoClass, array $query = []): array
    {
        $items = [];
        $page = 1;

        do {
            $response = $this->request()
                ->get($endpoint, array_merge($query, ['page' => $page]))
                ->throw();

            $json = $response->json();
            $data = $json['data'] ?? [];

            foreach ($data as $item) {
                $items[] = $dtoClass::fromApiResponse($item);
            }

            $totalPages = $json['meta']['pagination']['total_pages'] ?? 1;
            $page++;
        } while ($page <= $totalPages);

        return $items;
    }

    private function baseUrl(): string
    {
        return app(PelicanCredentials::class)->url();
    }

    private function apiKey(): string
    {
        return app(PelicanCredentials::class)->adminApiKey();
    }
}
