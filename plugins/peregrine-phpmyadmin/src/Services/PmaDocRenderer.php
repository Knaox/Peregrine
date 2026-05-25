<?php

declare(strict_types=1);

namespace Plugins\PeregrinePhpmyadmin\Services;

use Plugins\PeregrinePhpmyadmin\Settings\PmaSettings;

/**
 * Supplies the install-guide Blade views with everything they render: the live
 * Peregrine/PMA URLs, the shared secret, and the two copy-paste snippets (the
 * SignonScript and the config.inc.php block) pre-filled with the current
 * config — so the admin copies something that already works. Also builds the
 * ready-to-paste test curl. No markdown: the guide is a Blade view with
 * copy-to-clipboard code blocks.
 */
class PmaDocRenderer
{
    public const REDEEM_PATH = '/api/plugins/peregrine-phpmyadmin/redeem';

    /**
     * @return array<string, string>
     */
    public function context(PmaSettings $settings): array
    {
        $peregrineUrl = $this->peregrineUrl();
        $secret = $this->secretOrPlaceholder($settings);
        $pmaUrl = $settings->pmaUrl !== '' ? $settings->pmaUrl : 'https://pma.example.com';

        return [
            'peregrineUrl' => $peregrineUrl,
            'redeemUrl' => $peregrineUrl.self::REDEEM_PATH,
            'pmaUrl' => $pmaUrl,
            'secret' => $secret,
            'signonScript' => $this->signonScript($peregrineUrl, $secret),
            'configSnippet' => $this->configSnippet($peregrineUrl),
        ];
    }

    public function curlSnippet(PmaSettings $settings, string $token): string
    {
        $url = $this->peregrineUrl().self::REDEEM_PATH;
        $secret = $this->secretOrPlaceholder($settings);

        return "curl -sS -X POST '{$url}' \\\n"
            ."  -H 'Content-Type: application/json' \\\n"
            ."  -H 'X-Plugin-Secret: {$secret}' \\\n"
            ."  -d '{\"token\":\"{$token}\"}'";
    }

    private function signonScript(string $peregrineUrl, string $secret): string
    {
        $path = __DIR__.'/../../resources/signon/peregrine_signon.php.stub';
        $stub = is_file($path) ? (string) file_get_contents($path) : '';

        return strtr($stub, [
            '{{ peregrine_url }}' => $peregrineUrl,
            '{{ shared_secret }}' => $secret,
            '{{ redeem_path }}' => self::REDEEM_PATH,
        ]);
    }

    private function configSnippet(string $peregrineUrl): string
    {
        return <<<PHP
        \$i++;
        \$cfg['Servers'][\$i]['auth_type']            = 'signon';
        \$cfg['Servers'][\$i]['SignonScript']         = 'peregrine_signon.php';
        \$cfg['Servers'][\$i]['SignonURL']            = '{$peregrineUrl}';
        \$cfg['Servers'][\$i]['LogoutURL']            = '{$peregrineUrl}';
        \$cfg['Servers'][\$i]['host']                 = '';   // forced by the SignonScript
        \$cfg['Servers'][\$i]['AllowArbitraryServer'] = true;
        \$cfg['Servers'][\$i]['verbose']              = 'Peregrine';
        PHP;
    }

    private function peregrineUrl(): string
    {
        return rtrim((string) config('app.url'), '/');
    }

    private function secretOrPlaceholder(PmaSettings $settings): string
    {
        return $settings->sharedSecret !== '' ? $settings->sharedSecret : 'YOUR_SHARED_SECRET';
    }
}
