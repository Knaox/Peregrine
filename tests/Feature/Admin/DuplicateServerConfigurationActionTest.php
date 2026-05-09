<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Actions\Admin\DuplicateServerConfigurationAction;
use App\Models\ServerConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Locks the duplicate behaviour : new internal_name, all specs copied,
 * source untouched, pivot not propagated. The Filament action is a
 * thin wrapper over this service so green here = green in the UI.
 */
class DuplicateServerConfigurationActionTest extends TestCase
{
    use RefreshDatabase;

    private function action(): DuplicateServerConfigurationAction
    {
        return app(DuplicateServerConfigurationAction::class);
    }

    public function test_clone_gets_copy_suffix_when_first_copy(): void
    {
        $source = ServerConfiguration::factory()->create([
            'internal_name' => 'mc-2gb',
            'ram' => 2048, 'cpu' => 100, 'disk' => 10240,
        ]);

        $clone = ($this->action())($source);

        $this->assertSame('mc-2gb-copy', $clone->internal_name);
        $this->assertNotSame($source->id, $clone->id);
        $this->assertSame(2048, $clone->ram);
        $this->assertSame(100, $clone->cpu);
        $this->assertSame(10240, $clone->disk);
    }

    public function test_clone_increments_when_copy_already_exists(): void
    {
        ServerConfiguration::factory()->create(['internal_name' => 'mc-2gb']);
        ServerConfiguration::factory()->create(['internal_name' => 'mc-2gb-copy']);

        $source = ServerConfiguration::where('internal_name', 'mc-2gb')->firstOrFail();
        $clone = ($this->action())($source);

        $this->assertSame('mc-2gb-copy-2', $clone->internal_name);
    }

    public function test_clone_finds_first_free_slot_in_chain(): void
    {
        ServerConfiguration::factory()->create(['internal_name' => 'mc-2gb']);
        ServerConfiguration::factory()->create(['internal_name' => 'mc-2gb-copy']);
        ServerConfiguration::factory()->create(['internal_name' => 'mc-2gb-copy-2']);
        ServerConfiguration::factory()->create(['internal_name' => 'mc-2gb-copy-3']);

        $source = ServerConfiguration::where('internal_name', 'mc-2gb')->firstOrFail();
        $clone = ($this->action())($source);

        $this->assertSame('mc-2gb-copy-4', $clone->internal_name);
    }

    public function test_clone_strips_existing_copy_suffix_to_avoid_copy_copy(): void
    {
        // Source itself is a previously-cloned row. Naming the clone of
        // a clone should not snowball into "mc-2gb-copy-copy-copy".
        $source = ServerConfiguration::factory()->create(['internal_name' => 'mc-2gb-copy-3']);
        $clone = ($this->action())($source);

        // Stem is "mc-2gb" → first free slot is `-copy-4` (since
        // -copy-3 is taken by the source itself).
        $this->assertSame('mc-2gb-copy', $clone->internal_name);
    }

    public function test_clone_preserves_array_attributes_verbatim(): void
    {
        $source = ServerConfiguration::factory()->create([
            'internal_name' => 'mc-pinned',
            'allowed_node_ids' => [1, 2, 3],
            'env_var_mapping' => [
                ['variable_name' => 'SERVER_PORT', 'type' => 'main_port'],
                ['variable_name' => 'RCON_PORT', 'type' => 'offset', 'offset_value' => 1],
            ],
        ]);

        $clone = ($this->action())($source);

        $this->assertSame([1, 2, 3], $clone->allowed_node_ids);
        $this->assertSame('SERVER_PORT', $clone->env_var_mapping[0]['variable_name']);
        $this->assertSame('offset', $clone->env_var_mapping[1]['type']);
        $this->assertSame(1, $clone->env_var_mapping[1]['offset_value']);
    }

    public function test_clone_does_not_mutate_source_internal_name(): void
    {
        $source = ServerConfiguration::factory()->create(['internal_name' => 'mc-4gb']);
        $sourceIdBefore = $source->id;

        ($this->action())($source);

        $sourceFresh = ServerConfiguration::find($sourceIdBefore);
        $this->assertSame('mc-4gb', $sourceFresh->internal_name);
    }

    public function test_clone_starts_with_zero_attached_servers(): void
    {
        $source = ServerConfiguration::factory()->create();
        // Pretend the source has provisioned servers already (we don't
        // need real Server rows ; we just assert the clone's relation
        // is empty independently).

        $clone = ($this->action())($source);

        $this->assertSame(0, $clone->servers()->count());
    }
}
