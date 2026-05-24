<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Services\Config;

use App\Models\Server;
use App\Services\Pelican\PelicanFileService;
use Illuminate\Http\Client\RequestException;
use Plugins\EasyConfiguration\Services\Boost\BoostCalculator;
use Plugins\EasyConfiguration\Services\Boost\BoostLookup;
use Plugins\EasyConfiguration\Services\Parsing\ParserRegistry;
use Plugins\EasyConfiguration\Services\Templates\TemplateRegistry;
use Plugins\EasyConfiguration\Support\ParsedConfig;
use Throwable;

/**
 * Builds the read payload for a server's "Game configuration" section: every
 * template targeting the server's egg, each declared file read live from the
 * server via Pelican, parsed and merged with the render schema. No values are
 * persisted — they always come from the real file.
 *
 * Boost overlay (Option 2): for a parameter under an ACTIVE boost the editor
 * shows the baseline (the value to be restored), with `boost.effective_value`
 * carrying the live boosted value; a PENDING boost previews the effective value.
 */
final class ConfigReaderService
{
    public function __construct(
        private readonly TemplateRegistry $registry,
        private readonly ParserRegistry $parsers,
        private readonly ConfigMerger $merger,
        private readonly PelicanFileService $files,
        private readonly BoostLookup $boosts,
        private readonly BoostCalculator $calculator,
    ) {}

    /** @return array{templates: list<array<string, mixed>>} */
    public function read(Server $server): array
    {
        $boostMap = $this->boosts->forServer((int) $server->id);
        $templates = [];

        foreach ($this->registry->forEgg((int) $server->egg_id) as $row) {
            $definition = $this->registry->definition($row->template_id);
            if ($definition === null) {
                continue;
            }

            $files = [];
            foreach ($definition->files() as $fileDef) {
                if (($fileDef['enabled'] ?? true) === false) {
                    continue;
                }
                $files[] = $this->readFile($server, $fileDef, $boostMap);
            }

            $templates[] = [
                'id' => $definition->id(),
                'name' => $definition->name(),
                'description' => $definition->description(),
                'boost_enabled' => $definition->boostEnabled(),
                'boost_blacklist' => $definition->boostBlacklist(),
                'columns' => $definition->columns(),
                'files' => $files,
            ];
        }

        return ['templates' => $templates];
    }

    /**
     * @param  array<string, mixed>  $fileDef
     * @param  array<string, array<string, mixed>>  $boostMap
     * @return array<string, mixed>
     */
    private function readFile(Server $server, array $fileDef, array $boostMap): array
    {
        $format = (string) ($fileDef['format'] ?? 'properties');
        $path = (string) ($fileDef['path'] ?? '');
        $fileId = (string) ($fileDef['id'] ?? $path);

        $exists = true;
        $readError = false;
        try {
            $raw = $this->files->getFileContent($server->identifier, $path);
        } catch (RequestException $e) {
            // A definitive 404 means the file simply isn't there yet (hidden in
            // the UI). Any other HTTP error is a read failure, not an absence.
            $raw = '';
            if ($e->response->status() === 404) {
                $exists = false;
            } else {
                $readError = true;
            }
        } catch (Throwable) {
            // Connection refused / timeout / Wings unreachable: not "absent".
            $raw = '';
            $readError = true;
        }

        $parsed = $this->parsers->has($format)
            ? $this->parsers->get($format)->parse($raw)
            : new ParsedConfig([]);

        $merged = $this->merger->merge($fileDef, $parsed);
        $parameters = array_map(fn (array $param): array => $this->annotateBoost($param, $fileId, $boostMap), $merged['parameters']);

        return [
            'id' => $fileId,
            'label' => $fileDef['label'] ?? null,
            'path' => $path,
            'format' => $format,
            'exists' => $exists,
            'read_error' => $readError,
            'sectioned' => $merged['sectioned'],
            'section_labels' => is_array($fileDef['section_labels'] ?? null) ? $fileDef['section_labels'] : null,
            'expanded_by_default' => (bool) ($fileDef['expanded_by_default'] ?? false),
            'section_expanded' => is_array($fileDef['section_expanded'] ?? null) ? $fileDef['section_expanded'] : null,
            'parameters' => $parameters,
        ];
    }

    /**
     * @param  array<string, mixed>  $param
     * @param  array<string, array<string, mixed>>  $boostMap
     * @return array<string, mixed>
     */
    private function annotateBoost(array $param, string $fileId, array $boostMap): array
    {
        $identity = BoostLookup::identity($fileId, $param['section'] ?? null, (string) $param['key']);
        $boost = $boostMap[$identity] ?? null;

        if ($boost === null) {
            $param['boost'] = null;

            return $param;
        }

        $config = is_array($param['config'] ?? null) ? $param['config'] : [];

        if ($boost['status'] === 'active') {
            $baseline = $boost['original_value'] ?? (string) $param['value'];
            $param['value'] = $baseline;
            $effective = $boost['boosted_value'] ?? $this->preview((string) $baseline, $boost, $config);
        } else {
            $effective = $this->preview((string) $param['value'], $boost, $config);
        }

        $param['boost'] = [
            'id' => $boost['id'],
            'status' => $boost['status'],
            'multiplier' => $boost['multiplier'],
            'invert' => ! empty($boost['invert']),
            'effective_value' => $effective,
            'start_at' => $boost['start_at'],
            'end_at' => $boost['end_at'],
        ];

        return $param;
    }

    /**
     * @param  array<string, mixed>  $boost
     * @param  array<string, mixed>  $config
     */
    private function preview(string $baseline, array $boost, array $config): string
    {
        $value = $this->calculator->compute(
            is_numeric($baseline) ? (float) $baseline : 0.0,
            (float) $boost['multiplier'],
            isset($boost['max_cap']) && is_numeric($boost['max_cap']) ? (float) $boost['max_cap'] : null,
            isset($config['max']) && is_numeric($config['max']) ? (float) $config['max'] : null,
            ! empty($boost['invert']),
            isset($config['min']) && is_numeric($config['min']) ? (float) $config['min'] : null,
        );

        return $this->calculator->format($value, (bool) ($config['float'] ?? false));
    }
}
