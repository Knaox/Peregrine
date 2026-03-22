<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Http\Requests\Setup\InstallRequest;
use App\Http\Requests\Setup\TestDatabaseRequest;
use App\Http\Requests\Setup\TestPelicanRequest;
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

            // 2. Run migrations + seed using the runtime config
            $this->setupService->runMigrations();

            // 3. Create admin user
            $this->setupService->createAdminUser(
                name: $validated['admin']['name'],
                email: $validated['admin']['email'],
                password: $validated['admin']['password'],
            );

            // 4. Send the response BEFORE writing .env (because artisan serve will restart)
            // We use register_shutdown_function to write the .env after the response is sent
            register_shutdown_function(function () use ($validated, $dbHost, $dbPort, $dbName, $dbUser, $dbPass) {
                $this->setupService->writeEnv([
                    'PANEL_INSTALLED' => 'true',
                    'DB_CONNECTION' => 'mysql',
                    'DB_HOST' => $dbHost,
                    'DB_PORT' => (string) $dbPort,
                    'DB_DATABASE' => $dbName,
                    'DB_USERNAME' => $dbUser,
                    'DB_PASSWORD' => $dbPass,
                    'DB_SOCKET' => '',
                    'PELICAN_URL' => $validated['pelican']['url'],
                    'PELICAN_ADMIN_API_KEY' => $validated['pelican']['api_key'],
                    'PELICAN_CLIENT_API_KEY' => $validated['pelican']['client_api_key'],
                    'AUTH_MODE' => $validated['auth']['mode'],
                    'OAUTH_CLIENT_ID' => $validated['auth']['oauth_client_id'] ?? '',
                    'OAUTH_CLIENT_SECRET' => $validated['auth']['oauth_client_secret'] ?? '',
                    'OAUTH_AUTHORIZE_URL' => $validated['auth']['oauth_authorize_url'] ?? '',
                    'OAUTH_TOKEN_URL' => $validated['auth']['oauth_token_url'] ?? '',
                    'OAUTH_USER_URL' => $validated['auth']['oauth_user_url'] ?? '',
                    'BRIDGE_ENABLED' => $validated['bridge']['enabled'] ? 'true' : 'false',
                    'STRIPE_WEBHOOK_SECRET' => $validated['bridge']['stripe_webhook_secret'] ?? '',
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

    public function dockerDetect(): JsonResponse
    {
        $isDocker = filter_var(env('DOCKER', false), FILTER_VALIDATE_BOOLEAN);

        $defaults = $isDocker ? [
            'host' => env('DB_HOST', 'mysql'),
            'port' => (int) env('DB_PORT', 3306),
            'database' => env('DB_DATABASE', 'peregrine'),
            'username' => env('DB_USERNAME', 'peregrine'),
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
                    password: env('DB_PASSWORD', ''),
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
