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

    // RCON fallback. ARK: Survival Ascended's EOS query is currently blocked by
    // Epic (HTTP 403), so these game types are counted via Source RCON
    // `ListPlayers` instead — reliable and independent of Epic. The RCON port +
    // admin password are read from the server's Pelican startup variables
    // (first matching name wins); override the candidate lists if your egg uses
    // different variable names.
    'rcon' => [
        'types' => ['asa', 'ase'],
        'command' => 'ListPlayers',
        'timeout' => (float) env('GAME_QUERY_RCON_TIMEOUT', 4),
        'password_vars' => ['ADMIN_PASSWORD', 'SERVER_ADMIN_PASSWORD', 'ARK_ADMIN_PASSWORD', 'ServerAdminPassword', 'RCON_PASSWORD'],
        'port_vars' => ['RCON_PORT', 'ARK_RCON_PORT', 'RCONPORT'],
        'max_players_vars' => ['MAX_PLAYERS', 'ARK_MAX_PLAYERS', 'SERVER_MAX_PLAYERS', 'MAXPLAYERS'],
    ],

    // egg -> GameDig mapping. First rule whose any `match` substring is found
    // (case-insensitive) in "<egg name> <docker_image> <tags>" wins.
    //   type      : GameDig --type id (github.com/gamedig/node-gamedig)
    //   family    : 'minecraft' | 'source' | 'eos' | 'hytale' | 'other'
    //   queryable : false => never call the sidecar
    'rules' => [
        ['match' => ['bedrock', 'pocketmine', 'nukkit', 'geyser'], 'type' => 'mbe', 'family' => 'minecraft'],
        ['match' => ['minecraft', 'paper', 'spigot', 'purpur', 'forge', 'fabric', 'vanilla', 'bukkit', 'sponge'], 'type' => 'minecraft', 'family' => 'minecraft'],

        ['match' => ['counter-strike 2', 'counterstrike2', 'cs2'], 'type' => 'counterstrike2', 'family' => 'source'],
        ['match' => ['global offensive', 'csgo', 'cs:go'], 'type' => 'csgo', 'family' => 'source'],
        ['match' => ["garry's mod", 'garrys mod', 'garrysmod', 'gmod'], 'type' => 'garrysmod', 'family' => 'source'],
        ['match' => ['team fortress 2', 'teamfortress2', 'tf2'], 'type' => 'teamfortress2', 'family' => 'source'],
        ['match' => ['rust'], 'type' => 'rust', 'family' => 'source'],

        // ARK: Survival Ascended is EOS-only (queried via the Epic API); Survival
        // Evolved is the legacy A2S game. ASA must be tested before ASE.
        ['match' => ['survival ascended', 'ascended', 'asa'], 'type' => 'asa', 'family' => 'eos'],
        ['match' => ['survival evolved', 'arkse', 'ark:'], 'type' => 'ase', 'family' => 'source'],

        ['match' => ['valheim'], 'type' => 'valheim', 'family' => 'source'],
        ['match' => ['7 days to die', '7dtd', '7d2d', 'sdtd'], 'type' => 'sdtd', 'family' => 'source'],
        ['match' => ['project zomboid', 'projectzomboid', 'zomboid'], 'type' => 'projectzomboid', 'family' => 'source'],
        ['match' => ['palworld'], 'type' => 'palworld', 'family' => 'source'],
        ['match' => ['unturned'], 'type' => 'unturned', 'family' => 'source'],
        ['match' => ['terraria', 'tshock'], 'type' => 'terrariatshock', 'family' => 'source'],

        // Other EOS games (Epic API).
        ['match' => ['renown'], 'type' => 'renown', 'family' => 'eos'],
        ['match' => ['the isle', 'evrima'], 'type' => 'tie', 'family' => 'eos'],
        ['match' => ['squad'], 'type' => 'squad', 'family' => 'eos'],

        // Hytale — queried via the nitrado/hytale-plugin-query server mod: the
        // count only returns if that mod is installed (otherwise reads offline).
        ['match' => ['hytale'], 'type' => 'hytale', 'family' => 'hytale'],
    ],

    // Type used when no rule matches. Set to a GameDig type (e.g.
    // 'protocol-valve' for a best-effort A2S probe) to query unknown Steam
    // games, or null (default) to mark them "unsupported" rather than risk a
    // wrong "offline" on a non-A2S game.
    'fallback_type' => env('GAME_QUERY_FALLBACK_TYPE'),
];
