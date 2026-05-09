<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Actions\Admin\DuplicateResourceTemplateAction;
use App\Models\ResourceTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mirror of `DuplicateServerConfigurationActionTest`. Locks the
 * resource-template duplication path : new name, all specs copied,
 * source untouched. The Filament row + bulk actions wrap this service.
 */
class DuplicateResourceTemplateActionTest extends TestCase
{
    use RefreshDatabase;

    private function action(): DuplicateResourceTemplateAction
    {
        return app(DuplicateResourceTemplateAction::class);
    }

    public function test_clone_gets_copy_suffix_when_first_copy(): void
    {
        $source = ResourceTemplate::factory()->create([
            'name' => 'Medium-Medium', 'ram' => 4096, 'cpu' => 200, 'disk' => 20480,
        ]);

        $clone = ($this->action())($source);

        $this->assertSame('Medium-Medium-copy', $clone->name);
        $this->assertNotSame($source->id, $clone->id);
        $this->assertSame(4096, $clone->ram);
        $this->assertSame(200, $clone->cpu);
        $this->assertSame(20480, $clone->disk);
    }

    public function test_clone_increments_when_copy_already_exists(): void
    {
        ResourceTemplate::factory()->create(['name' => 'Medium-Medium']);
        ResourceTemplate::factory()->create(['name' => 'Medium-Medium-copy']);

        $source = ResourceTemplate::where('name', 'Medium-Medium')->firstOrFail();
        $clone = ($this->action())($source);

        $this->assertSame('Medium-Medium-copy-2', $clone->name);
    }

    public function test_clone_strips_existing_copy_suffix_to_avoid_copy_copy(): void
    {
        $source = ResourceTemplate::factory()->create(['name' => 'Medium-Medium-copy-3']);
        $clone = ($this->action())($source);

        // Stem is "Medium-Medium" → first free slot is `-copy`
        // (the parent stem has no other copy yet).
        $this->assertSame('Medium-Medium-copy', $clone->name);
    }

    public function test_clone_does_not_mutate_source_name(): void
    {
        $source = ResourceTemplate::factory()->create(['name' => 'Performance']);
        ($this->action())($source);

        $this->assertSame('Performance', $source->fresh()->name);
    }

    public function test_clone_starts_with_zero_bound_configurations(): void
    {
        $source = ResourceTemplate::factory()->create();
        $clone = ($this->action())($source);

        $this->assertSame(0, $clone->serverConfigurations()->count());
    }
}
