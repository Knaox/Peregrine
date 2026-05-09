<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\ConfigurationsController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\OrdersController;
use App\Http\Controllers\Api\V1\ShopMeController;
use App\Http\Controllers\Api\V1\WebhookEndpointsController;
use App\Http\Middleware\EnsureShopApiKey;
use Illuminate\Support\Facades\Route;

/*
 * Public API v1 surface for third-party shop integrations.
 *
 * `/health` is public ; everything else expects
 *   Authorization: Bearer psk_<env>_<48 hex>
 *
 * Per-route ability gating uses the second middleware argument :
 *   ->middleware(EnsureShopApiKey::class.':configurations:read')
 *
 * The whole stack is rate-limited via the `api-v1` named limiter
 * (60/min per API key, 300/min per IP) configured in
 * AppServiceProvider::boot().
 */

Route::get('/health', [HealthController::class, 'show'])
    ->name('health');

Route::middleware(EnsureShopApiKey::class)->group(function (): void {
    Route::get('/shop/me', [ShopMeController::class, 'show'])->name('shop.me');
});

Route::middleware(EnsureShopApiKey::class.':configurations:read')->group(function (): void {
    Route::get('/configurations', [ConfigurationsController::class, 'index'])->name('configurations.index');
    Route::get('/configurations/{id}', [ConfigurationsController::class, 'show'])
        ->whereNumber('id')
        ->name('configurations.show');
});

Route::middleware(EnsureShopApiKey::class.':orders:read')->group(function (): void {
    Route::get('/orders/{externalOrderId}', [OrdersController::class, 'show'])->name('orders.show');
});

Route::middleware(EnsureShopApiKey::class.':webhooks:read')->group(function (): void {
    Route::get('/webhooks/endpoints', [WebhookEndpointsController::class, 'index'])->name('webhooks.endpoints.index');
});

Route::middleware(EnsureShopApiKey::class.':webhooks:write')->group(function (): void {
    Route::post('/webhooks/endpoints', [WebhookEndpointsController::class, 'store'])->name('webhooks.endpoints.store');
    Route::post('/webhooks/endpoints/{id}/rotate-secret', [WebhookEndpointsController::class, 'rotateSecret'])
        ->whereNumber('id')
        ->name('webhooks.endpoints.rotate-secret');
    Route::delete('/webhooks/endpoints/{id}', [WebhookEndpointsController::class, 'destroy'])
        ->whereNumber('id')
        ->name('webhooks.endpoints.destroy');
});
