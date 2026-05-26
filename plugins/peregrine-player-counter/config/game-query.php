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

    // ── egg -> GameDig resolution ────────────────────────────────────────────
    // Resolution order (first match wins), see Services\EggGameTypeResolver:
    //   1. `overrides` below  — curated rules that win over the generated
    //      catalogue (RCON games, EOS routing, Hytale, richer keyword aliases).
    //   2. `config('peregrine-player-counter.games')` (config/games.php) — the
    //      full GameDig catalogue auto-generated from the bundled games list
    //      (356 games), each carrying its precise type + query-port strategy.
    //   3. `fallback_type` — generic A2S probe for anything still unmatched.
    //
    // Rule shape:
    //   match        : substrings matched (case-insensitive) against the egg's
    //                  "<name> <docker_image> <tags>".
    //   type         : GameDig --type id (github.com/gamedig/node-gamedig).
    //   family       : 'minecraft' | 'source' | 'eos' | 'hytale' | 'other'.
    //                  Only 'eos'/'hytale' change runtime behaviour (alternate
    //                  sidecar path + longer timeout); the rest is informational.
    //   queryable    : false => never call the sidecar.
    //   query_port   : ['mode' => 'same']                 query = game port
    //                  ['mode' => 'offset', 'value' => N]  query = game + N
    //                  ['mode' => 'fixed',  'value' => P]  absolute query port
    //                  ['mode' => 'var', 'env' => 'NAME']  query port read from
    //                                                      a startup variable
    //                  Omit to inherit the catalogue's own strategy.
    'overrides' => [
        // Minecraft (Java + Bedrock): broad aliases, queried on the game port.
        ['match' => ['bedrock', 'pocketmine', 'nukkit', 'geyser'], 'type' => 'mbe', 'family' => 'minecraft', 'query_port' => ['mode' => 'same']],
        ['match' => ['paper', 'spigot', 'purpur', 'forge', 'fabric', 'vanilla', 'bukkit', 'sponge', 'minecraft'], 'type' => 'minecraft', 'family' => 'minecraft', 'query_port' => ['mode' => 'same']],

        // Both ARK games are counted via RCON `ListPlayers` (their types are in
        // rcon.types above), not over the wire: ASA's EOS query is 403'd by Epic,
        // and ASE's A2S sits on a usually-unreachable query port. The RCON port
        // is a startup variable, so it's auto-provisioned exactly like below.
        ['match' => ['survival ascended', 'ascended', 'asa'], 'type' => 'asa', 'family' => 'eos', 'query_port' => ['mode' => 'var', 'env' => 'RCON_PORT']],
        ['match' => ['survival evolved', 'arkse', 'ark:'], 'type' => 'ase', 'family' => 'source', 'query_port' => ['mode' => 'var', 'env' => 'RCON_PORT']],

        // Palworld has no A2S query — counted via RCON (`ShowPlayers`).
        ['match' => ['palworld'], 'type' => 'palworld', 'family' => 'source', 'query_port' => ['mode' => 'var', 'env' => 'RCON_PORT']],

        // 7 Days to Die: keep the extra legacy aliases the catalogue drops.
        ['match' => ['7 days to die', '7dtd', '7d2d', 'sdtd'], 'type' => 'sdtd', 'family' => 'source'],
    ],

    // Full GameDig catalogue (auto-generated — see config/games.php). Scanned
    // after `overrides`, before `fallback_type`. Regenerate after a GameDig bump
    // with: node sidecar/scripts/generate-catalog.mjs
    'games' => require __DIR__.'/games.php',

    // Last-resort GameDig type for any egg matched by neither the overrides nor
    // the generated catalogue: 'protocol-valve' (generic A2S). Set the env to an
    // empty string (GAME_QUERY_FALLBACK_TYPE=) to mark unmatched eggs unqueryable
    // instead of showing a best-effort card.
    'fallback_type' => env('GAME_QUERY_FALLBACK_TYPE', 'protocol-valve'),

    // Query/RCON-port resolution (see Services\QueryAccessResolver). This is
    // NEVER run automatically: when a game's query port isn't reachable, the
    // card shows a warning + a manual "Resolve" button (the same one-click,
    // server-restarting flow ARK uses). The resolver allocates a port and points
    // the relevant startup variable at it, or moves the game port for
    // adjacent-port games (Valheim & co).
    'auto_resolve' => [
        // Startup-variable name candidates the 'var' resolver points at the new
        // port (SoF QUERY_PORT…). RCON reuses the rcon.port_vars list above. The
        // 'adjacent' strategy (Valheim & co) needs no variable — it assigns the
        // node's existing free allocation at game_port+offset via the Application
        // API (the client API can't target a specific port).
        'query_port_vars' => ['QUERY_PORT', 'QUERYPORT', 'SERVER_QUERY_PORT', 'A2S_PORT'],
    ],
];
