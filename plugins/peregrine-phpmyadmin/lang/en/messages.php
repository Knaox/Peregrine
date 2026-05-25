<?php

declare(strict_types=1);

return [
    'settings' => [
        'title' => 'phpMyAdmin integration',
        'save' => 'Save settings',
        'saved' => 'Settings saved.',
        'close' => 'Close',

        'section_connection' => 'Connection',
        'enabled' => 'Enabled',
        'enabled_help' => 'Show the "Open in phpMyAdmin" button on every server database.',
        'pma_url' => 'phpMyAdmin URL',
        'pma_url_help' => 'The URL where you open phpMyAdmin in your browser (e.g. http://192.168.1.30/phpmyadmin) — NOT Peregrine\'s URL.',
        'shared_secret' => 'Shared secret',
        'shared_secret_help' => 'Sent by your phpMyAdmin SignonScript in the X-Plugin-Secret header. Copy it into peregrine_signon.php.',
        'regenerate' => 'Regenerate secret',
        'regenerate_warning' => 'This invalidates the current secret. phpMyAdmin will reject logins until you update peregrine_signon.php with the new value.',
        'regenerated' => 'Secret regenerated — save, then update peregrine_signon.php.',
        'token_ttl' => 'Token lifetime (seconds)',
        'token_ttl_help' => 'How long a signon token stays valid before redemption (10–120s, default 30).',
        'auto_login' => 'Automatic login',
        'auto_login_help' => 'When on, the button logs the user in automatically (signon). When off, it opens phpMyAdmin and the user enters their credentials manually (visible via "Show password" in the Databases tab).',
        'auto_select_db' => 'Auto-select database',
        'auto_select_db_help' => 'Pre-select the clicked database in phpMyAdmin via ?db=.',
        'server_index' => 'Signon server index',
        'server_index_help' => 'The $i index of the signon server in config.inc.php. Leave empty if it is your only / default server; set it (e.g. 2) when phpMyAdmin already has another server, so direct access keeps its normal login.',

        'section_security' => 'Security (advanced)',
        'section_security_desc' => 'Optional hardening for the public redeem endpoint.',
        'ip_allowlist' => 'IP allowlist',
        'ip_allowlist_help' => 'One IP or CIDR per line. When set, only these addresses may redeem tokens (your phpMyAdmin host). Empty = no restriction.',
        'rate_limit' => 'Launches per minute per user',
        'rate_limit_help' => 'Maximum phpMyAdmin launches a user can trigger each minute.',

        'guide' => 'Installation guide',
        'copy' => 'Copy',
        'copied' => 'Copied!',
        'step' => 'Step',
        'troubleshooting' => 'Troubleshooting',
        'test_curl' => 'Test the bridge (curl)',
        'test_curl_help' => 'Run this from your phpMyAdmin host. A 200 response with fake credentials means the redeem bridge works.',
        'test_reachability' => 'Test reachability',
        'reachability_no_url' => 'Set the phpMyAdmin URL first.',
        'reachability_ok' => 'phpMyAdmin URL is reachable from Peregrine.',
        'reachability_fail' => 'Could not reach the phpMyAdmin URL from Peregrine.',
    ],
];
