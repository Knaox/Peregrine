<?php

declare(strict_types=1);

use App\Models\Server;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Plugins\MinecraftModpackInstaller\Enums\ModpackLoader;
use Plugins\MinecraftModpackInstaller\Enums\ModpackProvider;
use Plugins\MinecraftModpackInstaller\Exceptions\InstallationConflictException;
use Plugins\MinecraftModpackInstaller\Exceptions\ProviderNotConfiguredException;
use Plugins\MinecraftModpackInstaller\Exceptions\ProviderRequestException;
use Plugins\MinecraftModpackInstaller\Exceptions\ServerNotEligibleException;
use Plugins\MinecraftModpackInstaller\Models\ModpackInstallation;
use Plugins\MinecraftModpackInstaller\Services\DTO\SearchCriteria;
use Plugins\MinecraftModpackInstaller\Services\EligibilityService;
use Plugins\MinecraftModpackInstaller\Services\InstallationOrchestrator;
use Plugins\MinecraftModpackInstaller\Services\ModpackInstallIntent;
use Plugins\MinecraftModpackInstaller\Services\ModpackProviderRegistry;
use Plugins\MinecraftModpackInstaller\Services\ModpackSettingsService;

/**
 * Routes are mounted under `/api/plugins/minecraft-modpack-installer` by the
 * plugin's ServiceProvider. Server lookup uses the public `identifier`
 * (matching the rest of the panel's plugin contract — see invitations).
 */
