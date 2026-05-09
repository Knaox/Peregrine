<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Server provisioning configuration — the technical blueprint that the
 * Pelican panel needs in order to spin up a game server.
 *
 * Strictly TECHNICAL. Commercial concepts (price, currency, billing cycle,
 * trial, marketing description) live in the external shop and Stripe ; this
 * model knows none of them. A configuration becomes purchasable when it is
 * attached to a Shop via the `shop_server_configuration` pivot (Phase 2).
 *
 * Identification :
 *  - `internal_name` : stable admin slug, used in name_template placeholders
 *    and in outbound webhook payloads.
 *  - `name_template` : Twig-like template applied at provision time to derive
 *    the final `Server.name`. Default placeholders : `{user.username}` and
 *    `{configuration.internal_name}`.
 *
 * Pelican specs (`ram`, `cpu`, `disk`, `swap_mb`, `io_weight`, `cpu_pinning`)
 * are technical : they are pushed to Pelican `createServer`/`updateServerBuild`
 * verbatim. They reflect what the panel allocates, not what the shop
 * advertises.
 */
class ServerConfiguration extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        // Identity (technical-only)
        'internal_name',
        'technical_description',
        'name_template',

        // Pelican resource limits
        'ram',
        'cpu',
        'disk',
        'swap_mb',
        'io_weight',
        'cpu_pinning',

        // Pelican egg / nest / node selection
        'egg_id',
        'nest_id',
        'node_id',
        'default_node_id',
        'allowed_node_ids',
        'auto_deploy',

        // Pelican runtime
        'docker_image',
        'port_count',
        'env_var_mapping',
        'enable_oom_killer',
        'start_on_completion',
        'skip_install_script',
        'dedicated_ip',

        // Pelican feature limits
        'feature_limits_databases',
        'feature_limits_backups',
        'feature_limits_allocations',
    ];

    protected function casts(): array
    {
        return [
            'ram' => 'integer',
            'cpu' => 'integer',
            'disk' => 'integer',
            'swap_mb' => 'integer',
            'io_weight' => 'integer',
            'egg_id' => 'integer',
            'nest_id' => 'integer',
            'node_id' => 'integer',
            'default_node_id' => 'integer',
            'allowed_node_ids' => 'array',
            'auto_deploy' => 'boolean',
            'port_count' => 'integer',
            'env_var_mapping' => 'array',
            'enable_oom_killer' => 'boolean',
            'start_on_completion' => 'boolean',
            'skip_install_script' => 'boolean',
            'dedicated_ip' => 'boolean',
            'feature_limits_databases' => 'integer',
            'feature_limits_backups' => 'integer',
            'feature_limits_allocations' => 'integer',
        ];
    }

    /**
     * Number of consecutive free ports the allocator must reserve at
     * provisioning time, derived from BOTH the admin-set `port_count` AND
     * the highest `offset_value` referenced in `env_var_mapping`.
     *
     * Without this, an admin who sets `port_count=1` and adds a mapping
     * with `offset=2` ends up with the offset variable receiving null at
     * provisioning time (the 3rd port is never allocated). Computing the
     * effective count from both sources removes that footgun.
     *
     * Always >= 1.
     */
    public function effectivePortCount(): int
    {
        $declared = max(1, (int) ($this->port_count ?? 1));

        $mapping = $this->env_var_mapping ?? [];
        if (! is_array($mapping)) {
            return $declared;
        }

        $maxOffset = 0;
        foreach ($mapping as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            if (($entry['type'] ?? null) !== 'offset') {
                continue;
            }
            $maxOffset = max($maxOffset, (int) ($entry['offset_value'] ?? 0));
        }

        return max($declared, $maxOffset + 1);
    }

    /**
     * A configuration is "ready to provision" when :
     *  - egg_id is set, AND
     *  - either node_id is set (legacy : fixed-node deployment), OR
     *  - default_node_id is set (auto_deploy off + admin-picked default), OR
     *  - auto_deploy is on AND allowed_node_ids has at least one entry.
     *
     * Must stay aligned with `ProvisionServerJob::pickNode()` — same
     * resolution order. Drift would mark a configuration as "needs_config"
     * in Filament even though the job would have picked a node.
     */
    public function isReadyToProvision(): bool
    {
        if ($this->egg_id === null) {
            return false;
        }

        if ($this->node_id !== null) {
            return true;
        }

        if ($this->default_node_id !== null) {
            return true;
        }

        if ($this->auto_deploy && is_array($this->allowed_node_ids) && count($this->allowed_node_ids) > 0) {
            return true;
        }

        return false;
    }

    /**
     * Aggregate sync status for the Filament admin badge.
     *  - 'sync_error'  : the most recent BridgeSyncLog entry has response_status >= 400.
     *  - 'ready'       : isReadyToProvision().
     *  - 'needs_config': egg/node missing — admin action required.
     */
    public function syncStatus(): string
    {
        $lastLog = BridgeSyncLog::query()
            ->where('server_configuration_id', $this->id)
            ->orderByDesc('attempted_at')
            ->first();

        if ($lastLog !== null && $lastLog->response_status >= 400) {
            return 'sync_error';
        }

        return $this->isReadyToProvision() ? 'ready' : 'needs_config';
    }

    /**
     * Pelican removed the standalone /api/application/nests endpoint — nests
     * are reachable through eggs only. We mirror that semantics here :
     * admins pick an egg (Filament or via the catalog import flow), and the
     * `nest_id` is auto-derived whenever the configuration is saved.
     *
     * Defense in depth : works for ANY save path (Filament edit, artisan
     * tinker, factory, seeder, future API import).
     */
    protected static function booted(): void
    {
        static::saving(function (self $configuration): void {
            if ($configuration->egg_id !== null && $configuration->isDirty('egg_id')) {
                $configuration->nest_id = Egg::query()
                    ->whereKey($configuration->egg_id)
                    ->value('nest_id');
            }
            if ($configuration->egg_id === null) {
                $configuration->nest_id = null;
            }
        });

        // Outbound catalog webhooks (Phase 3). The observer fans out to
        // authorised shops + their subscribed endpoints.
        static::observe(\App\Observers\ServerConfigurationObserver::class);
    }

    public function egg(): BelongsTo
    {
        return $this->belongsTo(Egg::class);
    }

    public function nest(): BelongsTo
    {
        return $this->belongsTo(Nest::class);
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }

    public function defaultNode(): BelongsTo
    {
        return $this->belongsTo(Node::class, 'default_node_id');
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(BridgeSyncLog::class);
    }

    public function servers(): HasMany
    {
        return $this->hasMany(Server::class, 'server_configuration_id');
    }

    /**
     * Shops authorised to resell this configuration via the
     * `shop_server_configuration` pivot. A configuration with no shops
     * attached is admin-only (an orphan template) — invisible from the
     * public API surface.
     */
    public function shops(): BelongsToMany
    {
        return $this->belongsToMany(
            Shop::class,
            'shop_server_configuration',
        )
            ->withPivot(['shop_external_id', 'is_visible', 'sort_order'])
            ->withTimestamps();
    }
}
