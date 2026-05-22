<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Services\Config;

use App\Models\Server;
use App\Services\Pelican\PelicanFileService;
use Plugins\EasyConfiguration\Services\Parsing\ParserRegistry;
use Plugins\EasyConfiguration\Services\Templates\TemplateRegistry;
use Plugins\EasyConfiguration\Support\ParsedConfig;
use Throwable;

/**
 * Builds the read payload for a server's "Game configuration" section: every
 * template targeting the server's egg, each declared file read live from the
 * server via Pelican, parsed and merged with the render schema. No values are
 * persisted — they always come from the real file.
 */
final class ConfigReaderService
{
    public function __construct(
        private readonly TemplateRegistry $registry,
        private readonly ParserRegistry $parsers,
        private readonly ConfigMerger $merger,
        private readonly PelicanFileService $files,
    ) {}

    /** @return array{templates: list<array<string, mixed>>} */
    public function read(Server $server): array
    {
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
                $files[] = $this->readFile($server, $fileDef);
            }

            $templates[] = [
                'id' => $definition->id(),
                'name' => $definition->name(),
                'description' => $definition->description(),
                'boost_enabled' => $definition->boostEnabled(),
                'boost_blacklist' => $definition->boostBlacklist(),
                'files' => $files,
            ];
        }

        return ['templates' => $templates];
    }

    /**
     * @param  array<string, mixed>  $fileDef
     * @return array<string, mixed>
     */
    private function readFile(Server $server, array $fileDef): array
    {
        $format = (string) ($fileDef['format'] ?? 'properties');
        $path = (string) ($fileDef['path'] ?? '');

        $exists = true;
        try {
            $raw = $this->files->getFileContent($server->identifier, $path);
        } catch (Throwable) {
            $raw = '';
            $exists = false;
        }

        $parsed = $this->parsers->has($format)
            ? $this->parsers->get($format)->parse($raw)
            : new ParsedConfig([]);

        $merged = $this->merger->merge($fileDef, $parsed);

        return [
            'id' => (string) ($fileDef['id'] ?? $path),
            'label' => $fileDef['label'] ?? null,
            'path' => $path,
            'format' => $format,
            'exists' => $exists,
            'sectioned' => $merged['sectioned'],
            'parameters' => $merged['parameters'],
        ];
    }
}
