<?php

declare(strict_types=1);

namespace Plugins\PeregrinePhpmyadmin\Tests\Feature;

use Plugins\PeregrinePhpmyadmin\Services\PmaDocRenderer;
use Plugins\PeregrinePhpmyadmin\Settings\PmaSettings;
use Plugins\PeregrinePhpmyadmin\Tests\TestCase;

/**
 * Compiles the install-guide + curl Blade views (catches Blade syntax,
 *
 * @include resolution, the copy-block partial and variable interpolation
 * without needing a browser). The copy buttons themselves are Alpine and
 * exercised at runtime, but the markup must be present and well-formed.
 */
class PmaGuideViewTest extends TestCase
{
    public function test_guide_view_renders_both_languages_with_copyable_snippets(): void
    {
        $ctx = app(PmaDocRenderer::class)->context(PmaSettings::make());

        $html = view('peregrine-phpmyadmin::guide', ['ctx' => $ctx])->render();

        // The two copy-paste targets are present...
        $this->assertStringContainsString('peregrine_signon.php', $html);
        $this->assertStringContainsString('AllowArbitraryServer', $html);
        // ...inside a copy-block (the x-ref the Copy button reads from)...
        $this->assertStringContainsString('x-ref="code"', $html);
        // ...and both language panels rendered.
        $this->assertStringContainsString('Français', $html);
        $this->assertStringContainsString('English', $html);
        // ...and the "where is config.inc.php?" hint is present.
        $this->assertStringContainsString('config.sample.inc.php', $html);
        $this->assertStringContainsString('/etc/phpmyadmin/config.inc.php', $html);
        // ...with the per-install file location + permissions...
        $this->assertStringContainsString('/usr/share/phpmyadmin/peregrine_signon.php', $html);
        $this->assertStringContainsString('chmod 640', $html);
        // ...and the guide explains targeting the signon server via ?server=N.
        $this->assertStringContainsString('?server=2', $html);
    }

    public function test_curl_view_renders_a_copyable_command(): void
    {
        $html = view('peregrine-phpmyadmin::curl', [
            'curl' => app(PmaDocRenderer::class)->curlSnippet(PmaSettings::make(), 'test-token'),
        ])->render();

        $this->assertStringContainsString('X-Plugin-Secret', $html);
        $this->assertStringContainsString('test-token', $html);
        $this->assertStringContainsString('x-ref="code"', $html);
    }
}
