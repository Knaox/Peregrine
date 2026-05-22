<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Tests\Unit\Boost;

use PHPUnit\Framework\TestCase;
use Plugins\EasyConfiguration\Services\Boost\BoostCalculator;

final class BoostCalculatorTest extends TestCase
{
    private BoostCalculator $calc;

    protected function setUp(): void
    {
        $this->calc = new BoostCalculator;
    }

    public function test_it_multiplies_the_baseline(): void
    {
        self::assertSame(30.0, $this->calc->compute(10, 3, null, null));
    }

    public function test_it_caps_at_the_user_max_cap(): void
    {
        self::assertSame(25.0, $this->calc->compute(10, 3, 25, null));
    }

    public function test_it_caps_at_the_template_max(): void
    {
        self::assertSame(20.0, $this->calc->compute(10, 3, null, 20));
    }

    public function test_it_caps_at_the_lower_of_both(): void
    {
        self::assertSame(20.0, $this->calc->compute(10, 3, 25, 20));
    }

    public function test_it_formats_integers_without_decimals(): void
    {
        self::assertSame('30', $this->calc->format(30.0, false));
        self::assertSame('31', $this->calc->format(30.6, false));
    }

    public function test_it_formats_floats_trimming_zeros(): void
    {
        self::assertSame('2.5', $this->calc->format(2.5, true));
        self::assertSame('30', $this->calc->format(30.0, true));
    }
}
