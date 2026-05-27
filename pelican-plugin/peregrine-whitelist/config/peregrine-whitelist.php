<?php

return [
    // Comma-separated IPs or CIDR ranges of the trusted Peregrine instance(s).
    // Requests originating from these are exempt from the Client/Application
    // API rate limits. CIDR is supported (Symfony IpUtils).
    //   PEREGRINE_WHITELIST_IPS="203.0.113.10,10.0.0.0/24"
    'ips' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('PEREGRINE_WHITELIST_IPS', ''))
    ))),

    // Comma-separated hostnames, resolved to IPs once per process at boot
    // (handy when Peregrine sits behind a domain with a changing IP).
    //   PEREGRINE_WHITELIST_HOSTS="peregrine.example.com"
    'hostnames' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('PEREGRINE_WHITELIST_HOSTS', ''))
    ))),
];
