<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class BackupOAuthLegacy extends Command
{
    protected $signature = 'auth:backup-oauth-legacy';

    protected $description = 'Dump legacy users.oauth_provider/oauth_id rows to storage/backups/ before the oauth_identities migration. Idempotent — timestamped filenames.';

    public function handle(): int
    {
        if (! $this->legacyColumnsPresent()) {
            $this->warn('Legacy columns users.oauth_provider / users.oauth_id are absent — nothing to back up.');

            return self::SUCCESS;
        }

        $rows = DB::table('users')
            ->whereNotNull('oauth_provider')
            ->select('id', 'email', 'oauth_provider', 'oauth_id')
            ->orderBy('id')
            ->get();

        if ($rows->isEmpty()) {
            $this->info('No rows have oauth_provider set — no backup needed.');

            return self::SUCCESS;
        }

        $dir = storage_path('backups');
        File::ensureDirectoryExists($dir);

        $timestamp = now()->format('Ymd_His');
        $path = "{$dir}/oauth_legacy_pre_migration_{$timestamp}.sql";

        $sql = "-- Peregrine oauth legacy backup\n";
        $sql .= "-- Generated: ".now()->toIso8601String()."\n";
        $sql .= "-- Rows: ".$rows->count()."\n\n";

        foreach ($rows as $row) {
            $sql .= sprintf(
                "-- user_id=%d email=%s\nINSERT INTO oauth_identities_backup_preview (user_id, provider, provider_user_id, provider_email) VALUES (%d, %s, %s, %s);\n",
                $row->id,
                $row->email,
                $row->id,
                $this->quote($row->oauth_provider),
                $this->quote($row->oauth_id),
                $this->quote($row->email),
            );
        }

        File::put($path, $sql);

        $this->info("Backed up {$rows->count()} row(s) to: {$path}");

        return self::SUCCESS;
    }

    private function legacyColumnsPresent(): bool
    {
        $schema = DB::getSchemaBuilder();

        return $schema->hasColumn('users', 'oauth_provider')
            && $schema->hasColumn('users', 'oauth_id');
    }

    private function quote(?string $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        return "'".str_replace("'", "''", $value)."'";
    }
}
