<?php

declare(strict_types=1);

namespace Plugins\MinecraftModpackInstaller\Listeners;

use App\Events\ServerReinstallStarting;
use Plugins\MinecraftModpackInstaller\Events\UninstallationCompleted;
use Plugins\MinecraftModpackInstaller\Models\ModpackInstallation;
use Plugins\MinecraftModpackInstaller\Pelican\PelicanClient;
use Plugins\MinecraftModpackInstaller\Services\JavaCompatibilityMatrix;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Listens to the core `App\Events\ServerReinstallStarting` event so the
 * modpack-installer plugin can drop its `modpack_installations` row
 * whenever the operator reinstalls a server through the panel's homepage.
 *
 * Without this hook the modpack tab would keep showing the modpack as
 * "installed" even after the server has been wiped/reinstalled — Pelican
 * is happy, the egg is back to whatever it was before, but the plugin's
 * local installation tracking row never got told.
 *
 * The listener is registered through the plugin's service provider; the
 * core does not import any plugin class. If the plugin is not installed
 * the listener simply does not exist, and the event flows past with no
 * effect.
 */
class ClearModpackOnReinstall
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly PelicanClient $pelican,
        private readonly JavaCompatibilityMatrix $javaMatrix,
    ) {}

    public function handle(ServerReinstallStarting $event): void
    {
        $installation = ModpackInstallation::where('server_id', $event->server->id)->first();

        // Even when no modpack row exists, defensively clear the
        // `skip_scripts` flag on the Pelican side. Past versions of the
        // modpack swap-back code persisted `skip_scripts=true` on the
        // server row, which causes Pelican to silently bypass the egg
        // install script on every subsequent native /reinstall — exactly
        // the failure the user reported in the 18:09:34 logs. This reset
        // costs one PATCH and is idempotent.
        $this->resetSkipScripts($event->server);

        if ($installation === null) {
            return;
        }

        $this->logger->info('modpack: clearing installation row on server reinstall', [
            'server_id' => $event->server->id,
            'modpack_installation_id' => $installation->id,
            'wipe_data' => $event->wipeData,
        ]);

        try {
            // Fire the uninstall event so other listeners (audit logs,
            // permission registries, etc.) see the row going away. We pass
            // the server explicitly because $installation->server might
            // not eager-load by the time the listener fires.
            event(new UninstallationCompleted($event->server));
        } catch (Throwable $e) {
            $this->logger->info('modpack: UninstallationCompleted dispatch failed (non-fatal)', [
                'server_id' => $event->server->id,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $installation->delete();
        } catch (Throwable $e) {
            $this->logger->warning('modpack: failed to delete installation row on reinstall', [
                'server_id' => $event->server->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Force `skip_scripts=false` on the Pelican server row so the upcoming
     * /settings/reinstall actually runs the egg's install script. Read the
     * current container config first so the PATCH only touches the flag —
     * Pelican's StartupModificationService merges per-key, so passing an
     * empty `environment` would not destroy existing variables, but we
     * still want to mirror the live state for safety.
     */
    private function resetSkipScripts(\App\Models\Server $server): void
    {
        if ($server->pelican_server_id === null) {
            return;
        }

        try {
            $raw = $this->pelican->getServerRaw((int) $server->pelican_server_id);
            $attrs = $raw['attributes'] ?? [];
            $container = $attrs['container'] ?? [];

            $this->pelican->updateServerStartup((int) $server->pelican_server_id, [
                'egg' => $attrs['egg'] ?? null,
                // Live container image is what we want to keep (we're only
                // toggling skip_scripts). Fall through to the matrix's
                // default-Java image only when Pelican returned no image —
                // never hardcoded.
                'image' => (string) ($container['image']
                    ?? $this->javaMatrix->imageForJava($this->javaMatrix->defaultJava())),
                'startup' => (string) ($container['startup_command'] ?? 'java -jar {{SERVER_JARFILE}}'),
                'environment' => is_array($container['environment'] ?? null)
                    ? $container['environment']
                    : ['SERVER_JARFILE' => 'server.jar'],
                'skip_scripts' => false,
            ]);
        } catch (Throwable $e) {
            $this->logger->warning('modpack: skip_scripts reset failed (continuing)', [
                'server_id' => $server->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
