# peregrine-shop-sdk

PHP SDK for integrating a third-party shop with Peregrine's public API
v1 + Standard Webhooks.

## Install

```bash
composer require peregrine/peregrine-shop-sdk
```

## Quickstart

### Read the catalog

```php
use Peregrine\ShopSdk\Client;

$client = new Client('https://peregrine.example.com', 'psk_live_xxx...');
$configs = $client->configurations();
foreach ($configs['data'] as $config) {
    echo "{$config['internal_name']} : {$config['ram']} MB / {$config['cpu']} %\n";
}
```

### Tag a Stripe Checkout Session

```php
use Peregrine\ShopSdk\Stripe\MetadataBuilder;

$session = $stripe->checkout->sessions->create([
    'mode' => 'subscription',
    'line_items' => [[ 'price' => 'price_xxx', 'quantity' => 1 ]],
    'success_url' => 'https://my-shop.example.com/success',
    'cancel_url' => 'https://my-shop.example.com/cancel',
    'customer_email' => $buyer->email,
    'metadata' => MetadataBuilder::create()
        ->configuration(42)              // ServerConfiguration ID from /api/v1/configurations
        ->shop(7)                         // Your Shop ID in Peregrine
        ->user($buyer->email)
        ->order('shop-order-1234')        // Your own opaque order reference
        ->build(),
]);
```

### Verify an inbound webhook

```php
use Peregrine\ShopSdk\Webhooks\StandardWebhookVerifier;

$verifier = new StandardWebhookVerifier();
$id = $request->header('webhook-id');
$ts = $request->header('webhook-timestamp');
$sig = $request->header('webhook-signature');
$body = $request->getContent();

if (! $verifier->verify($id, $ts, $body, $sig, $endpointSigningSecret)) {
    return response('invalid signature', 401);
}

$payload = json_decode($body, true);
// $payload['type']      e.g. "configuration.updated"
// $payload['id']        same as webhook-id (use to dedupe)
// $payload['data']      the configuration snapshot
```

## Endpoints exposed by the SDK

- `shopMe()` → `GET /shop/me`
- `configurations(array $query)` → `GET /configurations` (cursor pagination)
- `configuration(int $id)` → `GET /configurations/{id}`
- `order(string $externalOrderId)` → `GET /orders/{externalOrderId}`
- `createWebhookEndpoint(name, url, subscribedEvents)` → `POST /webhooks/endpoints`

The full reference is at `https://peregrine.example.com/docs` (Swagger UI).

## License

MIT
