<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Tests\Unit\Parsing;

use PHPUnit\Framework\TestCase;
use Plugins\EasyConfiguration\Services\Parsing\JsonFormat;
use Plugins\EasyConfiguration\Support\ConfigChange;

final class JsonFormatTest extends TestCase
{
    private string $sample = '{"server":{"port":25565,"online":true},"name":"test"}';

    public function test_it_flattens_to_dotted_paths(): void
    {
        $parsed = (new JsonFormat)->parse($this->sample);

        self::assertSame('25565', $parsed->get('server.port')?->value);
        self::assertSame('true', $parsed->get('server.online')?->value);
        self::assertSame('test', $parsed->get('name')?->value);
    }

    public function test_apply_with_no_changes_is_byte_identical(): void
    {
        self::assertSame($this->sample, (new JsonFormat)->apply($this->sample, []));
    }

    public function test_it_preserves_number_type_and_key_order(): void
    {
        $result = (new JsonFormat)->apply($this->sample, [new ConfigChange('server.port', '30000')]);

        self::assertSame(
            ['server' => ['port' => 30000, 'online' => true], 'name' => 'test'],
            json_decode($result, true),
        );
    }

    public function test_it_preserves_boolean_type(): void
    {
        $result = (new JsonFormat)->apply($this->sample, [new ConfigChange('server.online', 'false')]);

        /** @var array{server: array{online: bool}} $decoded */
        $decoded = json_decode($result, true);
        self::assertFalse($decoded['server']['online']);
    }
}
