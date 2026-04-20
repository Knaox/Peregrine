<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use PDO;
use PDOException;

class SetupService
{
    /**
     * Write key-value pairs to the .env file atomically.
     *
     * Existing keys are updated in-place; new keys are appended.
     *
     * @param array<string, string> $values
     */
    public function writeEnv(array $values): void
    {
        $envPath = base_path('.env');

        // Follow symlinks so we write to the actual file on disk. The Docker
        // image symlinks /var/www/html/.env to storage/.env (persistent volume);
        // if we rename a temp file onto the symlink path, we replace the link
        // itself instead of updating the target.
        $realPath = is_link($envPath) ? (string) readlink($envPath) : $envPath;
        if (! str_starts_with($realPath, '/')) {
            $realPath = dirname($envPath) . '/' . $realPath;
        }

        $contents = file_exists($realPath) ? (string) file_get_contents($realPath) : '';

        foreach ($values as $key => $value) {
            $escapedValue = $this->escapeEnvValue($value);

            // Match existing key (with or without quotes)
            $pattern = '/^' . preg_quote($key, '/') . '=.*/m';

            if (preg_match($pattern, $contents)) {
                $contents = preg_replace($pattern, "{$key}={$escapedValue}", $contents);
            } else {
                $contents = rtrim($contents, "\n") . "\n{$key}={$escapedValue}\n";
            }
        }

        // Write atomically next to the real path, then rename there so we
        // never replace a symlink with a regular file.
        $tmpPath = $realPath . '.tmp';
        if (@file_put_contents($tmpPath, $contents, LOCK_EX) === false) {
            // Fallback: direct write (less atomic but fine for a tiny .env).
            file_put_contents($realPath, $contents, LOCK_EX);
            return;
        }

        rename($tmpPath, $realPath);
    }

    /**
     * Test a database connection with the given credentials.
     */
    public function testDatabaseConnection(
        string $host,
        int $port,
        string $database,
        string $username,
        string $password,
    ): bool {
        $dsn = "mysql:host={$host};port={$port};dbname={$database}";
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_TIMEOUT => 5,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $pdo->query('SELECT 1');

        return true;
    }

    /**
     * Test the connection to a Pelican panel by hitting its API.
     */
    public function testPelicanConnection(string $url, string $apiKey): bool
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])
                ->timeout(10)
                ->get(rtrim($url, '/') . '/api/application/users?per_page=1');

            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Reconfigure the database connection at runtime with the given credentials.
     * This is needed because writeEnv changes the .env but Laravel still has the old config cached.
     */
    public function reconfigureDatabase(
        string $host,
        int $port,
        string $database,
        string $username,
        string $password,
    ): void {
        config([
            'database.connections.mysql.host' => $host,
            'database.connections.mysql.port' => $port,
            'database.connections.mysql.database' => $database,
            'database.connections.mysql.username' => $username,
            'database.connections.mysql.password' => $password,
            'database.connections.mysql.unix_socket' => '',
            'database.default' => 'mysql',
        ]);

        DB::purge('mysql');
        DB::reconnect('mysql');
    }

    /**
     * Run all pending database migrations, then invoke only the production-safe
     * seeders explicitly.
     *
     * We can't use `--seed` because DatabaseSeeder falls back on
     * User::factory() — factories rely on the global `fake()` helper which
     * ships with fakerphp/faker (a require-dev dep, intentionally stripped
     * from the production composer install). Calling SettingsSeeder directly
     * keeps us within the production-safe surface.
     *
     * @param  bool  $fresh  When true, drops every table first. Use when the
     *                       selected database contains leftovers from a
     *                       previous Peregrine install and you accept the
     *                       data loss.
     */
    public function runMigrations(bool $fresh = false): void
    {
        if ($fresh) {
            Artisan::call('migrate:fresh', ['--force' => true]);
        } else {
            Artisan::call('migrate', ['--force' => true]);
        }

        Artisan::call('db:seed', [
            '--class' => \Database\Seeders\SettingsSeeder::class,
            '--force' => true,
        ]);
    }

    /**
     * Create an admin user in the local database.
     */
    public function createAdminUser(string $name, string $email, string $password): void
    {
        User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'is_admin' => true,
            'email_verified_at' => now(),
        ]);
    }

    /**
     * Mark the application as installed by setting PANEL_INSTALLED=true in .env.
     */
    public function markAsInstalled(): void
    {
        $this->writeEnv(['PANEL_INSTALLED' => 'true']);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Escape a value for safe inclusion in the .env file.
     *
     * Wraps the value in double-quotes if it contains spaces, quotes, or special characters.
     */
    private function escapeEnvValue(string $value): string
    {
        if ($value === '') {
            return '""';
        }

        // If the value contains spaces, #, quotes, or starts/ends with whitespace, wrap it.
        if (preg_match('/[\s#"\'\\\\]/', $value) || $value !== trim($value)) {
            $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $value);

            return '"' . $escaped . '"';
        }

        return $value;
    }
}
