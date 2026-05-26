<?php

declare(strict_types=1);

return [
    'settings' => [
        'title' => 'Player Counter',
        'save' => 'Save settings',
        'saved' => 'Settings saved.',
        'close' => 'Close',

        'section_connection' => 'Connection',
        'enabled' => 'Enabled',
        'enabled_help' => 'Show the live connected-player count (and up to 5 names) on every server overview.',
        'sidecar_url' => 'Sidecar URL',
        'sidecar_url_help' => 'Where Peregrine reaches the GameDig sidecar. Docker: http://game-query:9899 — bare-metal: http://127.0.0.1:9899. See the guide.',
        'sidecar_token' => 'Shared token (optional)',
        'sidecar_token_help' => 'If set, Peregrine sends it as a Bearer token; set the SAME value as GAME_QUERY_TOKEN on the sidecar. Leave empty for a loopback-only sidecar.',
        'regenerate' => 'Generate a token',
        'regenerated' => 'Token generated — save, then set the same value on the sidecar.',

        'supported_title' => 'Automatic game detection',
        'supported_intro' => 'All :count games in the GameDig catalogue are detected automatically from the egg, with the right protocol. When a game needs a dedicated query port (Valheim, Sons of the Forest, ARK…) it is allocated and configured automatically — no admin or player action.',
        'supported_note' => 'Games outside the catalogue still attempt a best-effort A2S query. Use the egg whitelist below to choose which servers show the counter.',

        'section_visibility' => 'Visibility',
        'egg_whitelist' => 'Allowed eggs (whitelist)',
        'egg_whitelist_help' => 'Among the supported games above, show the counter only on servers using these eggs. Leave empty to allow every supported egg.',

        'guide' => 'Docker setup guide',
        'copy' => 'Copy',
        'copied' => 'Copied!',
        'test' => 'Test sidecar',
        'test_no_url' => 'Set the Sidecar URL first.',
        'test_ok' => 'Sidecar is reachable from Peregrine.',
        'test_fail' => 'Could not reach the sidecar from Peregrine.',
    ],
];
