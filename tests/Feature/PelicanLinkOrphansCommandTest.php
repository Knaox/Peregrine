<?php

namespace Tests\Feature;

use App\Jobs\Pelican\LinkPelicanAccountJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class PelicanLinkOrphansCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatches_link_job_for_each_unlinked_user(): void
    {
        Bus::fake();

        User::factory()->create(['email' => 'a@example.com', 'pelican_user_id' => null]);
        User::factory()->create(['email' => 'b@example.com', 'pelican_user_id' => null]);
        User::factory()->create(['email' => 'linked@example.com', 'pelican_user_id' => 42]);

        $this->artisan('pelican:link-orphans')
            ->expectsOutputToContain('Found 2 user(s)')
            ->expectsOutputToContain('Dispatched 2 LinkPelicanAccountJob(s)')
            ->assertSuccessful();

        Bus::assertDispatched(LinkPelicanAccountJob::class, 2);
    }

    public function test_dry_run_lists_without_dispatching(): void
    {
        Bus::fake();

        User::factory()->create(['email' => 'orphan@example.com', 'pelican_user_id' => null]);

        $this->artisan('pelican:link-orphans', ['--dry-run' => true])
            ->expectsOutputToContain('--dry-run: no jobs dispatched.')
            ->assertSuccessful();

        Bus::assertNotDispatched(LinkPelicanAccountJob::class);
    }

    public function test_reports_no_orphans_when_all_linked(): void
    {
        Bus::fake();

        User::factory()->create(['email' => 'a@example.com', 'pelican_user_id' => 1]);

        $this->artisan('pelican:link-orphans')
            ->expectsOutputToContain('No orphan users')
            ->assertSuccessful();

        Bus::assertNotDispatched(LinkPelicanAccountJob::class);
    }
}
