<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Services\Config;

use App\Models\Server;
use App\Services\Pelican\PelicanFileService;
use Plugins\EasyConfiguration\Models\BoostSchedule;
use Plugins\EasyConfiguration\Services\Boost\BoostCalculator;
use Plugins\EasyConfiguration\Services\Boost\BoostLookup;
use Plugins\EasyConfiguration\Services\Parsing\ParserRegistry;
use Plugins\EasyConfiguration\Services\Templates\TemplateRegistry;
use Plugins\EasyConfiguration\Support\ConfigChange;
use Throwable;

/**
 * Atomic-ish save of one or more config files. Resolves each file's format/path
 * from the templates (never trusting the client), validates + builds all
 * changes, rewrites every file in memory first, then writes — so a validation
 * error writes nothing.
 *
 * Boost Option 2: a value submitted for a parameter under an ACTIVE boost is the
 * new baseline. The writer stores it back on the boost, writes the recomputed
 * (capped) boosted value to the file, and the boost still restores the new
 * baseline when it ends.
 */
final class ConfigWriterService
{
    public function __construct(
        private readonly TemplateRegistry $registry,
        private readonly ParserRegistry $parsers,
        private readonly ConfigChangeBuilder $builder,
        private readonly PelicanFileService $files,
        private readonly BoostLookup $boosts,
        private readonly BoostCalculator $calculator,
    ) {}

    /**
     * @param  list<array{id: string, values?: list<array{key: string, section?: string|null, value: string}>}>  $fileInputs
     * @return array{written: int, errors: array<string, array<string, string>>}
     */
    public function write(Server $server, array $fileInputs): array
    {
        $fileDefs = $this->fileDefsForServer($server);
        $boostMap = $this->boosts->forServer((int) $server->id);
        $errors = [];
        $plans = [];
        $baselineUpdates = [];

        foreach ($fileInputs as $input) {
            $fileId = (string) ($input['id'] ?? '');
            $def = $fileDefs[$fileId] ?? null;
            if ($def === null) {
                $errors[$fileId] = ['_file' => 'unknown file for this server'];

                continue;
            }

            $built = $this->builder->build($def, $input['values'] ?? []);
            if ($built['errors'] !== []) {
                $errors[$fileId] = $built['errors'];

                continue;
            }

            $changes = array_map(fn (ConfigChange $change): ConfigChange => $this->applyBoost($change, $fileId, $def, $boostMap, $baselineUpdates), $built['changes']);

            $format = (string) $def['format'];
            $path = (string) $def['path'];
            $plans[] = ['path' => $path, 'content' => $this->parsers->get($format)->apply($this->read($server, $path), $changes)];
        }

        if ($errors !== []) {
            return ['written' => 0, 'errors' => $errors];
        }

        $written = 0;
        foreach ($plans as $plan) {
            $this->files->writeFile($server->identifier, $plan['path'], $plan['content']);
            $written++;
        }

        $this->persistBaselines($baselineUpdates);

        return ['written' => $written, 'errors' => []];
    }

    /**
     * @param  array<string, mixed>  $def
     * @param  array<string, array<string, mixed>>  $boostMap
     * @param  array<int, array<string, array{baseline: string, boosted: string}>>  $baselineUpdates
     */
    private function applyBoost(ConfigChange $change, string $fileId, array $def, array $boostMap, array &$baselineUpdates): ConfigChange
    {
        $identity = BoostLookup::identity($fileId, $change->section, $change->key);
        $boost = $boostMap[$identity] ?? null;
        if ($boost === null || $boost['status'] !== 'active') {
            return $change;
        }

        $paramDef = $this->paramDef($def, $change->section, $change->key);
        $boosted = $this->calculator->format(
            $this->calculator->compute(
                is_numeric($change->value) ? (float) $change->value : 0.0,
                (float) $boost['multiplier'],
                isset($boost['max_cap']) && is_numeric($boost['max_cap']) ? (float) $boost['max_cap'] : null,
                isset($paramDef['config']['max']) && is_numeric($paramDef['config']['max']) ? (float) $paramDef['config']['max'] : null,
            ),
            (bool) ($paramDef['config']['float'] ?? false),
        );

        $baselineUpdates[(int) $boost['id']][$identity] = ['baseline' => $change->value, 'boosted' => $boosted];

        return new ConfigChange($change->key, $boosted, $change->section);
    }

    /**
     * @param  array<int, array<string, array{baseline: string, boosted: string}>>  $baselineUpdates
     */
    private function persistBaselines(array $baselineUpdates): void
    {
        foreach ($baselineUpdates as $boostId => $byIdentity) {
            $boost = BoostSchedule::find($boostId);
            if ($boost === null) {
                continue;
            }

            $boost->parameters = array_map(function (array $param) use ($byIdentity): array {
                $identity = BoostLookup::identity((string) $param['file_id'], $param['section'] ?? null, (string) $param['key']);
                if (isset($byIdentity[$identity])) {
                    $param['original_value'] = $byIdentity[$identity]['baseline'];
                    $param['boosted_value'] = $byIdentity[$identity]['boosted'];
                }

                return $param;
            }, $boost->parameters);
            $boost->save();
        }
    }

    /**
     * @param  array<string, mixed>  $def
     * @return array<string, mixed>
     */
    private function paramDef(array $def, ?string $section, string $key): array
    {
        $params = is_array($def['parameters'] ?? null) ? $def['parameters'] : [];
        if ($section !== null && is_array($params[$section][$key] ?? null)) {
            return $params[$section][$key];
        }
        if (is_array($params[$key] ?? null) && isset($params[$key]['display_type'])) {
            return $params[$key];
        }

        return [];
    }

    private function read(Server $server, string $path): string
    {
        try {
            return $this->files->getFileContent($server->identifier, $path);
        } catch (Throwable) {
            return '';
        }
    }

    /** @return array<string, array<string, mixed>> fileId => file definition */
    private function fileDefsForServer(Server $server): array
    {
        $map = [];
        foreach ($this->registry->forEgg((int) $server->egg_id) as $row) {
            $definition = $this->registry->definition($row->template_id);
            if ($definition === null) {
                continue;
            }
            foreach ($definition->files() as $file) {
                if (($file['enabled'] ?? true) === false) {
                    continue;
                }
                $map[(string) ($file['id'] ?? '')] = $file;
            }
        }

        return $map;
    }
}
