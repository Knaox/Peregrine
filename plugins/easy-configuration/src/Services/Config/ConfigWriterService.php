<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Services\Config;

use App\Models\Server;
use App\Services\Pelican\PelicanFileService;
use Plugins\EasyConfiguration\Services\Parsing\ParserRegistry;
use Plugins\EasyConfiguration\Services\Templates\TemplateRegistry;
use Throwable;

/**
 * Atomic-ish save of one or more config files for a server. Resolves each
 * file's format/path from the templates targeting the server's egg (never
 * trusting the client), validates + builds all changes, reads + rewrites every
 * file IN MEMORY first, and only then writes — so a validation error writes
 * nothing. The surgical writer keeps the rest of each file byte-identical.
 */
final class ConfigWriterService
{
    public function __construct(
        private readonly TemplateRegistry $registry,
        private readonly ParserRegistry $parsers,
        private readonly ConfigChangeBuilder $builder,
        private readonly PelicanFileService $files,
    ) {}

    /**
     * @param  list<array{id: string, values?: list<array{key: string, section?: string|null, value: string}>}>  $fileInputs
     * @return array{written: int, errors: array<string, array<string, string>>}
     */
    public function write(Server $server, array $fileInputs): array
    {
        $fileDefs = $this->fileDefsForServer($server);
        $errors = [];
        $plans = [];

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

            $format = (string) $def['format'];
            $path = (string) $def['path'];
            try {
                $raw = $this->files->getFileContent($server->identifier, $path);
            } catch (Throwable) {
                $raw = '';
            }

            $plans[] = ['path' => $path, 'content' => $this->parsers->get($format)->apply($raw, $built['changes'])];
        }

        if ($errors !== []) {
            return ['written' => 0, 'errors' => $errors];
        }

        $written = 0;
        foreach ($plans as $plan) {
            $this->files->writeFile($server->identifier, $plan['path'], $plan['content']);
            $written++;
        }

        return ['written' => $written, 'errors' => []];
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
