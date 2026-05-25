<?php

declare(strict_types=1);

namespace Plugins\PeregrinePlayerCounter\Services;

use Plugins\PeregrinePlayerCounter\Settings\PlayerCounterSettings;

/**
 * Supplies the install-guide Blade views with ready-to-paste snippets: the
 * docker-compose service block for the GameDig sidecar (pre-filled with the
 * current token, if any) and the matching Sidecar URL to enter in the plugin
 * settings. No markdown — the guide is a Blade view with copy-to-clipboard
 * code blocks, mirroring the phpMyAdmin plugin.
 */
class PlayerCounterDocRenderer
{
    public const SIDECAR_PATH = 'plugins/peregrine-player-counter/sidecar';

    /**
     * @return array<string, string>
     */
    public function context(PlayerCounterSettings $settings): array
    {
        return [
            'composeSnippet' => $this->composeSnippet($settings),
            'sidecarUrlDocker' => 'http://game-query:9899',
            'sidecarUrlLocal' => 'http://127.0.0.1:9899',
            'bareMetalCmd' => 'cd '.self::SIDECAR_PATH.' && npm install && node index.mjs',
            'hasToken' => $settings->sidecarToken !== '' ? '1' : '',
        ];
    }

    private function composeSnippet(PlayerCounterSettings $settings): string
    {
        $tokenLine = $settings->sidecarToken !== ''
            ? "\n      GAME_QUERY_TOKEN: \"{$settings->sidecarToken}\""
            : '';

        return <<<YAML
  game-query:
    build: ./{$this->sidecarPath()}
    container_name: peregrine-game-query
    restart: unless-stopped
    environment:
      GAME_QUERY_HOST: 0.0.0.0
      GAME_QUERY_PORT: "9899"{$tokenLine}
YAML;
    }

    private function sidecarPath(): string
    {
        return self::SIDECAR_PATH;
    }
}
