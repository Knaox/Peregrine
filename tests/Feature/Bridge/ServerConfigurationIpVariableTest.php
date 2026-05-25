<?php

declare(strict_types=1);

namespace Tests\Feature\Bridge;

use App\Models\ServerConfiguration;
use App\Services\Bridge\IpVariableResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Locks the data-layer contract for the "IP variable" feature : the three
 * columns added by the migration are writable, persist, and round-trip with
 * the right casts. A pure in-memory model test would pass even if the
 * migration were missing — this one hits the database.
 */
class ServerConfigurationIpVariableTest extends TestCase
{
    use RefreshDatabase;

    public function test_persists_and_casts_ip_variable_columns(): void
    {
        $config = ServerConfiguration::factory()->create([
            'ip_variable_enabled' => true,
            'ip_variable_name' => 'SERVER_IP',
            'ip_variable_source' => IpVariableResolver::SOURCE_ALLOCATION_ALIAS,
        ]);

        $fresh = ServerConfiguration::findOrFail($config->id);

        $this->assertTrue($fresh->ip_variable_enabled);
        $this->assertSame('SERVER_IP', $fresh->ip_variable_name);
        $this->assertSame(IpVariableResolver::SOURCE_ALLOCATION_ALIAS, $fresh->ip_variable_source);
    }

    public function test_defaults_to_disabled(): void
    {
        $config = ServerConfiguration::factory()->create();

        $fresh = ServerConfiguration::findOrFail($config->id);

        $this->assertFalse($fresh->ip_variable_enabled);
        $this->assertNull($fresh->ip_variable_name);
        $this->assertNull($fresh->ip_variable_source);
    }
}
