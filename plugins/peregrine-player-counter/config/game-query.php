<?php

declare(strict_types=1);

// Static mapping + tunables for the player-counter plugin. Merged under the
// `peregrine-player-counter` config key by the service provider. The runtime
// connection settings (enabled, sidecar URL, token) live in the plugin's KV
// settings instead — see Settings\PlayerCounterSettings.
return [
    // Per-query timeouts (seconds). EOS hits the Epic API (OAuth + filter, plus
    // a possible re-query for the #654 duplicate) so it needs a longer ceiling.
    // PHP-side HTTP timeout to the sidecar. Must be > the sidecar's per-query
    // ceiling (now a single attempt) or PHP aborts first with a cURL 28.
    'timeout' => (float) env('GAME_QUERY_TIMEOUT', 8),
    'eos_timeout' => (float) env('GAME_QUERY_EOS_TIMEOUT', 14),

    // Single cache lifetime (seconds). A server-home plugin section only gets
    // `serverId` (no console stream), so freshness is poll-driven by this TTL
    // for every game. Kept >= 60s to shield the Epic API from rate-limiting.
    'cache_ttl' => (int) env('GAME_QUERY_CACHE_TTL', 30),

    // RCON fallback. Some games can't be queried over the wire (ARK: Survival
    // Ascended's EOS query is blocked by Epic with a 403; Palworld exposes no
    // A2S at all), so they're counted via RCON instead — reliable and
    // independent of Epic/Steam. The RCON port + admin password are read from
    // the server's Pelican startup variables (first matching name wins);
    // override the candidate lists if your egg uses different names. `commands`
    // overrides the RCON command + response parser per type (default: ARK's
    // `ListPlayers`). Per-game prerequisite: RCON enabled + an admin password.
    'rcon' => [
        'types' => ['asa', 'ase', 'palworld'],
        'command' => 'ListPlayers',
        'format' => 'ark',
        'commands' => [
            // Palworld: `ShowPlayers` returns CSV "name,playeruid,steamid".
            'palworld' => ['command' => 'ShowPlayers', 'format' => 'palworld'],
        ],
        'timeout' => (float) env('GAME_QUERY_RCON_TIMEOUT', 4),
        'password_vars' => ['ADMIN_PASSWORD', 'SERVER_ADMIN_PASSWORD', 'ARK_ADMIN_PASSWORD', 'ServerAdminPassword', 'RCON_PASSWORD'],
        'port_vars' => ['RCON_PORT', 'ARK_RCON_PORT', 'RCONPORT'],
        'max_players_vars' => ['MAX_PLAYERS', 'ARK_MAX_PLAYERS', 'SERVER_MAX_PLAYERS', 'MAXPLAYERS', 'SERVER_PLAYER_MAX_NUM'],
    ],

    // egg -> GameDig mapping. First rule whose any `match` substring is found
    // (case-insensitive) in "<egg name> <docker_image> <tags>" wins.
    //   type         : GameDig --type id (github.com/gamedig/node-gamedig)
    //   family       : 'minecraft' | 'source' | 'eos' | 'hytale' | 'other'
    //   queryable    : false => never call the sidecar
    //   query_offset : int added to the primary port before querying, for
    //                  games whose query port != game port (e.g. Hytale +3).
    //                  Default 0. GameDig's own port_query_offset (Valheim +1,
    //                  …) is applied by GameDig itself — don't double it here.
    // Officially-supported games ONLY. The counter ships rules for the six
    // titles below (shown in the in-panel "Supported games" notice); every
    // other egg resolves to "unsupported" and shows no card. Minecraft is two
    // rules (Java + Bedrock). ARK ASA must be matched before ASE (substring
    // order). Keep this list in sync with `supported_games` further down.
    'rules' => [
        ['match' => ['bedrock', 'pocketmine', 'nukkit', 'geyser'], 'type' => 'mbe', 'family' => 'minecraft'],
        ['match' => ['minecraft', 'paper', 'spigot', 'purpur', 'forge', 'fabric', 'vanilla', 'bukkit', 'sponge'], 'type' => 'minecraft', 'family' => 'minecraft'],

        ['match' => ['valheim'], 'type' => 'valheim', 'family' => 'source'],
        ['match' => ['7 days to die', '7dtd', '7d2d', 'sdtd'], 'type' => 'sdtd', 'family' => 'source'],

        // Both ARK games are counted via RCON `ListPlayers` (their types are in
        // rcon.types above), not over the wire: ASA's EOS query is 403'd by Epic,
        // and ASE's A2S sits on a usually-unreachable query port. Prerequisite:
        // RCON enabled + an admin password (ARK_ADMIN_PASSWORD).
        ['match' => ['survival ascended', 'ascended', 'asa'], 'type' => 'asa', 'family' => 'eos'],
        ['match' => ['survival evolved', 'arkse', 'ark:'], 'type' => 'ase', 'family' => 'source'],

        // Palworld has no A2S query — counted via RCON (`ShowPlayers`). Needs
        // RCONEnabled=True + an AdminPassword (see the rcon.* block above).
        ['match' => ['palworld'], 'type' => 'palworld', 'family' => 'source'],
    ],

    // Last-resort GameDig type for any egg WITHOUT a dedicated rule above:
    // 'protocol-valve' (generic A2S). Every whitelisted server then still shows a
    // card and attempts a count — whether it returns anything is the admin's
    // call (they chose to whitelist that egg). The games listed in `rules` are
    // queried with their precise type; everything else uses this best-effort
    // probe. Set the env to an empty string (GAME_QUERY_FALLBACK_TYPE=) to mark
    // unmapped games unqueryable instead.
    'fallback_type' => env('GAME_QUERY_FALLBACK_TYPE', 'protocol-valve'),

    // Display-only list for the settings "Supported games" notice
    // (resources/views/partials/supported-games.blade.php). Keep in sync with
    // the `rules` above.
    'supported_games' => [
        'Minecraft (Java & Bedrock)',
        'Valheim',
        '7 Days to Die',
        'ARK: Survival Ascended',
        'ARK: Survival Evolved',
        'Palworld',
    ],
];
