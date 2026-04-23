<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServerPlan extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        // Mirror Shop business
        'shop_plan_id',
        'shop_plan_slug',
        'shop_plan_type',
        'name',
        'description',
        'is_active',
        'price_cents',
        'currency',
        'interval',
        'interval_count',
        'has_trial',
        'trial_interval',
        'trial_interval_count',
        'stripe_price_id',

        // Mirror Shop Pelican specs
        'ram',
        'cpu',
        'disk',
        'swap_mb',
        'io_weight',
        'cpu_pinning',

        // Peregrine technical config
        'egg_id',
        'nest_id',
        'node_id',
        'default_node_id',
        'allowed_node_ids',
        'auto_deploy',
        'docker_image',
        'port_count',
        'env_var_mapping',
        'enable_oom_killer',
        'start_on_completion',
        'skip_install_script',
        'dedicated_ip',
        'feature_limits_databases',
        'feature_limits_backups',
        'feature_limits_allocations',

        // Stripe Checkout config (Shop-owned)
        'checkout_custom_fields',

        // Sync tracking
        'last_shop_synced_at',
    ];

    /**
     * Champs Shop-owned, jamais modifiés par l'admin Peregrine.
     * Utilisés par PlanSyncController::upsert() pour ne réécrire que les
     * champs business à chaque push, en préservant la config technique
     * Peregrine déjà saisie par l'admin.
     *
     * @var list<string>
     */
    public const SHOP_OWNED_FIELDS = [
        'shop_plan_id',
        'shop_plan_slug',
        'shop_plan_type',
        'name',
        'description',
        'is_active',
        'price_cents',
        'currency',
        'interval',
        'interval_count',
        'has_trial',
        'trial_interval',
        'trial_interval_count',
        'stripe_price_id',
        'ram',
        'cpu',
        'disk',
        'swap_mb',
        'io_weight',
        'cpu_pinning',
        'checkout_custom_fields',
    ];

    protected function casts(): array
    {
        return [
            'shop_plan_id' => 'integer',
            'price_cents' => 'integer',
            'interval_count' => 'integer',
            'has_trial' => 'boolean',
            'trial_interval_count' => 'integer',
            'egg_id' => 'integer',
            'nest_id' => 'integer',
            'ram' => 'integer',
            'cpu' => 'integer',
            'disk' => 'integer',
            'swap_mb' => 'integer',
            'io_weight' => 'integer',
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
            'checkout_custom_fields' => 'array',
            'is_active' => 'boolean',
            'last_shop_synced_at' => 'datetime',
        ];
    }

    /**
     * Un plan est "ready to provision" quand :
     *  - egg_id est défini ET
     *  - soit node_id défini (déploiement sur un node spécifique),
     *  - soit auto_deploy actif AVEC au moins un allowed_node_ids.
     */
    public function isReadyToProvision(): bool
    {
        if ($this->egg_id === null) {
            return false;
        }

        if ($this->node_id !== null) {
            return true;
        }

        if ($this->auto_deploy && is_array($this->allowed_node_ids) && count($this->allowed_node_ids) > 0) {
            return true;
        }

        return false;
    }

    /**
     * Vrai si le plan a été créé par un push depuis le Shop (vs créé
     * manuellement en standalone — futur cas d'usage).
     */
    public function isFromShop(): bool
    {
        return $this->shop_plan_id !== null;
    }

    /**
     * Statut de sync agrégé pour l'indicateur visuel Filament.
     *  - 'inactive'    : is_active = false (désactivé côté Shop)
     *  - 'sync_error'  : dernière entrée bridge_sync_logs failed
     *  - 'ready'       : isReadyToProvision()
     *  - 'needs_config': egg/node manquants, à configurer côté Peregrine
     */
    public function syncStatus(): string
    {
        if (! $this->is_active) {
            return 'inactive';
        }

        $lastLog = BridgeSyncLog::query()
            ->where('server_plan_id', $this->id)
            ->orderByDesc('attempted_at')
            ->first();

        if ($lastLog !== null && $lastLog->response_status >= 400) {
            return 'sync_error';
        }

        return $this->isReadyToProvision() ? 'ready' : 'needs_config';
    }

    /**
     * Pelican removed the standalone /api/application/nests endpoint — nests
     * are now only reachable through eggs. We mirror that semantics here :
     * the admin only picks an egg (in Filament or via the Bridge), and the
     * nest_id is auto-derived from that egg whenever the plan is saved.
     *
     * Defense in depth : works for ANY save path (Filament edit, artisan
     * tinker, Bridge upsert if Shop ever pushes egg_id, factory, seeder).
     */
    protected static function booted(): void
    {
        static::saving(function (self $plan): void {
            if ($plan->egg_id !== null && $plan->isDirty('egg_id')) {
                $plan->nest_id = Egg::query()->whereKey($plan->egg_id)->value('nest_id');
            }
            if ($plan->egg_id === null) {
                $plan->nest_id = null;
            }
        });
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
        return $this->hasMany(Server::class, 'plan_id');
    }
}
