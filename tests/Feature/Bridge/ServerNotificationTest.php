<?php

namespace Tests\Feature\Bridge;

use App\Events\Bridge\ServerProvisioned;
use App\Events\Bridge\ServerSuspended;
use App\Listeners\Bridge\SendServerReadyNotification;
use App\Listeners\Bridge\SendServerSuspendedNotification;
use App\Models\OAuthIdentity;
use App\Models\Server;
use App\Models\ServerPlan;
use App\Models\User;
use App\Notifications\Bridge\ServerReadyNotification;
use App\Notifications\Bridge\ServerSuspendedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Sprint 3 — server lifecycle email notifications. Verifies :
 *  - ServerProvisioned event triggers ServerReadyNotification
 *  - ServerSuspended event triggers ServerSuspendedNotification
 *  - Local user (no OAuth identity) gets the LOCAL template (with reset URL)
 *  - OAuth user (has identity) gets the OAUTH template (no reset URL)
 */
class ServerNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_server_provisioned_event_dispatches_ready_notification(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $server = $this->makeServer($user);

        event(new ServerProvisioned($server, $user));

        Notification::assertSentTo($user, ServerReadyNotification::class);
    }

    public function test_server_suspended_event_dispatches_suspended_notification(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $server = $this->makeServer($user);

        event(new ServerSuspended($server, $user));

        Notification::assertSentTo($user, ServerSuspendedNotification::class);
    }

    public function test_ready_notification_uses_local_template_when_user_has_no_oauth_identity(): void
    {
        $user = User::factory()->create();
        $server = $this->makeServer($user);

        // Render the notification's mail content and inspect it.
        $notification = new ServerReadyNotification($server);
        $mail = $notification->toMail($user);

        // The local template includes a "set my password" link — assert it
        // appears in the rendered body. The OAuth template does NOT include it.
        $rendered = $mail->render();
        $this->assertStringContainsString('Set my password', (string) $rendered);
    }

    public function test_ready_notification_uses_oauth_template_when_user_has_oauth_identity(): void
    {
        $user = User::factory()->create();
        OAuthIdentity::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'g-test-1',
            'provider_email' => $user->email,
        ]);
        $server = $this->makeServer($user);

        $notification = new ServerReadyNotification($server);
        $mail = $notification->toMail($user);

        $rendered = (string) $mail->render();
        // OAuth template uses a different CTA — no password reset link.
        $this->assertStringNotContainsString('Set my password', $rendered);
        $this->assertStringContainsString('Open the panel', $rendered);
    }

    public function test_listeners_are_wired_to_events(): void
    {
        // Sanity check : the event listener registry has our bindings.
        // This catches a regression if someone removes the Event::listen
        // calls in SocialAuthServiceProvider::boot().
        Event::fake();

        $user = User::factory()->create();
        $server = $this->makeServer($user);

        event(new ServerProvisioned($server, $user));
        event(new ServerSuspended($server, $user));

        Event::assertListening(ServerProvisioned::class, SendServerReadyNotification::class);
        Event::assertListening(ServerSuspended::class, SendServerSuspendedNotification::class);
    }

    private function makeServer(User $user): Server
    {
        $nest = \App\Models\Nest::create(['pelican_nest_id' => mt_rand(1, 9999), 'name' => 'N']);
        $egg = \App\Models\Egg::create([
            'pelican_egg_id' => mt_rand(1, 9999), 'nest_id' => $nest->id,
            'name' => 'E', 'docker_image' => 't:1', 'startup' => 'echo',
        ]);
        $node = \App\Models\Node::create([
            'pelican_node_id' => mt_rand(1, 9999), 'name' => 'NN',
            'fqdn' => 'n.test', 'scheme' => 'https', 'memory' => 1, 'disk' => 1,
        ]);
        $plan = ServerPlan::create([
            'name' => 'Test Plan', 'stripe_price_id' => 'price_'.\Illuminate\Support\Str::random(8),
            'egg_id' => $egg->id, 'nest_id' => $nest->id, 'node_id' => $node->id,
            'ram' => 1024, 'cpu' => 100, 'disk' => 5000, 'is_active' => true,
        ]);

        return Server::create([
            'user_id' => $user->id,
            'pelican_server_id' => mt_rand(100, 999),
            'identifier' => substr(\Illuminate\Support\Str::random(8), 0, 8),
            'name' => 'srv-test',
            'status' => 'active',
            'egg_id' => $egg->id,
            'plan_id' => $plan->id,
            'scheduled_deletion_at' => now()->addDays(14),
        ]);
    }
}
