<?php

namespace App\Services\Pelican\Concerns;

use App\Services\Pelican\PelicanCredentials;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

trait MakesClientRequests
{
    private function baseUrl(): string
    {
        return app(PelicanCredentials::class)->url();
    }

    private function clientApiKey(): string
    {
        return app(PelicanCredentials::class)->clientApiKey();
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
