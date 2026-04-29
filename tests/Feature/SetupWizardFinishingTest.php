<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Pins the post-install behaviour of EnsureInstalled. The wizard runs
 * steps 7 (Backfill) and 8 (Webhook) AFTER step 6 (Summary) flips
 * panel.installed to true. Without the wizard-finishing sentinel,
 * EnsureInstalled would redirect /setup to / on the next refresh and
 * lock the admin out of the remaining steps.
 */
class SetupWizardFinishingTest extends TestCase
{
    private string $finishingSentinel;

    private string $installedSentinel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->finishingSentinel = storage_path('.wizard_finishing');
        $this->installedSentinel = storage_path('.installed');
        @unlink($this->finishingSentinel);
        @unlink($this->installedSentinel);
    }

    protected function tearDown(): void
    {
        @unlink($this->finishingSentinel);
        @unlink($this->installedSentinel);
        parent::tearDown();
    }

    public function test_setup_redirects_to_root_when_installed_and_no_finishing_sentinel(): void
    {
        config(['panel.installed' => true]);

        $response = $this->get('/setup');

        $response->assertRedirect('/');
    }

    public function test_setup_remains_reachable_when_installed_and_finishing_sentinel_present(): void
    {
        config(['panel.installed' => true]);
        @touch($this->finishingSentinel);

        $response = $this->get('/setup');

        $response->assertOk();
    }

    public function test_stale_finishing_sentinel_is_cleaned_up_and_redirect_resumes(): void
    {
        config(['panel.installed' => true]);
        @touch($this->finishingSentinel);
        // Backdate the sentinel by 2 hours — over the 1h staleness cap.
        @touch($this->finishingSentinel, time() - 7200);

        $response = $this->get('/setup');

        $response->assertRedirect('/');
        $this->assertFileDoesNotExist($this->finishingSentinel);
    }

    public function test_finalize_endpoint_removes_the_finishing_sentinel(): void
    {
        config(['panel.installed' => true]);
        @touch($this->finishingSentinel);
        $this->assertFileExists($this->finishingSentinel);

        $response = $this->postJson('/api/setup/finalize');

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertFileDoesNotExist($this->finishingSentinel);
    }

    public function test_finalize_endpoint_is_idempotent_when_sentinel_missing(): void
    {
        config(['panel.installed' => true]);
        // No sentinel on disk.
        $this->assertFileDoesNotExist($this->finishingSentinel);

        $response = $this->postJson('/api/setup/finalize');

        $response->assertOk()->assertJson(['success' => true]);
    }

    public function test_api_setup_paths_pass_through_when_installed(): void
    {
        // Sanity check : the post-install Backfill / Webhook API calls
        // must NOT be redirected, regardless of the wizard-finishing
        // sentinel — otherwise the SPA can't drive steps 7-8 even with
        // the sentinel set.
        config(['panel.installed' => true]);

        // docker-detect is a GET that returns JSON without touching DB —
        // perfect to confirm /api/setup/* passes through middleware.
        $response = $this->getJson('/api/setup/docker-detect');

        $response->assertOk()->assertJsonStructure(['is_docker', 'db_ready', 'defaults']);
    }
}
