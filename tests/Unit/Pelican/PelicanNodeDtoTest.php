<?php

declare(strict_types=1);

namespace Tests\Unit\Pelican;

use App\Services\Pelican\DTOs\PelicanNode;
use PHPUnit\Framework\TestCase;

class PelicanNodeDtoTest extends TestCase
{
    public function test_parses_daemon_connection_fields_from_api_response(): void
    {
        $node = PelicanNode::fromApiResponse([
            'attributes' => [
                'id' => 7,
                'name' => 'node-fr-1',
                'fqdn' => 'node1.example.com',
                'memory' => 32768,
                'disk' => 512000,
                'location_id' => 'eu-west',
                'scheme' => 'http',
                'daemon_listen' => 8443,
                'maintenance_mode' => true,
            ],
        ]);

        $this->assertSame(7, $node->id);
        $this->assertSame('http', $node->scheme);
        $this->assertSame(8443, $node->daemonListen);
        $this->assertTrue($node->maintenanceMode);
    }

    public function test_falls_back_to_safe_defaults_when_daemon_fields_missing(): void
    {
        $node = PelicanNode::fromApiResponse([
            'attributes' => [
                'id' => 3,
                'name' => 'legacy',
                'fqdn' => 'legacy.example.com',
            ],
        ]);

        $this->assertSame('https', $node->scheme);
        $this->assertSame(8080, $node->daemonListen);
        $this->assertFalse($node->maintenanceMode);
    }
}
