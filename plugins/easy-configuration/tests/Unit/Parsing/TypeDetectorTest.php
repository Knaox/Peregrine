<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Tests\Unit\Parsing;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Plugins\EasyConfiguration\Services\Parsing\TypeDetector;

final class TypeDetectorTest extends TestCase
{
    #[DataProvider('cases')]
    public function test_it_detects_the_expected_type(string $value, string $expected): void
    {
        self::assertSame($expected, (new TypeDetector)->detect($value));
    }

    /** @return list<array{string, string}> */
    public static function cases(): array
    {
        return [
            ['true', 'boolean'],
            ['false', 'boolean'],
            ['FALSE', 'boolean'],
            ['  true  ', 'boolean'],
            ['42', 'number'],
            ['3.14', 'number'],
            ['-7', 'number'],
            ['1', 'number'],      // ambiguous toggle stays a number unless template opts in
            ['0', 'number'],
            ['yes', 'text'],      // not auto-promoted to boolean
            ['on', 'text'],
            ['hello world', 'text'],
            ['', 'text'],
        ];
    }
}