Route::middleware('auth')->group(function () {

    /**
     * Resolve a server the caller can access, or 404. Mirrors the helper
     * inlined in plugins/invitations so the user lookup behaves the same.
     */
    $resolveServer = function (string $serverIdentifier, Request $request): Server {
        return Server::where('identifier', $serverIdentifier)
            ->accessibleBy($request->user())
            ->firstOrFail();
    };

    $requirePerm = function (Request $request, Server $server, string $permission): void {
        if ($request->user()->is_admin) {
            return;
        }
        if (! $request->user()->hasServerPermission($server, $permission)) {
            abort(403, __('modpacks.errors.permission_denied'));
        }
    };

    $serializeProvider = function (ModpackProvider $id, ModpackProviderRegistry $registry): array {
        $provider = $registry->get($id);

        return [
            'id' => $id->value,
            'name' => $id->displayName(),
            'configured' => $provider->isConfigured(),
            'external_register_url' => $id->externalRegisterUrl(),
            'capabilities' => $provider->capabilities()->toArray(),
        ];
    };

    $serializeInstallation = function (?ModpackInstallation $installation): ?array {
        if ($installation === null) {
            return null;
        }

        return [
            'id' => $installation->id,
            'provider' => $installation->provider->value,
            'modpack_id' => $installation->modpack_id,
            'modpack_name' => $installation->modpack_name,
            'icon_url' => $installation->icon_url,
            'version_id' => $installation->version_id,
            'version_label' => $installation->version_label,
            'external_url' => $installation->external_url,
            'status' => $installation->status->value,
            'status_message' => $installation->status_message,
            'is_active' => $installation->status->isActive(),
            'java_version' => $installation->java_version,
            'started_at' => $installation->started_at?->toIso8601String(),
            'completed_at' => $installation->completed_at?->toIso8601String(),
        ];
    };

    // ---------------------------------------------------------------------
    // Discovery
    // ---------------------------------------------------------------------

    Route::get(
        'servers/{serverIdentifier}/modpacks/eligibility',
        function (string $serverIdentifier, Request $request, EligibilityService $eligibility) use ($resolveServer): JsonResponse {
            $server = $resolveServer($serverIdentifier, $request);

            return response()->json([
                'data' => [
                    'eligible' => $eligibility->isEligible($server),
                    'reason' => $eligibility->reason($server),
                ],
            ]);
        },
    );

    Route::get(
        'servers/{serverIdentifier}/modpacks/providers',
        function (string $serverIdentifier, Request $request, ModpackProviderRegistry $registry, EligibilityService $eligibility) use ($resolveServer, $requirePerm, $serializeProvider): JsonResponse {
            $server = $resolveServer($serverIdentifier, $request);
            $requirePerm($request, $server, 'modpack.read');

            if (! $eligibility->isEligible($server)) {
                abort(403, __('modpacks.errors.server_not_eligible'));
            }

            $data = [];
            foreach (ModpackProvider::cases() as $providerCase) {
                if (! $registry->has($providerCase)) {
                    continue;
                }
                $data[] = $serializeProvider($providerCase, $registry);
            }

            return response()->json(['data' => $data]);
        },
    );

    Route::get(
        'servers/{serverIdentifier}/modpacks/providers/{provider}/minecraft-versions',
        function (string $serverIdentifier, string $provider, Request $request, ModpackProviderRegistry $registry, EligibilityService $eligibility) use ($resolveServer, $requirePerm): JsonResponse {
            $server = $resolveServer($serverIdentifier, $request);
            $requirePerm($request, $server, 'modpack.read');

            if (! $eligibility->isEligible($server)) {
                abort(403, __('modpacks.errors.server_not_eligible'));
            }

            $providerEnum = ModpackProvider::tryFrom($provider);
            if ($providerEnum === null || ! $registry->has($providerEnum)) {
                return response()->json(['error' => 'modpacks.errors.unknown_provider'], 422);
            }
            $impl = $registry->get($providerEnum);
            if (! $impl->capabilities()->minecraftVersionFilter) {
                return response()->json(['data' => []]);
            }

            $cacheKey = "modpacks:{$provider}:mc-versions:public";
            $versions = Cache::remember($cacheKey, 6 * 3600, fn (): array => $impl->listMinecraftVersions());

            return response()->json(['data' => $versions]);
        },
    );

    // ---------------------------------------------------------------------
    // Search
    // ---------------------------------------------------------------------

    Route::get(
        'servers/{serverIdentifier}/modpacks/search',
        function (string $serverIdentifier, Request $request, ModpackProviderRegistry $registry, EligibilityService $eligibility, ModpackSettingsService $settings) use ($resolveServer, $requirePerm): JsonResponse {
            $server = $resolveServer($serverIdentifier, $request);
            $requirePerm($request, $server, 'modpack.read');

            if (! $eligibility->isEligible($server)) {
                abort(403, __('modpacks.errors.server_not_eligible'));
            }

            $validated = $request->validate([
                'provider' => ['nullable', 'string', 'in:modrinth,curseforge,atlauncher,ftb,technic,voidswrath'],
                'q' => ['nullable', 'string', 'max:255'],
                'mc' => ['nullable', 'string', 'max:32'],
                'loader' => ['nullable', 'string', 'in:forge,fabric,quilt,neoforge'],
                'page' => ['nullable', 'integer', 'min:1', 'max:200'],
                'size' => ['nullable', 'integer', 'in:6,12,24'],
            ]);

            $providerEnum = ModpackProvider::tryFrom($validated['provider'] ?? '')
                ?? $settings->defaultProvider();

            if (! $registry->has($providerEnum)) {
                return response()->json(['error' => 'modpacks.errors.unknown_provider'], 422);
            }

            $provider = $registry->get($providerEnum);
            if (! $provider->isConfigured()) {
                return response()->json(['error' => 'modpacks.errors.provider_not_configured'], 422);
            }

            $criteria = new SearchCriteria(
                query: $validated['q'] ?? null,
                minecraftVersion: $validated['mc'] ?? null,
                loader: ModpackLoader::tryFromAny($validated['loader'] ?? null),
                page: (int) ($validated['page'] ?? 1),
                pageSize: (int) ($validated['size'] ?? 12),
            );

            try {
                $result = $provider->search($criteria);
            } catch (ProviderRequestException $e) {
                return response()->json(['error' => 'modpacks.errors.provider_unreachable', 'detail' => $providerEnum->value], 502);
            }

            $hits = array_map(static fn ($hit) => [
                'provider' => $hit->provider->value,
                'modpack_id' => $hit->modpackId,
                'name' => $hit->name,
                'slug' => $hit->slug,
                'description' => $hit->description,
                'icon_url' => $hit->iconUrl,
                'external_url' => $hit->externalUrl,
                'is_server_compatible' => $hit->isServerCompatible,
            ], $result->hits);

            return response()->json([
                'data' => $hits,
                'meta' => [
                    'current_page' => $result->currentPage,
                    'last_page' => $result->lastPage(),
                    'per_page' => $result->perPage,
                    'total' => $result->total,
                ],
            ]);
        },
    );

    Route::get(
        'servers/{serverIdentifier}/modpacks/{provider}/{modpackId}/versions',
        function (string $serverIdentifier, string $provider, string $modpackId, Request $request, ModpackProviderRegistry $registry, EligibilityService $eligibility) use ($resolveServer, $requirePerm): JsonResponse {
            $server = $resolveServer($serverIdentifier, $request);
            $requirePerm($request, $server, 'modpack.read');

            if (! $eligibility->isEligible($server)) {
                abort(403, __('modpacks.errors.server_not_eligible'));
            }

            $providerEnum = ModpackProvider::tryFrom($provider);
            if ($providerEnum === null || ! $registry->has($providerEnum)) {
                return response()->json(['error' => 'modpacks.errors.unknown_provider'], 422);
            }

            $mcFilter = $request->query('mc');
            $mcFilter = is_string($mcFilter) && $mcFilter !== '' ? $mcFilter : null;

            try {
                $versions = $registry->get($providerEnum)->listVersions($modpackId, $mcFilter);
            } catch (ProviderRequestException $e) {
                return response()->json(['error' => 'modpacks.errors.provider_unreachable'], 502);
            }

            $payload = array_map(static fn ($v) => [
                'version_id' => $v->versionId,
                'label' => $v->label,
                'minecraft_versions' => $v->minecraftVersions,
                'loaders' => $v->loaders,
                'release_type' => $v->releaseType,
            ], $versions);

            return response()->json(['data' => $payload]);
        },
    );

    // ---------------------------------------------------------------------
    // Installation lifecycle
    // ---------------------------------------------------------------------

    Route::get(
        'servers/{serverIdentifier}/modpacks/installation',
        function (string $serverIdentifier, Request $request) use ($resolveServer, $requirePerm, $serializeInstallation): JsonResponse {
            $server = $resolveServer($serverIdentifier, $request);
            $requirePerm($request, $server, 'modpack.read');

            $installation = ModpackInstallation::where('server_id', $server->id)->first();

            return response()->json(['data' => $serializeInstallation($installation)]);
        },
    );

    Route::post(
        'servers/{serverIdentifier}/modpacks/installation',
        function (string $serverIdentifier, Request $request, InstallationOrchestrator $orchestrator) use ($resolveServer, $requirePerm, $serializeInstallation): JsonResponse {
            $server = $resolveServer($serverIdentifier, $request);
            $requirePerm($request, $server, 'modpack.install');

            $validated = $request->validate([
                'provider' => ['required', 'string', 'in:modrinth,curseforge,atlauncher,ftb,technic,voidswrath'],
                'modpack_id' => ['required', 'string', 'max:255'],
                'version_id' => ['required', 'string', 'max:255'],
                'purge_files' => ['required', 'boolean'],
            ]);

            try {
                $intent = new ModpackInstallIntent(
                    provider: ModpackProvider::from($validated['provider']),
                    modpackId: $validated['modpack_id'],
                    versionId: $validated['version_id'],
                    purgeFiles: (bool) $validated['purge_files'],
                );
                $installation = $orchestrator->startInstall($server, $request->user(), $intent);
            } catch (ServerNotEligibleException) {
                return response()->json(['error' => 'modpacks.errors.server_not_eligible'], 403);
            } catch (ProviderNotConfiguredException) {
                return response()->json(['error' => 'modpacks.errors.provider_not_configured'], 422);
            } catch (InstallationConflictException) {
                return response()->json(['error' => 'modpacks.errors.installation_in_progress'], 422);
            }

            return response()->json(['data' => $serializeInstallation($installation)], 202);
        },
    );

    Route::delete(
        'servers/{serverIdentifier}/modpacks/installation',
        function (string $serverIdentifier, Request $request, InstallationOrchestrator $orchestrator) use ($resolveServer, $requirePerm, $serializeInstallation): JsonResponse {
            $server = $resolveServer($serverIdentifier, $request);
            $requirePerm($request, $server, 'modpack.uninstall');

            try {
                $installation = $orchestrator->startUninstall($server);
            } catch (InstallationConflictException) {
                return response()->json(['error' => 'modpacks.errors.installation_in_progress'], 422);
            }

            return response()->json(['data' => $serializeInstallation($installation)], 202);
        },
    );
});
