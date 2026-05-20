<?php

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coverage for the "Nice OAuth" theme toggle (theme_login_oauth_first):
 * persisted as a '1'/'0' string, surfaced as a real bool by /state, and
 * reflected in the resolved theme so LoginFormCard can branch on
 * theme.data.login.oauth_first. Kept in its own file so AdminThemeControllerTest
 * stays within the project's 300-line budget.
 */
class ThemeOAuthFirstTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_save_round_trips_oauth_first_as_boolean(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/api/admin/theme/save', ['theme_login_oauth_first' => true])
            ->assertOk()
            ->assertJsonPath('data.login.oauth_first', true);

        $this->assertSame('1', Setting::where('key', 'theme_login_oauth_first')->value('value'));

        $this->actingAs($this->admin())
            ->getJson('/api/admin/theme/state')
            ->assertOk()
            ->assertJsonPath('draft.theme_login_oauth_first', true);
    }

    public function test_oauth_first_defaults_to_false(): void
    {
        // Zero-regression default: existing installs keep the combined layout.
        $this->actingAs($this->admin())
            ->getJson('/api/admin/theme/state')
            ->assertOk()
            ->assertJsonPath('draft.theme_login_oauth_first', false);
    }
}
