<?php

return [
    'settings' => [
        'title' => 'Whitelist API Peregrine',
        'description' => 'Les IP/domaines du proxy Peregrine de confiance sont exemptés des rate limits Client & Application API. Peregrine applique ses propres limites par utilisateur.',
        'ips' => 'IP / CIDR de confiance',
        'ips_help' => 'Séparés par des virgules. CIDR supporté, ex. 203.0.113.10, 10.0.0.0/24',
        'hosts' => 'Domaines de confiance',
        'hosts_help' => 'Séparés par des virgules ; résolus en IP au boot, ex. peregrine.example.com',
    ],
    'notifications' => [
        'saved' => 'Réglages whitelist Peregrine enregistrés. Vide le cache de config / redémarre pour appliquer les changements env.',
    ],
];
