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
            // Write environment variables
            $this->setupService->writeEnv([
                'DB_HOST' => $validated['database']['host'],
                'DB_PORT' => (string) $validated['database']['port'],
                'DB_DATABASE' => $validated['database']['name'],
                'DB_USERNAME' => $validated['database']['username'],
                'DB_PASSWORD' => $validated['database']['password'] ?? '',
                'PELICAN_URL' => $validated['pelican']['url'],
                'PELICAN_ADMIN_API_KEY' => $validated['pelican']['api_key'],
                'AUTH_MODE' => $validated['auth']['mode'],
                'OAUTH_CLIENT_ID' => $validated['auth']['oauth_client_id'] ?? '',
                'OAUTH_CLIENT_SECRET' => $validated['auth']['oauth_client_secret'] ?? '',
                'OAUTH_AUTHORIZE_URL' => $validated['auth']['oauth_authorize_url'] ?? '',
                'OAUTH_TOKEN_URL' => $validated['auth']['oauth_token_url'] ?? '',
                'OAUTH_USER_URL' => $validated['auth']['oauth_user_url'] ?? '',
                'BRIDGE_ENABLED' => $validated['bridge']['enabled'] ? 'true' : 'false',
                'STRIPE_WEBHOOK_SECRET' => $validated['bridge']['stripe_webhook_secret'] ?? '',
            ]);

            // Run migrations
            $this->setupService->runMigrations();

            // Create admin user
            $this->setupService->createAdminUser(
                name: $validated['admin']['name'],
                email: $validated['admin']['email'],
                password: $validated['admin']['password'],
            );

            // Mark as installed
            $this->setupService->markAsInstalled();

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
        return response()->json([
            'is_docker' => env('DOCKER', false),
            'defaults' => [
                'host' => 'mysql',
                'port' => 3306,
                'database' => 'biomebounty_panel',
                'username' => 'biomebounty',
            ],
        ]);
    }
}
