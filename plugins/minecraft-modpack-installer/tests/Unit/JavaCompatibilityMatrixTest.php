<?php

declare(strict_types=1);

namespace Plugins\MinecraftModpackInstaller\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Plugins\MinecraftModpackInstaller\Services\JavaCompatibilityMatrix;
use RuntimeException;

/**
 * Unit tests for the static resolution helpers in JavaCompatibilityMatrix.
 *
 * These intentionally exercise the *static* `resolveRequiredJava` and
 * `resolveImageForJava` entry points — they are pure functions, take only
 * primitive arguments, and need no Laravel container, no model, no DB.
 *
 * Run: ./vendor/bin/phpunit -c plugins/minecraft-modpack-installer/phpunit.xml.dist
 */
final class JavaCompatibilityMatrixTest extends TestCase
{
    /**
     * The bundled rule set we ship in `config/java-compatibility.php`.
     * Tests reference this exact list so a regression in the config file
     * shows up here too. Keep in sync with the plugin config.
     *
     * @return list<array<string, mixed>>
     */
    private static function bundledRules(): array
    {
        return [
            ['loader' => 'forge',    'mc_min' => null,     'mc_max' => '1.16.5', 'java' => 8],
            ['loader' => 'forge',    'mc_min' => '1.17',   'mc_max' => '1.17.1', 'java' => 16],
            ['loader' => 'forge',    'mc_min' => '1.18',   'mc_max' => '1.20.4', 'java' => 17],
            ['loader' => 'forge',    'mc_min' => '1.20.5', 'mc_max' => null,     'java' => 21],

            ['loader' => 'neoforge', 'mc_min' => '1.20.1', 'mc_max' => '1.20.4', 'java' => 17],
            ['loader' => 'neoforge', 'mc_min' => '1.20.5', 'mc_max' => null,     'java' => 21],

            ['loader' => 'fabric',   'mc_min' => null,     'mc_max' => '1.16.5', 'java' => 8],
            ['loader' => 'fabric',   'mc_min' => '1.17',   'mc_max' => '1.17.1', 'java' => 16],
            ['loader' => 'fabric',   'mc_min' => '1.18',   'mc_max' => '1.20.4', 'java' => 17],
            ['loader' => 'fabric',   'mc_min' => '1.20.5', 'mc_max' => null,     'java' => 21],

            ['loader' => 'quilt',    'mc_min' => null,     'mc_max' => '1.16.5', 'java' => 8],
            ['loader' => 'quilt',    'mc_min' => '1.17',   'mc_max' => '1.17.1', 'java' => 16],
            ['loader' => 'quilt',    'mc_min' => '1.18',   'mc_max' => '1.20.4', 'java' => 17],
            ['loader' => 'quilt',    'mc_min' => '1.20.5', 'mc_max' => null,     'java' => 21],

            ['loader' => null,       'mc_min' => null,     'mc_max' => '1.16.5', 'java' => 8],
            ['loader' => null,       'mc_min' => '1.17',   'mc_max' => '1.17.1', 'java' => 16],
            ['loader' => null,       'mc_min' => '1.18',   'mc_max' => '1.20.4', 'java' => 17],
            ['loader' => null,       'mc_min' => '1.20.5', 'mc_max' => null,     'java' => 21],
        ];
    }

    /**
     * @return iterable<string, array{string, ?string, int}>
     */
    public static function bundledRuleCases(): iterable
    {
        // ── Forge: critical because Forge ≤ 1.16.5 strictly refuses Java 9+
        yield 'forge 1.7.10 → java 8' => ['1.7.10', 'forge', 8];
        yield 'forge 1.12.2 → java 8' => ['1.12.2', 'forge', 8];
        yield 'forge 1.16.5 → java 8' => ['1.16.5', 'forge', 8];
        yield 'forge 1.17.1 → java 16' => ['1.17.1', 'forge', 16];
        yield 'forge 1.18.2 → java 17' => ['1.18.2', 'forge', 17];
        yield 'forge 1.20.1 → java 17' => ['1.20.1', 'forge', 17];
        yield 'forge 1.20.4 → java 17' => ['1.20.4', 'forge', 17];
        yield 'forge 1.20.6 → java 21' => ['1.20.6', 'forge', 21];
        yield 'forge 1.21 → java 21' => ['1.21', 'forge', 21];

        // ── NeoForge: only exists from 1.20.1+ ; 1.20.5 jumps to Java 21
        yield 'neoforge 1.20.1 → java 17' => ['1.20.1', 'neoforge', 17];
        yield 'neoforge 1.20.4 → java 17' => ['1.20.4', 'neoforge', 17];
        yield 'neoforge 1.20.6 → java 21' => ['1.20.6', 'neoforge', 21];
        yield 'neoforge 1.21 → java 21' => ['1.21', 'neoforge', 21];

        // ── Fabric & Quilt: track Vanilla minimums
        yield 'fabric 1.16.5 → java 8' => ['1.16.5', 'fabric', 8];
        yield 'fabric 1.18.2 → java 17' => ['1.18.2', 'fabric', 17];
        yield 'fabric 1.20.6 → java 21' => ['1.20.6', 'fabric', 21];
        yield 'quilt 1.18.2 → java 17' => ['1.18.2', 'quilt', 17];

        // ── Vanilla / no loader
        yield 'vanilla 1.12.2 → java 8' => ['1.12.2', null, 8];
        yield 'vanilla 1.17.1 → java 16' => ['1.17.1', null, 16];
        yield 'vanilla 1.20.1 → java 17' => ['1.20.1', null, 17];
        yield 'vanilla 1.20.6 → java 21' => ['1.20.6', null, 21];
        yield 'vanilla 1.21 → java 21' => ['1.21', null, 21];

        // ── Casing and whitespace tolerance
        yield 'forge upper-cased → java 17' => ['1.20.1', 'FORGE', 17];
        yield 'forge with spaces → java 17' => ['1.20.1', '  forge  ', 17];

        // ── Unknown loader falls through to vanilla rules
        yield 'unknown loader 1.18 → java 17' => ['1.18', 'mystery-loader', 17];
    }

