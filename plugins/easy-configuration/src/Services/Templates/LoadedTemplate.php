<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Services\Templates;

/**
 * Outcome of loading one template file from disk: either a validated
 * definition, or the validation/JSON error that made it invalid. Carries the
 * content checksum so the registry can skip unchanged files on rebuild.
 */
final class LoadedTemplate
{
    private function __construct(
        public readonly string $id,
        public readonly string $sourcePath,
        public readonly string $checksum,
        public readonly bool $valid,
        public readonly ?TemplateDefinition $definition = null,
        public readonly ?string $error = null,
    ) {}

    public static function valid(string $id, string $path, string $checksum, TemplateDefinition $definition): self
    {
        return new self($id, $path, $checksum, true, $definition);
    }

    public static function invalid(string $id, string $path, string $checksum, string $error): self
    {
        return new self($id, $path, $checksum, false, null, $error);
    }
}
