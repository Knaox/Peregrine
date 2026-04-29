<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `/plugins/{id}/bundle.js` exists as a controller-served fallback for the
 * static symlink at `public/plugins/{id}` → `plugins/{id}/frontend/dist`.
 * Without it, a missing symlink (fresh Docker volume timing, marketplace
 * install with a malformed ZIP, etc.) lets the request fall through to the
 * SPA catch-all, return HTML, and trip Cloudflare's `nosniff` so the browser
 * refuses to execute the script — the plugin's page renders blank.
 *
 * These tests pin the contract so :
 *  - existing bundle returns 200 + `application/javascript`
 *  - unknown plugin returns a clean 404 (not the SPA HTML)
 *  - other `/plugins/...` paths 404 cleanly thanks to the SPA exclusion
 */
class PluginBundleRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_serves_bundle_with_javascript_content_type(): void
    {
        $response = $this->get('/plugins/invitations/bundle.js');

        $response->assertOk();
        $this->assertStringContainsString(
            'application/javascript',
            (string) $response->headers->get('Content-Type'),
        );
        $this->assertNotEmpty(
            $response->headers->get('Cache-Control'),
            'Bundle must be cacheable — the URL is version-busted via ?v=<version>.',
        );
    }

    public function test_unknown_plugin_returns_404(): void
    {
        // The original bug : the SPA catch-all matched /plugins/... and
        // returned a 200 + HTML, so the browser refused to execute the
        // "script". With the controller fallback + SPA exclusion, missing
        // bundles must 404 — the browser treats 4xx script loads as load
        // errors instead of nosniff violations.
        $this->get('/plugins/does-not-exist/bundle.js')->assertNotFound();
    }

    public function test_other_plugin_paths_do_not_match_spa_catchall(): void
    {
        // The SPA catch-all in routes/web.php excludes `plugins` so any other
        // request under /plugins/* returns a clean 404 instead of the React
        // shell HTML — otherwise a stray asset URL would still trip nosniff.
        $response = $this->get('/plugins/invitations/some-other-asset.js');

        $response->assertNotFound();
    }
}