    #[DataProvider('bundledRuleCases')]
    public function test_resolves_java_for_bundled_rules(string $mc, ?string $loader, int $expected): void
    {
        self::assertSame(
            $expected,
            JavaCompatibilityMatrix::resolveRequiredJava(self::bundledRules(), 17, $mc, $loader),
        );
    }

    public function test_falls_back_to_default_when_no_rule_matches(): void
    {
        // Only-an-impossible-loader rule list — every input falls through.
        $rules = [
            ['loader' => 'glowstone', 'mc_min' => '1.20', 'mc_max' => '1.20', 'java' => 21],
        ];

        self::assertSame(
            17,
            JavaCompatibilityMatrix::resolveRequiredJava($rules, 17, '1.18.2', 'forge'),
        );
    }

    public function test_falls_back_to_default_when_mc_version_unknown_and_no_open_rule(): void
    {
        // Rules are all bounded — null mc can't match any.
        $rules = [
            ['loader' => null, 'mc_min' => '1.18', 'mc_max' => '1.20.4', 'java' => 17],
        ];

        self::assertSame(
            21,
            JavaCompatibilityMatrix::resolveRequiredJava($rules, 21, null, null),
        );
    }

    public function test_open_ended_rule_matches_unknown_mc_version(): void
    {
        $rules = [
            ['loader' => null, 'mc_min' => null, 'mc_max' => null, 'java' => 11],
        ];

        self::assertSame(
            11,
            JavaCompatibilityMatrix::resolveRequiredJava($rules, 17, null, null),
        );
    }

    public function test_first_matching_rule_wins_so_loader_specific_must_come_first(): void
    {
        // Generic vanilla rule listed FIRST — eats the loader-specific one.
        $rulesBad = [
            ['loader' => null,    'mc_min' => null, 'mc_max' => '1.16.5', 'java' => 11],
            ['loader' => 'forge', 'mc_min' => null, 'mc_max' => '1.16.5', 'java' => 8],
        ];
        self::assertSame(
            11,
            JavaCompatibilityMatrix::resolveRequiredJava($rulesBad, 17, '1.16.5', 'forge'),
            'Expected the first-matching rule to win even when wrong, demonstrating ordering matters.',
        );

        // Loader-specific FIRST — correct.
        $rulesGood = [
            ['loader' => 'forge', 'mc_min' => null, 'mc_max' => '1.16.5', 'java' => 8],
            ['loader' => null,    'mc_min' => null, 'mc_max' => '1.16.5', 'java' => 11],
        ];
        self::assertSame(
            8,
            JavaCompatibilityMatrix::resolveRequiredJava($rulesGood, 17, '1.16.5', 'forge'),
        );
    }

    public function test_image_resolution_returns_explicit_mapping(): void
    {
        $images = [
            '8' => 'registry/yolks:8',
            '17' => 'registry/yolks:17',
            '21' => 'registry/yolks:21',
        ];

        self::assertSame('registry/yolks:8', JavaCompatibilityMatrix::resolveImageForJava($images, 8, 17));
        self::assertSame('registry/yolks:17', JavaCompatibilityMatrix::resolveImageForJava($images, 17, 17));
        self::assertSame('registry/yolks:21', JavaCompatibilityMatrix::resolveImageForJava($images, 21, 17));
    }

    public function test_image_resolution_falls_back_to_default_java_image(): void
    {
        $images = [
            '17' => 'registry/yolks:17',
            // Java 11 not mapped explicitly.
        ];

        self::assertSame(
            'registry/yolks:17',
            JavaCompatibilityMatrix::resolveImageForJava($images, 11, 17),
        );
    }

    public function test_image_resolution_throws_when_neither_target_nor_default_mapped(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/No Docker image configured for Java 11/');

        JavaCompatibilityMatrix::resolveImageForJava([], 11, 17);
    }

    public function test_image_resolution_ignores_blank_overrides(): void
    {
        $images = [
            '17' => '',         // intentionally blank — operator typo
            '21' => 'good:21',
        ];

        $this->expectException(RuntimeException::class);
        JavaCompatibilityMatrix::resolveImageForJava($images, 17, 17);
    }
}
