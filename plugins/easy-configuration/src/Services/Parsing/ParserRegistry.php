<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Services\Parsing;

use Plugins\EasyConfiguration\Exceptions\UnsupportedConfigFormatException;

/**
 * Maps a format id to its handler. The set is fixed (the five P0 formats) and
 * built once; the plugin resolves a single shared instance from the container.
 */
final class ParserRegistry
{
    /** @var array<string, ConfigFormat> */
    private array $formats = [];

    public function __construct()
    {
        foreach ([
            new PropertiesFormat,
            new IniFormat,
            new JsonFormat,
            new YamlFormat,
            new TomlFormat,
            new PalworldFormat,
            new TheForestFormat,
        ] as $format) {
            $this->formats[$format->format()] = $format;
        }
    }

    public function has(string $format): bool
    {
        return isset($this->formats[$format]);
    }

    public function get(string $format): ConfigFormat
    {
        return $this->formats[$format] ?? throw UnsupportedConfigFormatException::for($format);
    }

    /** @return list<string> */
    public function formats(): array
    {
        return array_keys($this->formats);
    }
}
