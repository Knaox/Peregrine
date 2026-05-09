<?php

declare(strict_types=1);

namespace Peregrine\ShopSdk\Stripe;

/**
 * Fluent builder for the 4 required `peregrine_*` metadata keys plus
 * the optional `peregrine_metadata` JSON bag and `peregrine_server_id`
 * resubscribe hint. Use it when creating a Stripe Checkout Session,
 * Subscription, or Product on the shop side :
 *
 *     $stripe->checkout->sessions->create([
 *         ...,
 *         'metadata' => MetadataBuilder::create()
 *             ->configuration(42)
 *             ->shop(7)
 *             ->user('buyer@example.com')
 *             ->order('shop-order-1234')
 *             ->build(),
 *     ]);
 */
final class MetadataBuilder
{
    private ?int $configurationId = null;

    private ?int $shopId = null;

    private ?string $userEmail = null;

    private ?string $externalOrderId = null;

    private ?int $resubscribeServerId = null;

    /** @var array<string, mixed> */
    private array $extra = [];

    public static function create(): self
    {
        return new self();
    }

    public function configuration(int $id): self
    {
        $this->configurationId = $id;
        return $this;
    }

    public function shop(int $id): self
    {
        $this->shopId = $id;
        return $this;
    }

    public function user(string $email): self
    {
        $this->userEmail = $email;
        return $this;
    }

    public function order(string $externalOrderId): self
    {
        $this->externalOrderId = $externalOrderId;
        return $this;
    }

    public function resubscribeServer(int $peregrineServerId): self
    {
        $this->resubscribeServerId = $peregrineServerId;
        return $this;
    }

    /**
     * @param  array<string, mixed>  $bag
     */
    public function withExtra(array $bag): self
    {
        $this->extra = $bag;
        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function build(): array
    {
        foreach ([
            'peregrine_configuration_id' => $this->configurationId,
            'peregrine_shop_id' => $this->shopId,
            'peregrine_user_email' => $this->userEmail,
            'peregrine_external_order_id' => $this->externalOrderId,
        ] as $key => $value) {
            if ($value === null || $value === '') {
                throw new \LogicException("Metadata builder missing required field: {$key}");
            }
        }

        $payload = [
            'peregrine_configuration_id' => (string) $this->configurationId,
            'peregrine_shop_id' => (string) $this->shopId,
            'peregrine_user_email' => $this->userEmail,
            'peregrine_external_order_id' => $this->externalOrderId,
        ];

        if ($this->resubscribeServerId !== null) {
            $payload['peregrine_server_id'] = (string) $this->resubscribeServerId;
            $payload['is_resubscribe'] = 'true';
        }
        if ($this->extra !== []) {
            $payload['peregrine_metadata'] = (string) json_encode($this->extra);
        }

        return $payload;
    }
}
