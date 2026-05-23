<?php

declare(strict_types=1);

namespace App\Services\Plugin;

use App\Models\Server;
use Closure;
use Throwable;

/**
 * Process-wide registry of "startup variable claimers" — closures a plugin
 * registers to declare that it OWNS certain egg env variables for a server. The
 * core startup-variables page flags claimed variables (e.g. badges them as
 * "linked") so the relationship is visible without a duplicate editing surface.
 *
 * Same static-singleton rationale as {@see ManifestEnricherRegistry}: plugins
 * boot dynamically (their ServiceProvider only registers when DB-active), so a
 * static registry the core consults at request time keeps core → plugin fully
 * decoupled. Core owns the abstraction; plugins push into it, guarded by
 * `class_exists`.
 *
 * Usage (from a plugin's ServiceProvider::boot()):
 *
 *   StartupVariableClaimRegistry::getInstance()->register('my-plugin',
 *       fn (Server $s): array => MyModel::envVarsFor($s));
 */
final class StartupVariableClaimRegistry
{
    private static ?self $instance = null;

    /** @var array<string, Closure> plugin_id => fn(Server): list<string> (env_variable names) */
    private array $claimers = [];

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    public function register(string $pluginId, Closure $claimer): void
    {
        $this->claimers[$pluginId] = $claimer;
    }

    /**
     * Env variable names claimed (and therefore flagged as "linked" on the core
     * startup page) for this server. A throwing claimer is reported and skipped
     * — a buggy plugin must never break the startup endpoint.
     *
     * @return list<string>
     */
    public function claimedFor(Server $server): array
    {
        $claimed = [];
        foreach ($this->claimers as $claimer) {
            try {
                foreach ((array) $claimer($server) as $name) {
                    if (is_string($name) && $name !== '') {
                        $claimed[$name] = true;
                    }
                }
            } catch (Throwable $e) {
                report($e);
            }
        }

        return array_keys($claimed);
    }

    /** Test-only: clear registered claimers to avoid cross-test leakage. */
    public function reset(): void
    {
        $this->claimers = [];
    }
}
