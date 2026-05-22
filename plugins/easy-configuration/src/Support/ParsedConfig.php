<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Support;

/**
 * The result of parsing a config file: an ordered list of parameters. Order
 * mirrors the file so the UI can preserve it. Lookups are by (section, key).
 */
final class ParsedConfig
{
    /** @param list<ConfigParameter> $parameters */
    public function __construct(public readonly array $parameters) {}

    public function get(string $key, ?string $section = null): ?ConfigParameter
    {
        foreach ($this->parameters as $parameter) {
            if ($parameter->key === $key && $parameter->section === $section) {
                return $parameter;
            }
        }

        return null;
    }

    public function has(string $key, ?string $section = null): bool
    {
        return $this->get($key, $section) instanceof ConfigParameter;
    }

    /** @return list<array{key: string, value: string, section: string|null}> */
    public function toArray(): array
    {
        return array_map(static fn (ConfigParameter $p): array => $p->toArray(), $this->parameters);
    }
}
