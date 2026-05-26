<?php

declare(strict_types=1);

namespace Plugins\PeregrinePlayerCounter\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Plugins\PeregrinePlayerCounter\Services\QueryPortStrategy;
use Plugins\PeregrinePlayerCounter\Tests\TestCase;

class QueryPortStrategyTest extends TestCase
{
    private QueryPortStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new QueryPortStrategy;
    }

    #[Test]
    public function same_port_games_query_the_game_port_and_need_nothing(): void
    {
        $plan = $this->strategy->plan(['mode' => 'same'], false, ['ip' => '1.2.3.4', 'port' => 28015], [28015], []);

        $this->assertSame(28015, $plan['send_port']);
        $this->assertTrue($plan['reachable']);
        $this->assertSame('none', $plan['kind']);
    }

    #[Test]
    public function offset_games_send_the_game_port_and_require_the_adjacent_allocation(): void
    {
        // Valheim: game 2456, query 2457 — GameDig adds the +1 itself, so we send
        // the game port, but the adjacent port must be allocated to be reachable.
        $unreachable = $this->strategy->plan(['mode' => 'offset', 'value' => 1], false, ['ip' => 'x', 'port' => 2456], [2456], []);
        $this->assertSame(2456, $unreachable['send_port']);
        $this->assertFalse($unreachable['reachable']);
        $this->assertSame('adjacent', $unreachable['kind']);
        $this->assertSame(1, $unreachable['offset']);

        $reachable = $this->strategy->plan(['mode' => 'offset', 'value' => 1], false, ['ip' => 'x', 'port' => 2456], [2456, 2457], []);
        $this->assertTrue($reachable['reachable']);
    }

    #[Test]
    public function variable_games_read_the_query_port_from_a_startup_variable(): void
    {
        // Sons of the Forest exposes a configurable QUERY_PORT — when it points
        // at an allocated port the game is reachable; otherwise it must resolve.
        $vars = ['QUERY_PORT' => '27016'];

        $reachable = $this->strategy->plan(['mode' => 'fixed', 'value' => 27016], false, ['ip' => 'x', 'port' => 8766], [8766, 27016], $vars);
        $this->assertSame(27016, $reachable['send_port']);
        $this->assertTrue($reachable['reachable']);
        $this->assertSame('var', $reachable['kind']);
        $this->assertSame('QUERY_PORT', $reachable['env']);

        $unreachable = $this->strategy->plan(['mode' => 'fixed', 'value' => 27016], false, ['ip' => 'x', 'port' => 8766], [8766], $vars);
        $this->assertFalse($unreachable['reachable']);
        $this->assertSame('var', $unreachable['kind']);
    }

    #[Test]
    public function fixed_port_game_with_an_empty_query_variable_must_resolve_not_noop(): void
    {
        // Regression: Sons of the Forest exposes QUERY_PORT but its server_value
        // is empty (only a local default, never tied to an allocation). The
        // variable's mere presence means the port is reconfigurable, so the plan
        // must mark it NOT reachable (kind=var) and target QUERY_PORT — the old
        // code wrongly returned kind=none/reachable and the resolver did nothing.
        $plan = $this->strategy->plan(['mode' => 'fixed', 'value' => 27016], false, ['ip' => 'x', 'port' => 8766], [8766], ['QUERY_PORT' => '']);

        $this->assertSame('var', $plan['kind']);
        $this->assertSame('QUERY_PORT', $plan['env']);
        $this->assertFalse($plan['reachable']);
        $this->assertSame(27016, $plan['send_port']); // fixed port queried until resolved
    }

    #[Test]
    public function it_fuzzy_matches_any_query_port_variable_name(): void
    {
        // A variable not in the configured candidate list still matches if its
        // name mentions both 'query' and 'port'.
        $plan = $this->strategy->plan(['mode' => 'fixed', 'value' => 27016], false, ['ip' => 'x', 'port' => 8766], [8766], ['STEAM_QUERYPORT' => '40000']);

        $this->assertSame('var', $plan['kind']);
        $this->assertSame('STEAM_QUERYPORT', $plan['env']);
        $this->assertFalse($plan['reachable']); // 40000 not allocated
    }

    #[Test]
    public function fixed_port_with_no_redirectable_variable_is_unreachable_not_reachable(): void
    {
        $plan = $this->strategy->plan(['mode' => 'fixed', 'value' => 27016], false, ['ip' => 'x', 'port' => 8766], [8766], ['UNRELATED' => 'x']);

        $this->assertSame('unreachable', $plan['kind']);
        $this->assertFalse($plan['reachable']);
        $this->assertSame(27016, $plan['send_port']);
    }

    #[Test]
    public function rcon_games_always_resolve_via_the_rcon_port_variable(): void
    {
        $vars = ['RCON_PORT' => '27020'];

        $plan = $this->strategy->plan(['mode' => 'same'], true, ['ip' => 'x', 'port' => 7777], [7777], $vars);

        $this->assertSame('var', $plan['kind']);
        $this->assertSame('RCON_PORT', $plan['env']);
        $this->assertSame(27020, $plan['send_port']);
        $this->assertFalse($plan['reachable']); // 27020 not allocated yet
    }

    #[Test]
    public function send_port_is_cheap_for_same_and_offset_without_startup_vars(): void
    {
        $this->assertSame(28015, $this->strategy->sendPort(['mode' => 'same'], false, 28015, []));
        $this->assertSame(2456, $this->strategy->sendPort(['mode' => 'offset', 'value' => 1], false, 2456, []));

        $this->assertFalse($this->strategy->needsStartupVars(['mode' => 'same'], false));
        $this->assertFalse($this->strategy->needsStartupVars(['mode' => 'offset', 'value' => 1], false));
        $this->assertTrue($this->strategy->needsStartupVars(['mode' => 'var', 'env' => 'QUERY_PORT'], false));
        $this->assertTrue($this->strategy->needsStartupVars(['mode' => 'same'], true));
    }
}
