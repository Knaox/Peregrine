<?php

declare(strict_types=1);

namespace Tests\Feature\Plugins\EasyConfiguration;

use App\Models\User;
use App\Services\Sync\InfrastructureSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Plugins\EasyConfiguration\Http\Controllers\Admin\TemplateEggController;
use Plugins\EasyConfiguration\Services\Pelican\EggBundleImporter;
use Tests\TestCase;

class TemplateEggImportTest extends TestCase
{
    use ActivatesEasyConfigurationPlugin;
    use RefreshDatabase;

    private const UUID = 'b97bb5f8-8145-46f8-8d6b-a7bb6abeadd1';

    protected function setUp(): void
    {
        $this->bootEasyConfigurationPlugin();

        parent::setUp();
    }

    private function bundle(): string
    {
        return (string) file_get_contents(base_path('plugins/easy-configuration/official/eggs/7-days-to-die.json'));
    }

    public function test_first_import_creates_the_egg_and_resyncs(): void
    {
        Http::fake([
            '*api/application/eggs/import*' => Http::response(['object' => 'egg', 'attributes' => ['id' => 55, 'uuid' => self::UUID]], 201),
            '*api/application/eggs*' => Http::response(['data' => [], 'meta' => ['pagination' => ['total_pages' => 1]]]),
        ]);
        $sync = $this->mock(InfrastructureSync::class);
        $sync->shouldReceive('syncEggs')->once()->andReturn(1);

        $result = app(EggBundleImporter::class)->import($this->bundle());

        $this->assertSame(['pelican_egg_id' => 55, 'updated' => false], $result);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/api/application/eggs/import')
            && str_contains($request->body(), self::UUID));
    }

    public function test_reimport_reports_an_update_when_the_uuid_already_exists(): void
    {
        Http::fake([
            '*api/application/eggs/import*' => Http::response(['object' => 'egg', 'attributes' => ['id' => 55, 'uuid' => self::UUID]], 201),
            '*api/application/eggs*' => Http::response([
                'data' => [['attributes' => ['id' => 55, 'uuid' => self::UUID, 'name' => '7 Days To Die']]],
                'meta' => ['pagination' => ['total_pages' => 1]],
            ]),
        ]);
        $this->mock(InfrastructureSync::class)->shouldReceive('syncEggs')->once()->andReturn(1);

        $result = app(EggBundleImporter::class)->import($this->bundle());

        $this->assertTrue($result['updated']);
        $this->assertSame(55, $result['pelican_egg_id']);
    }

    public function test_the_bundled_egg_ships_the_sandbox_code_wiring(): void
    {
        $egg = json_decode($this->bundle(), true);

        $variable = collect($egg['variables'])->firstWhere('env_variable', 'SANDBOX_CODE');
        $this->assertNotNull($variable, 'the bundled egg must declare SANDBOX_CODE');
        $this->assertSame('A', $variable['default_value']);
        $this->assertStringContainsString('-SandboxCode=${SANDBOX_CODE}', $egg['startup_commands']['Default']);
        $this->assertSame(self::UUID, $egg['uuid']);
    }

    public function test_bundle_path_rejects_hostile_ids_and_resolves_official_ones(): void
    {
        $this->assertNull(TemplateEggController::bundlePath('../../etc/passwd'));
        $this->assertNull(TemplateEggController::bundlePath('UPPER'));
        $this->assertFileExists((string) TemplateEggController::bundlePath('7-days-to-die'));
    }

    public function test_the_import_endpoint_is_admin_gated(): void
    {
        $player = User::factory()->create(['is_admin' => false]);

        $this->actingAs($player)
            ->postJson('/api/plugins/easy-configuration/admin/templates/7-days-to-die/egg/import')
            ->assertForbidden();
    }
}
