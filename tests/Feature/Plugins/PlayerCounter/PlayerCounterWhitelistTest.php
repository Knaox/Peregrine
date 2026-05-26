<?php

declare(strict_types=1);

namespace Tests\Feature\Plugins\PlayerCounter;

use Plugins\PeregrinePlayerCounter\Settings\PlayerCounterSettings;
use Tests\TestCase;

/**
 * Egg whitelist logic: an empty list shows the counter on every egg (default),
 * a non-empty list restricts it to the listed egg ids. The stored value is
 * normalized to a deduped list of positive ints.
 */
class PlayerCounterWhitelistTest extends TestCase
{
    use ActivatesPlayerCounterPlugin;

    protected function setUp(): void
    {
        $this->bootPlayerCounterPlugin();
        parent::setUp();
    }

    private function settings(array $whitelist): PlayerCounterSettings
    {
        return new PlayerCounterSettings(enabled: true, sidecarUrl: '', sidecarToken: '', eggWhitelist: $whitelist);
    }

    public function test_empty_whitelist_allows_every_egg(): void
    {
        $settings = $this->settings([]);

        $this->assertTrue($settings->allowsEgg(1));
        $this->assertTrue($settings->allowsEgg(999));
        $this->assertTrue($settings->allowsEgg(null));
    }

    public function test_non_empty_whitelist_allows_only_listed_eggs(): void
    {
        $settings = $this->settings([3, 7]);

        $this->assertTrue($settings->allowsEgg(3));
        $this->assertTrue($settings->allowsEgg(7));
        $this->assertFalse($settings->allowsEgg(1));
        $this->assertFalse($settings->allowsEgg(null));
    }

    public function test_normalize_egg_ids_casts_dedupes_and_drops_invalid(): void
    {
        $this->assertSame([3, 7], PlayerCounterSettings::normalizeEggIds(['3', 7, '3', 0, -1, 'x']));
        $this->assertSame([], PlayerCounterSettings::normalizeEggIds(null));
        $this->assertSame([5], PlayerCounterSettings::normalizeEggIds('5'));
    }
}
