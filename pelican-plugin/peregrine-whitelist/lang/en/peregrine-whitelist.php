<?php

return [
    'settings' => [
        'title' => 'Peregrine API Whitelist',
        'description' => 'Trusted Peregrine proxy IPs/hostnames are exempt from the Client & Application API rate limits. Peregrine enforces its own per-user limits.',
        'ips' => 'Trusted IPs / CIDRs',
        'ips_help' => 'Comma-separated. CIDR supported, e.g. 203.0.113.10, 10.0.0.0/24',
        'hosts' => 'Trusted hostnames',
        'hosts_help' => 'Comma-separated; resolved to IPs at boot, e.g. peregrine.example.com',
    ],
    'notifications' => [
        'saved' => 'Peregrine whitelist settings saved. Clear config cache / restart for env changes to apply.',
    ],
];
