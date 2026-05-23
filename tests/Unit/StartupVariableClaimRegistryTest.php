<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Server;
use App\Services\Plugin\StartupVariableClaimRegistry;
use PHPUnit\Framework\TestCase;

final class StartupVariableClaimRegistryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        StartupVariableClaimRegistry::getInstance()->reset();
    }

    protected function tearDown(): void
    {
        StartupVariableClaimRegistry::getInstance()->reset();
        parent::tearDown();
    }

    public function test_it_returns_nothing_when_no_claimer_is_registered(): void
    {
        self::assertSame([], StartupVariableClaimRegistry::getInstance()->claimedFor(new Server));
    }

    public function test_it_merges_and_dedupes_claimed_names_across_claimers(): void
    {
        $registry = StartupVariableClaimRegistry::getInstance();
        $registry->register('a', static fn (Server $s): array => ['MAX_PLAYERS', 'SERVER_NAME']);
        $registry->register('b', static fn (Server $s): array => ['SERVER_NAME', 'DIFFICULTY', '']);

        $claimed = $registry->claimedFor(new Server);

        sort($claimed);
        self::assertSame(['DIFFICULTY', 'MAX_PLAYERS', 'SERVER_NAME'], $claimed);
    }

    public function test_reset_clears_registered_claimers(): void
    {
        $registry = StartupVariableClaimRegistry::getInstance();
        $registry->register('a', static fn (Server $s): array => ['X']);
        $registry->reset();

        self::assertSame([], $registry->claimedFor(new Server));
    }
}
