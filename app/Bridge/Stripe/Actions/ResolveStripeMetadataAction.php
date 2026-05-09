<?php

declare(strict_types=1);

namespace App\Bridge\Stripe\Actions;

use App\Bridge\Stripe\DTOs\ResolvedStripeContext;
use App\Bridge\Stripe\Exceptions\BridgeMetadataException;
use App\Models\ServerConfiguration;
use App\Models\Shop;

/**
 * Single source of truth for translating the inbound Stripe metadata bag
 * into a fully-validated `ResolvedStripeContext`. Centralised here so the
 * handlers (`ProvisionFromCheckoutAction`, `HandleRefundAction`,
 * `HandleDisputeAction`) all share the same authorisation contract :
 *
 *  1. The 4 required `peregrine_*` keys are present and non-empty.
 *  2. The shop exists AND is active.
 *  3. The configuration exists.
 *  4. The shop is authorised to resell the configuration via the
 *     `shop_server_configuration` pivot.
 *
 * Any deviation throws `BridgeMetadataException` ; the caller logs +
 * notifies admin + responds 200 (we never let a misconfigured shop
 * trigger Stripe retry storms).
 */
final class ResolveStripeMetadataAction
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __invoke(array $metadata): ResolvedStripeContext
    {
        $required = [
            'peregrine_configuration_id',
            'peregrine_shop_id',
            'peregrine_user_email',
            'peregrine_external_order_id',
        ];
        foreach ($required as $key) {
            if (! isset($metadata[$key]) || $metadata[$key] === '') {
                throw new BridgeMetadataException(
                    reason: 'missing_required_metadata',
                    details: ['missing_key' => $key],
                );
            }
        }

        $configurationId = is_numeric($metadata['peregrine_configuration_id'])
            ? (int) $metadata['peregrine_configuration_id']
            : null;
        $shopId = is_numeric($metadata['peregrine_shop_id'])
            ? (int) $metadata['peregrine_shop_id']
            : null;

        if ($configurationId === null || $shopId === null) {
            throw new BridgeMetadataException(
                reason: 'invalid_id_format',
                details: ['shop_id' => $metadata['peregrine_shop_id'], 'config_id' => $metadata['peregrine_configuration_id']],
            );
        }

        $shop = Shop::find($shopId);
        if ($shop === null) {
            throw new BridgeMetadataException(reason: 'unknown_shop', details: ['shop_id' => $shopId]);
        }
        if (! $shop->isActive()) {
            throw new BridgeMetadataException(reason: 'shop_suspended', details: ['shop_id' => $shopId]);
        }

        $configuration = ServerConfiguration::find($configurationId);
        if ($configuration === null) {
            throw new BridgeMetadataException(
                reason: 'unknown_configuration',
                details: ['configuration_id' => $configurationId],
            );
        }

        // Pivot check : the shop must be authorised to resell this
        // configuration. The `is_visible` toggle is intentionally NOT
        // checked here — visibility scopes the public catalog endpoint,
        // but a shop can still complete a checkout it had reserved at the
        // moment the toggle was on. Otherwise admins flipping `is_visible`
        // off would race-cancel in-flight checkouts.
        $authorised = $shop->serverConfigurations()
            ->where('server_configurations.id', $configuration->id)
            ->exists();
        if (! $authorised) {
            throw new BridgeMetadataException(
                reason: 'configuration_not_authorised_for_shop',
                details: ['shop_id' => $shopId, 'configuration_id' => $configurationId],
            );
        }

        $resubscribeId = isset($metadata['peregrine_server_id']) && is_numeric($metadata['peregrine_server_id'])
            ? (int) $metadata['peregrine_server_id']
            : null;

        $extra = isset($metadata['peregrine_metadata']) && is_string($metadata['peregrine_metadata'])
            ? (json_decode($metadata['peregrine_metadata'], true) ?: [])
            : [];

        return new ResolvedStripeContext(
            shop: $shop,
            configuration: $configuration,
            userEmail: strtolower(trim((string) $metadata['peregrine_user_email'])),
            externalOrderId: (string) $metadata['peregrine_external_order_id'],
            serverIdForResubscribe: $resubscribeId,
            extraMetadata: is_array($extra) ? $extra : [],
        );
    }
}
