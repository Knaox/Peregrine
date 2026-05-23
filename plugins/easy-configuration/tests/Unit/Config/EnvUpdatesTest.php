<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Tests\Unit\Config;

use PHPUnit\Framework\TestCase;
use Plugins\EasyConfiguration\Services\Config\ConfigWriterService;
use Plugins\EasyConfiguration\Support\ConfigChange;

final class EnvUpdatesTest extends TestCase
{
    public function test_it_collects_only_linked_params(): void
    {
        $def = [
            'parameters' => [
                'max-players' => ['display_type' => 'number', 'env_var' => 'MAX_PLAYERS'],
                'motd' => ['display_type' => 'text'],
            ],
        ];
        $changes = [
            new ConfigChange('max-players', '64'),
            new ConfigChange('motd', 'hello'),
        ];

        self::assertSame(['MAX_PLAYERS' => '64'], ConfigWriterService::envUpdatesForFile($def, $changes));
    }

    public function test_it_resolves_env_var_inside_a_section(): void
    {
        $def = [
            'parameters' => [
                'ServerSettings' => ['MaxPlayers' => ['display_type' => 'number', 'env_var' => 'MAX_PLAYERS']],
            ],
        ];
        $changes = [new ConfigChange('MaxPlayers', '40', 'ServerSettings')];

        self::assertSame(['MAX_PLAYERS' => '40'], ConfigWriterService::envUpdatesForFile($def, $changes));
    }

    public function test_it_returns_empty_when_no_param_is_linked(): void
    {
        $def = ['parameters' => ['x' => ['display_type' => 'text']]];

        self::assertSame([], ConfigWriterService::envUpdatesForFile($def, [new ConfigChange('x', 'y')]));
    }
}
