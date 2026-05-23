<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Services\Boost;

use App\Models\Server;
use App\Services\Pelican\PelicanFileService;
use Illuminate\Support\Facades\Log;
use Plugins\EasyConfiguration\Models\BoostSchedule;
use Plugins\EasyConfiguration\Services\Parsing\ParserRegistry;
use Plugins\EasyConfiguration\Services\Pelican\PowerHelper;
use Plugins\EasyConfiguration\Services\Templates\TemplateDefinition;
use Plugins\EasyConfiguration\Services\Templates\TemplateRegistry;
use Plugins\EasyConfiguration\Support\ConfigChange;
use Throwable;

/**
 * Applies and ends boosts against the real files, wrapped in the stop/start
 * envelope. Apply snapshots each parameter's baseline, writes the capped
 * boosted value, and flips the boost active. End (or cancel) restores the
 * baselines. Both are resilient: an apply that can't confirm the server stopped
 * fails the boost; an end writes the originals even on a stop timeout (priority
 * to restoration), logging a warning.
 */
final class BoostApplier
{
    public function __construct(
        private readonly TemplateRegistry $registry,
        private readonly ParserRegistry $parsers,
        private readonly PelicanFileService $files,
        private readonly PowerHelper $power,
        private readonly BoostCalculator $calculator,
        private readonly BoostService $boosts,
    ) {}

    public function apply(BoostSchedule $boost): bool
    {
        $server = Server::find($boost->server_id);
        if ($server === null) {
            $this->fail($boost, 'Server no longer exists');

            return false;
        }

        // Only take the server down if it is up: a running server is stopped and
        // we wait for the confirmed `offline` state before writing; an already
        // offline server is written in place and left off (no restart later).
        $wasRunning = $this->power->isRunning($server);
        if ($wasRunning && ! $this->power->stopAndWait($server)) {
            $this->fail($boost, 'Server did not stop within the timeout');

            return false;
        }

        $definition = $this->registry->definition($boost->template_id);
        $updated = [];

        foreach ($this->groupByFile($boost->parameters) as $fileId => $params) {
            $fileDef = $this->fileDef($definition, $fileId);
            if ($fileDef === null) {
                foreach ($params as $param) {
                    $updated[] = $param;
                }

                continue;
            }

            $format = (string) $fileDef['format'];
            $path = (string) $fileDef['path'];
            $parsed = $this->parsers->get($format)->parse($this->read($server, $path));
            $changes = [];

            foreach ($params as $param) {
                $section = $param['section'] ?? null;
                $found = $parsed->get($param['key'], $section);
                $baseline = $found?->value ?? '0';
                $def = $this->paramDef($fileDef, $section, $param['key']);
                $boosted = $this->calculator->compute(
                    is_numeric($baseline) ? (float) $baseline : 0.0,
                    (float) $boost->multiplier,
                    isset($param['max_cap']) && is_numeric($param['max_cap']) ? (float) $param['max_cap'] : null,
                    isset($def['config']['max']) && is_numeric($def['config']['max']) ? (float) $def['config']['max'] : null,
                    ! empty($param['invert']),
                    isset($def['config']['min']) && is_numeric($def['config']['min']) ? (float) $def['config']['min'] : null,
                );
                $boostedString = $this->calculator->format($boosted, (bool) ($def['config']['float'] ?? false));

                $changes[] = new ConfigChange($param['key'], $boostedString, $section);
                $updated[] = [...$param, 'original_value' => $baseline, 'boosted_value' => $boostedString];
            }

            if ($changes !== []) {
                $this->files->writeFile($server->identifier, $path, $this->parsers->get($format)->apply($this->read($server, $path), $changes));
            }
        }

        $boost->parameters = $updated;
        $boost->status = 'active';
        $boost->applied_at = now();
        $boost->last_error = null;
        $boost->save();

        if ($wasRunning) {
            $this->power->start($server);
        }

        return true;
    }

    public function end(BoostSchedule $boost, string $finalStatus): void
    {
        $server = Server::find($boost->server_id);

        if ($server !== null) {
            $wasRunning = $this->power->isRunning($server);
            $offline = ! $wasRunning || $this->power->stopAndWait($server);
            $definition = $this->registry->definition($boost->template_id);

            foreach ($this->groupByFile($boost->parameters) as $fileId => $params) {
                $fileDef = $this->fileDef($definition, $fileId);
                if ($fileDef === null) {
                    continue;
                }
                $format = (string) $fileDef['format'];
                $path = (string) $fileDef['path'];
                $changes = [];
                foreach ($params as $param) {
                    if (isset($param['original_value'])) {
                        $changes[] = new ConfigChange($param['key'], (string) $param['original_value'], $param['section'] ?? null);
                    }
                }
                if ($changes !== []) {
                    $this->files->writeFile($server->identifier, $path, $this->parsers->get($format)->apply($this->read($server, $path), $changes));
                }
            }

            if (! $offline) {
                Log::warning('easy-config: restored boost originals without a confirmed stop', ['boost' => $boost->id]);
            }

            if ($wasRunning) {
                $this->power->start($server);
            }
        }

        $boost->status = $finalStatus === 'cancelled' ? 'cancelled' : 'completed';
        $boost->ended_at = now();
        $boost->save();

        $this->boosts->archive($boost, $boost->status);

        // A recurring boost re-arms its next occurrence only when it completes
        // naturally — cancelling a boost stops the series.
        if ($boost->status === 'completed') {
            $this->boosts->rearm($boost);
        }

        $boost->delete();
    }

    private function fail(BoostSchedule $boost, string $error): void
    {
        $boost->status = 'failed';
        $boost->last_error = $error;
        $boost->ended_at = now();
        $boost->save();
        $this->boosts->archive($boost, 'failed', $error);
        $boost->delete();
    }

    private function fileDef(?TemplateDefinition $definition, string $fileId): ?array
    {
        if ($definition === null) {
            return null;
        }
        foreach ($definition->files() as $file) {
            if ((string) ($file['id'] ?? '') === $fileId) {
                return $file;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $fileDef
     * @return array<string, mixed>
     */
    private function paramDef(array $fileDef, ?string $section, string $key): array
    {
        $params = is_array($fileDef['parameters'] ?? null) ? $fileDef['parameters'] : [];
        if ($section !== null && is_array($params[$section][$key] ?? null)) {
            return $params[$section][$key];
        }
        if (is_array($params[$key] ?? null) && isset($params[$key]['display_type'])) {
            return $params[$key];
        }

        return [];
    }

    /**
     * @param  list<array<string, mixed>>  $params
     * @return array<string, list<array<string, mixed>>>
     */
    private function groupByFile(array $params): array
    {
        $byFile = [];
        foreach ($params as $param) {
            $byFile[(string) $param['file_id']][] = $param;
        }

        return $byFile;
    }

    private function read(Server $server, string $path): string
    {
        try {
            return $this->files->getFileContent($server->identifier, $path);
        } catch (Throwable) {
            return '';
        }
    }
}
