<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Services\Templates;

/**
 * Typed read-only view over a validated template JSON document. The raw array
 * is preserved for shipping to the frontend verbatim; the getters expose the
 * pieces the backend reasons about (egg targeting, boost config, files).
 */
final class TemplateDefinition
{
    /** @param array<string, mixed> $data */
    private function __construct(public readonly array $data) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    public function id(): string
    {
        return (string) ($this->data['id'] ?? '');
    }

    public function version(): string
    {
        return (string) ($this->data['version'] ?? '1.0.0');
    }

    /** @return array<string, string> */
    public function name(): array
    {
        return $this->labelArray($this->data['name'] ?? []);
    }

    /** @return array<string, string> */
    public function description(): array
    {
        return $this->labelArray($this->data['description'] ?? []);
    }

    public function author(): ?string
    {
        $author = $this->data['author'] ?? null;

        return is_string($author) ? $author : null;
    }

    /** @return list<int> */
    public function targetEggs(): array
    {
        $eggs = $this->data['target_eggs'] ?? [];

        return is_array($eggs)
            ? array_values(array_map(static fn ($id): int => (int) $id, array_filter($eggs, 'is_int')))
            : [];
    }

    public function boostEnabled(): bool
    {
        return (bool) (($this->data['boost']['enabled'] ?? false));
    }

    /** @return list<string> */
    public function boostBlacklist(): array
    {
        $list = $this->data['boost']['parameter_blacklist'] ?? [];

        return is_array($list) ? array_values(array_map('strval', $list)) : [];
    }

    /** @return list<array<string, mixed>> */
    public function files(): array
    {
        $files = $this->data['files'] ?? [];

        return is_array($files) ? array_values(array_filter($files, 'is_array')) : [];
    }

    public function fileCount(): int
    {
        return count($this->files());
    }

    /** @param mixed $value @return array<string, string> */
    private function labelArray(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $locale => $text) {
            if (is_string($locale) && is_string($text)) {
                $out[$locale] = $text;
            }
        }

        return $out;
    }
}
