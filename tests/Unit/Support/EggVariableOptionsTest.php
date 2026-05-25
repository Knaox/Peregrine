<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Models\Egg;
use App\Services\Pelican\PelicanApplicationService;
use App\Support\EggVariableOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class EggVariableOptionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_returns_empty_for_null_egg(): void
    {
        $this->assertSame([], EggVariableOptions::forEgg(null));
        $this->assertSame([], EggVariableOptions::forEgg(0));
    }

    public function test_returns_empty_for_missing_egg(): void
    {
        $this->assertSame([], EggVariableOptions::forEgg(999999));
    }

    public function test_maps_pelican_variables_to_options(): void
    {
        $egg = $this->egg(pelicanEggId: 42);

        $this->mock(PelicanApplicationService::class)
            ->shouldReceive('getEggVariables')
            ->once()
            ->with(42)
            ->andReturn([
                ['env_variable' => 'SERVER_PORT', 'name' => 'Server Port', 'default' => '25565'],
                ['env_variable' => 'EULA', 'name' => '', 'default' => 'true'],
            ]);

        $options = EggVariableOptions::forEgg($egg->id);

        $this->assertSame([
            'SERVER_PORT' => 'SERVER_PORT — Server Port',
            'EULA' => 'EULA',
        ], $options);
    }

    public function test_caches_successful_lookup(): void
    {
        $egg = $this->egg(pelicanEggId: 7);

        $this->mock(PelicanApplicationService::class)
            ->shouldReceive('getEggVariables')
            ->once() // cached on the second call
            ->andReturn([['env_variable' => 'A', 'name' => 'Alpha', 'default' => '']]);

        EggVariableOptions::forEgg($egg->id);
        $options = EggVariableOptions::forEgg($egg->id);

        $this->assertSame(['A' => 'A — Alpha'], $options);
    }

    public function test_returns_empty_and_does_not_cache_on_failure(): void
    {
        $egg = $this->egg(pelicanEggId: 13);

        // Throwing twice proves the failure is NOT cached : the second call
        // retries instead of returning a stale empty list from cache.
        $this->mock(PelicanApplicationService::class)
            ->shouldReceive('getEggVariables')
            ->twice()
            ->andThrow(new \RuntimeException('pelican down'));

        $this->assertSame([], EggVariableOptions::forEgg($egg->id));
        $this->assertSame([], EggVariableOptions::forEgg($egg->id));
    }

    private function egg(int $pelicanEggId): Egg
    {
        return Egg::create([
            'pelican_egg_id' => $pelicanEggId,
            'nest_id' => null,
            'name' => 'Test Egg',
            'docker_image' => 'ghcr.io/test',
            'startup' => 'java -jar server.jar',
            'description' => null,
            'tags' => [],
            'features' => [],
        ]);
    }
}
