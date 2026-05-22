<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Services\Copy;

use App\Models\Server;
use App\Services\Pelican\PelicanFileService;
use Plugins\EasyConfiguration\Services\Parsing\ParserRegistry;
use Plugins\EasyConfiguration\Services\Templates\TemplateRegistry;
use Plugins\EasyConfiguration\Support\ConfigChange;
use Throwable;

/**
 * Copies selected parameter values from a source server's config files to a
 * single target server. Reads the SOURCE values live, then surgically writes
 * them into the target's files (preserving everything else). Resolves file
 * formats/paths from the source egg's templates — never trusting the client.
 */
final class CopyService
{
    public function __construct(
        private readonly TemplateRegistry $registry,
        private readonly ParserRegistry $parsers,
        private readonly PelicanFileService $files,
    ) {}

    /**
     * @param  list<array{id: string, params: list<array{key: string, section?: string|null}>}>  $fileInputs
     * @return int number of parameters copied
     */
    public function copy(Server $source, Server $target, array $fileInputs): int
    {
        $defs = $this->fileDefs($source);
        $copied = 0;

        foreach ($fileInputs as $input) {
            $def = $defs[(string) ($input['id'] ?? '')] ?? null;
            if ($def === null) {
                continue;
            }

            $format = (string) $def['format'];
            $path = (string) $def['path'];
            $parsed = $this->parsers->get($format)->parse($this->read($source, $path));

            $changes = [];
            foreach ($input['params'] ?? [] as $param) {
                $section = isset($param['section']) && is_string($param['section']) ? $param['section'] : null;
                $found = $parsed->get((string) $param['key'], $section);
                if ($found !== null) {
                    $changes[] = new ConfigChange($found->key, $found->value, $section);
                }
            }

            if ($changes === []) {
                continue;
            }

            $content = $this->parsers->get($format)->apply($this->read($target, $path), $changes);
            $this->files->writeFile($target->identifier, $path, $content);
            $copied += count($changes);
        }

        return $copied;
    }

    /** @return array<string, array<string, mixed>> fileId => file definition */
    private function fileDefs(Server $source): array
    {
        $map = [];
        foreach ($this->registry->forEgg((int) $source->egg_id) as $row) {
            $definition = $this->registry->definition($row->template_id);
            if ($definition === null) {
                continue;
            }
            foreach ($definition->files() as $file) {
                $map[(string) ($file['id'] ?? '')] = $file;
            }
        }

        return $map;
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
