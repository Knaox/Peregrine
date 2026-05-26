<?php

declare(strict_types=1);

namespace Tests\Feature\Plugins\PlayerCounter;

use App\Services\Pelican\PelicanClientService;
use Mockery;
use Plugins\PeregrinePlayerCounter\Services\RconClient;
use Plugins\PeregrinePlayerCounter\Services\RconPlayerQuery;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Response parsing for RCON-counted games. The RCON command/format is chosen per
 * game type (ARK `ListPlayers`, Palworld `ShowPlayers`); this covers both
 * parsers in isolation (the network side is exercised elsewhere).
 */
class RconPlayerQueryParsingTest extends TestCase
{
    use ActivatesPlayerCounterPlugin;

    protected function setUp(): void
    {
        $this->bootPlayerCounterPlugin();
        parent::setUp();
    }

    /**
     * @return array{0: int, 1: list<string>}
     */
    private function parse(string $format, string $response): array
    {
        $query = new RconPlayerQuery(
            Mockery::mock(PelicanClientService::class),
            Mockery::mock(RconClient::class),
        );

        $method = new ReflectionMethod($query, 'parse');
        $method->setAccessible(true);

        return $method->invoke($query, $format, $response);
    }

    public function test_parses_palworld_showplayers_csv_and_skips_header(): void
    {
        $response = "name,playeruid,steamid\nAlice,111,76561190000000001\nBob,222,76561190000000002";

        [$count, $names] = $this->parse('palworld', $response);

        $this->assertSame(2, $count);
        $this->assertSame(['Alice', 'Bob'], $names);
    }

    public function test_palworld_with_only_header_is_empty(): void
    {
        [$count, $names] = $this->parse('palworld', 'name,playeruid,steamid');

        $this->assertSame(0, $count);
        $this->assertSame([], $names);
    }

    public function test_parses_ark_listplayers(): void
    {
        $response = "0. Hero, 0002abc\n1. Villain, 0002def";

        [$count, $names] = $this->parse('ark', $response);

        $this->assertSame(2, $count);
        $this->assertSame(['Hero', 'Villain'], $names);
    }

    public function test_ark_no_players_connected_is_zero(): void
    {
        [$count, $names] = $this->parse('ark', 'No Players Connected');

        $this->assertSame(0, $count);
        $this->assertSame([], $names);
    }
}
