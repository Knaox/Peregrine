<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Tests\Unit\Config;

use PHPUnit\Framework\TestCase;
use Plugins\EasyConfiguration\Services\Config\ConfigValueValidator;

final class ConfigValueValidatorTest extends TestCase
{
    private ConfigValueValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new ConfigValueValidator;
    }

    public function test_number_range_and_type(): void
    {
        $def = ['display_type' => 'number', 'config' => ['min' => 1, 'max' => 100]];

        self::assertNull($this->validator->validate($def, '50'));
        // Soft min/max: a non-linked number may be typed below its min OR above
        // its max (manual override). Only the type check stays hard.
        self::assertNull($this->validator->validate($def, '200'));
        self::assertNull($this->validator->validate($def, '0'));
        self::assertNotNull($this->validator->validate(['display_type' => 'number'], 'abc'));
    }

    public function test_env_linked_number_is_hard_capped_at_min_and_max(): void
    {
        // An env-linked param bounds the synced Pelican variable, so BOTH `min`
        // and `max` stay hard — manual override below min or above max is rejected.
        $linked = ['display_type' => 'number', 'env_var' => 'MAX_PLAYERS', 'config' => ['min' => 1, 'max' => 100]];

        self::assertNull($this->validator->validate($linked, '100'));
        self::assertNotNull($this->validator->validate($linked, '200'));
        self::assertNotNull($this->validator->validate($linked, '0'));
    }

    public function test_whole_number_unless_float_is_allowed(): void
    {
        self::assertNotNull($this->validator->validate(['display_type' => 'number'], '3.5'));
        self::assertNull($this->validator->validate(['display_type' => 'number', 'config' => ['float' => true]], '3.5'));
    }

    public function test_select_must_be_an_allowed_option(): void
    {
        $def = ['display_type' => 'select', 'config' => ['options' => [['value' => 'easy'], ['value' => 'hard']]]];

        self::assertNull($this->validator->validate($def, 'easy'));
        self::assertNotNull($this->validator->validate($def, 'peaceful'));
    }

    public function test_boolean_honours_custom_true_false_values(): void
    {
        $def = ['display_type' => 'boolean', 'config' => ['true_value' => '1', 'false_value' => '0']];

        self::assertNull($this->validator->validate($def, '1'));
        self::assertNull($this->validator->validate($def, '0'));
        self::assertNotNull($this->validator->validate($def, 'true'));
    }

    public function test_text_max_length_and_regex(): void
    {
        self::assertNotNull($this->validator->validate(['display_type' => 'text', 'config' => ['max_length' => 3]], 'toolong'));
        self::assertNull($this->validator->validate(['display_type' => 'text', 'config' => ['regex' => '^[a-z]+$']], 'abc'));
        self::assertNotNull($this->validator->validate(['display_type' => 'text', 'config' => ['regex' => '^[a-z]+$']], 'ABC'));
    }

    public function test_color_hex(): void
    {
        self::assertNull($this->validator->validate(['display_type' => 'color', 'config' => ['format' => 'hex']], '#ffaa00'));
        self::assertNotNull($this->validator->validate(['display_type' => 'color', 'config' => ['format' => 'hex']], 'red'));
    }
}
