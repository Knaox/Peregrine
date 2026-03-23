<?php

namespace App\Services\Pelican\Concerns;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

trait MakesClientRequests
{
    private function baseUrl(): string
    {
        return rtrim((string) config('panel.pelican.url'), '/');
    }

    private function clientApiKey(): string
    {
        return (string) config('panel.pelican.client_api_key');
    }

    private function request(): PendingRequest
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->clientApiKey(),
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])
            ->retry(3, 100)
            ->baseUrl($this->baseUrl());
    }
}
