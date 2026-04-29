<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Http\Requests\Setup\InstallRequest;
use App\Http\Requests\Setup\TestDatabaseRequest;
use App\Http\Requests\Setup\TestPelicanRequest;
use App\Services\SettingsService;
use App\Services\SetupService;
use Illuminate\Http\JsonResponse;

class SetupController extends Controller
{
    public function __construct(
        private SetupService $setupService,
    ) {}

    public function testDatabase(TestDatabaseRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $success = $this->setupService->testDatabaseConnection(
                host: $validated['host'],
                port: (int) $validated['port'],
                database: $validated['database'],
                username: $validated['username'],
                password: $validated['password'] ?? '',
            );

            return response()->json(['success' => $success]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function testPelican(TestPelicanRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $success = $this->setupService->testPelicanConnection(
                url: $validated['url'],
                apiKey: $validated['api_key'],
            );

            return response()->json(['success' => $success]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function install(InstallRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $dbHost = $validated['database']['host'];
            $dbPort = (int) $validated['database']['port'];
            $dbName = $validated['database']['name'];
            $dbUser = $validated['database']['username'];
            $dbPass = $validated['database']['password'] ?? '';

            // 1. Reconfigure DB connection at runtime (no .env write yet — artisan serve restarts on .env change)
            $this->setupService->reconfigureDatabase($dbHost, $dbPort, $dbName, $dbUser, $dbPass);

            // 2. Run migrations + seed using the runtime config. If the admin
            // ticked "Fresh install" because the selected DB already holds
            // leftovers from a previous install, drop every table first.
            $this->setupService->runMigrations(
                fresh: (bool) ($validated['database']['fresh'] ?? false),
            );

            // 3. Create admin user (with the language picked at step 1).
            $wizardLocale = in_array($validated['locale'] ?? 'en', ['en', 'fr'], true)
                ? (string) $validated['locale']
                : 'en';
            $this->setupService->createAdminUser(
                name: $validated['admin']['name'],
                email: $validated['admin']['email'],
                password: $validated['admin']['password'],
                locale: $wizardLocale,
            );

            // Persist the language choice as the panel-wide default. New users
            // (Bridge / Paymenter / OAuth flows) get this locale; the React SPA
            // also reads it as fallback when no browser preference matches.
            app(SettingsService::class)->set('default_locale', $wizardLocale);

            // 3.5 Activate the default "invitations" plugin (best effort — a
            // failure here must not break setup). It ships with Peregrine
            // and runs its own migrations on activate().
            //
            // Other bundled plugins (e.g. egg-config-editor) are preinstalled
            // on disk but NOT auto-activated — the admin opts in from the
            // Plugins page when they want them.
            try {
                if (app(\App\Services\PluginManager::class)->getManifest('invitations')) {
                    app(\App\Services\PluginManager::class)->activate('invitations');
                }
            } catch (\Throwable) {
                // ignore — admin can activate it manually from the Plugins page.
            }

            // 3.6 Override the default `auth_local_registration_enabled` if
            // the admin toggled it off in the wizard. AuthSettingsSeeder ran
            // with the 'true' default during step 2 — flip it only when
            // explicitly disabled, no-op otherwise.
            if (($validated['auth']['allow_local_registration'] ?? true) === false) {
                app(SettingsService::class)->set('auth_local_registration_enabled', 'false');
            }

            // 4. Drop an "installed" sentinel file NOW (before the response is
            // sent). phpdotenv runs in immutable mode, so writing PANEL_INSTALLED=true
            // to .env mid-process doesn't take effect until php-fpm workers cycle.
            // EnsureInstalled middleware checks this sentinel alongside the env
            // value so the redirect loop stops on the very next page load.
            @touch(storage_path('.installed'));

            // Drop a "wizard finishing" sentinel so EnsureInstalled keeps
            // /setup reachable while the admin completes step 7 (Backfill)
            // and step 8 (Webhook). Without this, a browser refresh or any
            // asset reload between Summary success and Finish would kick
            // the admin to / and leave them stuck. The sentinel is removed
            // by the Finish button (POST /api/setup/finalize) and auto-
            // expires after 1h to recover from abandoned wizards.
            @touch(storage_path('.wizard_finishing'));

            // 5. Pelican URL + API keys → `settings` table (encrypted secrets).
            //    Done synchronously NOW (before the response) — these writes go
            //    to the DB so they don't share .env's permission limits, and
            //    PelicanCredentials reads from settings on every call so the
            //    BackfillStep that runs right after install picks them up
            //    without a queue:restart dance.
            $settings = app(SettingsService::class);
            $settings->set('pelican_url', $validated['pelican']['url']);
            $settings->set('pelican_admin_api_key', \Illuminate\Support\Facades\Crypt::encryptString($validated['pelican']['api_key']));
            $settings->set('pelican_client_api_key', \Illuminate\Support\Facades\Crypt::encryptString($validated['pelican']['client_api_key']));

            // 6. Send the response BEFORE writing .env (because artisan serve will restart)
            // We use register_shutdown_function to write the .env after the response is sent
            register_shutdown_function(function () use ($dbHost, $dbPort, $dbName, $dbUser, $dbPass) {
                $this->setupService->writeEnv([
                    'PANEL_INSTALLED' => 'true',
                    'DB_CONNECTION' => 'mysql',
                    'DB_HOST' => $dbHost,
                    'DB_PORT' => (string) $dbPort,
                    'DB_DATABASE' => $dbName,
                    'DB_USERNAME' => $dbUser,
                    'DB_PASSWORD' => $dbPass,
                    'DB_SOCKET' => '',
                    // Pelican URL + API keys live in the `settings` table now,
                    // not .env — see step 5 above.
                    // Auth config in `settings` (managed via /admin/auth-settings).
                    // Bridge config in `settings` (managed at /admin/bridge-settings).
                ]);
            });

            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mark the wizard as finished — removes the `.wizard_finishing`
     * sentinel so the next visit to /setup gets redirected to / (no
     * accidental re-entry). Called by WebhookStep's Finish button.
     *
     * Idempotent : missing sentinel returns success too. No auth — the
     * sentinel itself is the only authority over wizard state.
     */
    public function finalize(): JsonResponse
    {
        $sentinel = storage_path('.wizard_finishing');
        if (file_exists($sentinel)) {
            @unlink($sentinel);
        }
        return response()->json(['success' => true]);
    }

    /**
     * Tell the SPA which step it should resume on. Used on mount to
     * recover from page reloads that wipe React state — typically
     * triggered by `php artisan serve` restarting after .env writes.
     *
     * - `installed=false` : fresh install, SPA starts at step 0.
     * - `installed=true && finishing=true` : install already ran but
     *    Finish wasn't clicked yet → SPA resumes at the Backfill step
     *    (index 6 in STEP_COMPONENTS, the 7th step).
     * - `installed=true && finishing=false` : wizard fully done — SPA
     *    redirects to / (no point showing it).
     */
    public function state(): JsonResponse
    {
        $installed = config('panel.installed') || file_exists(storage_path('.installed'));
        $finishing = file_exists(storage_path('.wizard_finishing'));

        return response()->json([
            'installed' => $installed,
            'finishing' => $finishing,
        ]);
    }

    public function dockerDetect(): JsonResponse
    {
        $isDocker = filter_var(env('DOCKER', false), FILTER_VALIDATE_BOOLEAN);

        $defaults = $isDocker ? [
            'host' => env('DB_HOST', 'mysql'),
            'port' => (int) env('DB_PORT', 3306),
            'database' => env('DB_DATABASE', 'peregrine'),
            'username' => env('DB_USERNAME', 'peregrine'),
            // Return the password from the container env so the wizard can
            // pre-fill it. Only ever exposed on the /setup flow, which the
            // EnsureInstalled middleware disables once PANEL_INSTALLED=true.
            'password' => (string) env('DB_PASSWORD', ''),
        ] : [];

        // Test if the current DB config already works
        $dbReady = false;
        if ($isDocker) {
            try {
                $dbReady = $this->setupService->testDatabaseConnection(
                    host: $defaults['host'],
                    port: $defaults['port'],
                    database: $defaults['database'],
                    username: $defaults['username'],
                    password: $defaults['password'],
                );
            } catch (\Throwable) {
                $dbReady = false;
            }
        }

        return response()->json([
            'is_docker' => $isDocker,
            'db_ready' => $dbReady,
            'defaults' => $defaults,
        ]);
    }
}
