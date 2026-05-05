<?php

declare(strict_types=1);

namespace Plugins\MinecraftModpackInstaller\Models;

use App\Models\Server;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Plugins\MinecraftModpackInstaller\Enums\ModpackInstallationStatus;
use Plugins\MinecraftModpackInstaller\Enums\ModpackProvider;

class ModpackInstallation extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'server_id',
        'provider',
        'modpack_id',
        'modpack_name',
        'modpack_slug',
        'version_id',
        'version_label',
        'icon_url',
        'external_url',
        'status',
        'status_message',
        'purge_files',
        'java_version',
        'pelican_egg_snapshot_id',
        'pelican_image_snapshot',
        'pelican_startup_snapshot',
        'pelican_jarfile_snapshot',
        'pelican_environment_snapshot',
        'started_at',
        'completed_at',
        'failed_at',
        'installed_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'server_id' => 'integer',
            'provider' => ModpackProvider::class,
            'status' => ModpackInstallationStatus::class,
            'purge_files' => 'boolean',
            'java_version' => 'integer',
            'pelican_egg_snapshot_id' => 'integer',
            'pelican_environment_snapshot' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
            'installed_by' => 'integer',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function installedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'installed_by');
    }
}
