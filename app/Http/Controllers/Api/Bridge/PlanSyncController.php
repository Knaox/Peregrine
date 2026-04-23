<?php

namespace App\Http\Controllers\Api\Bridge;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bridge\BridgeUpsertPlanRequest;
use App\Models\BridgeSyncLog;
use App\Models\ServerPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Receives plan-sync calls from the Shop. Two endpoints :
 *  - upsert : create or refresh a ServerPlan from a payload pushed by the Shop
 *  - destroy : deactivate (NOT delete) a plan when the Shop removes it
 *
 * The HMAC signature is validated upstream by VerifyBridgeSignature middleware.
 * Every call is logged in `bridge_sync_logs` for audit (admin Filament page).
 *
 * Update semantics : when re-pushing an existing shop_plan_id, only Shop-owned
 * fields are overwritten (cf. ServerPlan::SHOP_OWNED_FIELDS). The Peregrine-
 * configured fields (egg_id, node_id, docker_image, env_var_mapping, toggles)
 * are NEVER touched by a Shop push — they belong to the Peregrine admin.
 */
class PlanSyncController extends Controller
{
    /**
     * No-op health check used by the shop's "Test connection" button. Validates
     * that the URL is reachable AND that the HMAC secret matches on both ends —
     * the middleware has already rejected if either was wrong by the time we
     * get here. No DB writes, no audit log entry (a health check is not a
     * sync action).
     */
    public function ping(Request $request): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'service' => 'peregrine-bridge',
            'version' => '1.0',
            'received_at' => now()->toIso8601String(),
        ], 200);
    }

    public function upsert(BridgeUpsertPlanRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $shopPlanId = (int) $validated['shop_plan_id'];

        return DB::transaction(function () use ($validated, $shopPlanId, $request) {
            $existing = ServerPlan::where('shop_plan_id', $shopPlanId)->first();

            $shopOwnedAttributes = $this->mapPayloadToShopAttributes($validated);

            if ($existing !== null) {
                // Update path : preserve Peregrine-configured fields by only
                // touching Shop-owned columns.
                $existing->fill($shopOwnedAttributes);
                $existing->last_shop_synced_at = now();
                $existing->save();
                $plan = $existing;
            } else {
                // Create path : merge Shop attributes with safe Peregrine defaults
                // (the migration also defines defaults — repeating here for explicitness).
                $plan = ServerPlan::create(array_merge(
                    $shopOwnedAttributes,
                    [
                        'auto_deploy' => false,
                        'port_count' => 1,
                        'enable_oom_killer' => true,
                        'start_on_completion' => true,
                        'skip_install_script' => false,
                        'dedicated_ip' => false,
                        'feature_limits_databases' => 0,
                        'feature_limits_backups' => 3,
                        'feature_limits_allocations' => 1,
                        'last_shop_synced_at' => now(),
                    ],
                ));
            }

            $status = $plan->isReadyToProvision() ? 'ready' : 'needs_admin_config';

            $response = [
                'peregrine_plan_id' => $plan->id,
                'synced_at' => $plan->last_shop_synced_at->toIso8601String(),
                'status' => $status,
            ];

            BridgeSyncLog::create([
                'action' => 'upsert',
                'shop_plan_id' => $shopPlanId,
                'server_plan_id' => $plan->id,
                'request_payload' => $this->scrubPayloadForLog($validated),
                'response_status' => 200,
                'response_body' => json_encode($response),
                'ip_address' => $request->ip(),
                'signature_valid' => (bool) $request->attributes->get('bridge.signature_valid', false),
                'attempted_at' => now(),
            ]);

            return response()->json($response, 200);
        });
    }

    public function destroy(int $shopPlanId, Request $request): JsonResponse
    {
        $plan = ServerPlan::where('shop_plan_id', $shopPlanId)->first();

        if ($plan === null) {
            $response = ['error' => 'plan_not_found'];

            BridgeSyncLog::create([
                'action' => 'delete',
                'shop_plan_id' => $shopPlanId,
                'server_plan_id' => null,
                'request_payload' => null,
                'response_status' => 404,
                'response_body' => json_encode($response),
                'ip_address' => $request->ip(),
                'signature_valid' => (bool) $request->attributes->get('bridge.signature_valid', false),
                'attempted_at' => now(),
            ]);

            return response()->json($response, 404);
        }

        return DB::transaction(function () use ($plan, $shopPlanId, $request) {
            $plan->update(['is_active' => false]);
            $deactivatedAt = now();

            $response = ['deactivated_at' => $deactivatedAt->toIso8601String()];

            BridgeSyncLog::create([
                'action' => 'delete',
                'shop_plan_id' => $shopPlanId,
                'server_plan_id' => $plan->id,
                'request_payload' => null,
                'response_status' => 200,
                'response_body' => json_encode($response),
                'ip_address' => $request->ip(),
                'signature_valid' => (bool) $request->attributes->get('bridge.signature_valid', false),
                'attempted_at' => $deactivatedAt,
            ]);

            return response()->json($response, 200);
        });
    }

    /**
     * Map the validated payload to ServerPlan column names. Only includes
     * Shop-owned fields — Peregrine config is never set from the Shop side.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function mapPayloadToShopAttributes(array $payload): array
    {
        $billing = $payload['billing'] ?? [];
        $specs = $payload['pelican_specs'] ?? [];
        $checkout = $payload['checkout'] ?? [];

        return [
            'shop_plan_id' => $payload['shop_plan_id'],
            'shop_plan_slug' => $payload['shop_plan_slug'],
            'shop_plan_type' => $payload['shop_plan_type'],
            'name' => $payload['name'],
            'description' => $payload['description'] ?? null,
            'is_active' => $payload['is_active'],
            'price_cents' => $billing['price_cents'] ?? null,
            'currency' => $billing['currency'] ?? null,
            'interval' => $billing['interval'] ?? null,
            'interval_count' => $billing['interval_count'] ?? null,
            'has_trial' => $billing['has_trial'] ?? false,
            'trial_interval' => $billing['trial_interval'] ?? null,
            'trial_interval_count' => $billing['trial_interval_count'] ?? null,
            'stripe_price_id' => $billing['stripe_price_id'] ?? null,
            'ram' => $specs['ram_mb'] ?? null,
            'cpu' => $specs['cpu_percent'] ?? null,
            'disk' => $specs['disk_mb'] ?? null,
            'swap_mb' => $specs['swap_mb'] ?? 0,
            'io_weight' => $specs['io_weight'] ?? 500,
            'cpu_pinning' => $specs['cpu_pinning'] ?? null,
            'checkout_custom_fields' => $checkout['custom_fields'] ?? null,
        ];
    }

    /**
     * Scrub potentially sensitive data from the audit-log payload.
     * Shop "checkout custom fields" might include user-entered values (server
     * names, custom inputs) — strip raw values before persisting in DB to
     * avoid storing free-text in our audit table.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function scrubPayloadForLog(array $payload): array
    {
        if (isset($payload['checkout']['custom_fields']) && is_array($payload['checkout']['custom_fields'])) {
            foreach ($payload['checkout']['custom_fields'] as &$field) {
                unset($field['value']);
            }
        }

        return $payload;
    }
}
