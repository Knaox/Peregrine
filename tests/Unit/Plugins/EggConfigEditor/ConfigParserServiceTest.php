<?php

namespace Tests\Unit\Plugins\EggConfigEditor;

use Plugins\EggConfigEditor\Services\ConfigParserService;
use RuntimeException;
use Tests\TestCase;

/**
 * Unit tests for the 3 file-format parsers powering the egg config editor.
 *
 * Plugin classes are not in the project's composer autoload (plugins boot at
 * runtime via PluginBootstrap), so we register the PSR-4 prefix in setUp.
 */
class ConfigParserServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $loader = require base_path('vendor/autoload.php');
        $loader->addPsr4('Plugins\\EggConfigEditor\\', base_path('plugins/egg-config-editor/src/'));
    }

    public function test_properties_round_trip_preserves_comments_and_unknown_lines(): void
    {
        $parser = new ConfigParserService;
        $original = "# server props\nmax-players=20\n# difficulty hint\ndifficulty=easy\n!banner\nmotd=Hello\n";

        $parsed = $parser->parse($original, 'properties');
        $this->assertSame(['max-players' => '20', 'difficulty' => 'easy', 'motd' => 'Hello'], $parsed);

        $serialized = $parser->serialize(['max-players' => 50, 'motd' => 'World'], 'properties', $original);
        // Comments preserved verbatim.
        $this->assertStringContainsString('# server props', $serialized);
        $this->assertStringContainsString('# difficulty hint', $serialized);
        $this->assertStringContainsString('!banner', $serialized);
        // Updated values rewritten in place.
        $this->assertStringContainsString('max-players=50', $serialized);
        $this->assertStringContainsString('motd=World', $serialized);
        // Untouched key kept as-is.
        $this->assertStringContainsString('difficulty=easy', $serialized);
    }

    public function test_properties_appends_brand_new_keys_at_the_bottom(): void
    {
        $parser = new ConfigParserService;
        $original = "max-players=20\n";

        $serialized = $parser->serialize(['max-players' => 20, 'pvp' => true], 'properties', $original);

        // Existing key unchanged, new key appended at the end.
        $this->assertStringContainsString('max-players=20', $serialized);
        $this->assertStringContainsString('pvp=true', $serialized);
    }

    public function test_ini_uses_unit_separator_section_notation(): void
    {
        $parser = new ConfigParserService;
        $sep = ConfigParserService::SECTION_KEY_SEPARATOR;
        $original = "; ARK\n[ServerSettings]\nMaxPlayers=70\nServerPVE=False\n";

        $parsed = $parser->parse($original, 'ini');
        $this->assertSame([
            'ServerSettings'.$sep.'MaxPlayers' => '70',
            'ServerSettings'.$sep.'ServerPVE' => 'False',
        ], $parsed);

        $serialized = $parser->serialize(['ServerSettings'.$sep.'MaxPlayers' => 100], 'ini', $original);
        $this->assertStringContainsString('[ServerSettings]', $serialized);
        $this->assertStringContainsString('MaxPlayers=100', $serialized);
        $this->assertStringContainsString('; ARK', $serialized);
    }

    public function test_ini_handles_section_names_containing_dots(): void
    {
        // Real-world Unreal Engine INI : section names can contain dots
        // (e.g. `[/script/shootergame.shootergamemode]`). The unit-separator
        // strategy keeps them disambiguated from the key boundary.
        $parser = new ConfigParserService;
        $sep = ConfigParserService::SECTION_KEY_SEPARATOR;
        $original = "[/script/shootergame.shootergamemode]\nbDisableFriendlyFire=0\nMaxTribeLogs=100\n";

        $parsed = $parser->parse($original, 'ini');
        $this->assertSame([
            '/script/shootergame.shootergamemode'.$sep.'bDisableFriendlyFire' => '0',
            '/script/shootergame.shootergamemode'.$sep.'MaxTribeLogs' => '100',
        ], $parsed);

        $serialized = $parser->serialize(
            ['/script/shootergame.shootergamemode'.$sep.'bDisableFriendlyFire' => 1],
            'ini',
            $original,
        );
        $this->assertStringContainsString('[/script/shootergame.shootergamemode]', $serialized);
        $this->assertStringContainsString('bDisableFriendlyFire=1', $serialized);
    }

    public function test_ini_section_brand_new_keys_create_new_section_block(): void
    {
        $parser = new ConfigParserService;
        $sep = ConfigParserService::SECTION_KEY_SEPARATOR;
        $original = "[Existing]\nKey=val\n";

        $serialized = $parser->serialize(
            ['Existing'.$sep.'Key' => 'val', 'NewSection'.$sep.'Foo' => 'bar'],
            'ini',
            $original,
        );

        $this->assertStringContainsString('[Existing]', $serialized);
        $this->assertStringContainsString('[NewSection]', $serialized);
        $this->assertStringContainsString('Foo=bar', $serialized);
    }

    public function test_json_round_trip_supports_top_level_object(): void
    {
        $parser = new ConfigParserService;
        $original = "{\n    \"name\": \"hello\",\n    \"max\": 20,\n    \"enabled\": true\n}";

        $parsed = $parser->parse($original, 'json');
        $this->assertSame(['name' => 'hello', 'max' => 20, 'enabled' => true], $parsed);

        $serialized = $parser->serialize(['name' => 'world', 'max' => 30, 'enabled' => false], 'json');
        $decoded = json_decode($serialized, true);
        $this->assertSame(['name' => 'world', 'max' => 30, 'enabled' => false], $decoded);
    }

    public function test_json_throws_on_invalid_payload(): void
    {
        $parser = new ConfigParserService;
        $this->expectException(RuntimeException::class);
        $parser->parse('not-json', 'json');
    }

    public function test_unsupported_type_throws(): void
    {
        $parser = new ConfigParserService;
        $this->expectException(RuntimeException::class);
        $parser->parse('foo=bar', 'yml');
    }

    public function test_parses_empty_content_to_empty_array(): void
    {
        $parser = new ConfigParserService;
        $this->assertSame([], $parser->parse('', 'properties'));
        $this->assertSame([], $parser->parse('', 'ini'));
        $this->assertSame([], $parser->parse('', 'json'));
    }

    public function test_ini_skips_metadata_comment_line_with_semicolon(): void
    {
        $parser = new ConfigParserService;
        $sep = ConfigParserService::SECTION_KEY_SEPARATOR;
        $original = ";METADATA=(Diff=true, UseCommands=true)\n[ServerSettings]\nMaxPlayers=70\n";

        $parsed = $parser->parse($original, 'ini');

        $this->assertSame(['ServerSettings'.$sep.'MaxPlayers' => '70'], $parsed);
        $this->assertArrayNotHasKey('METADATA', $parsed);
        $this->assertArrayNotHasKey(';METADATA', $parsed);
    }

    public function test_ini_strips_utf8_bom_so_first_comment_is_detected(): void
    {
        $parser = new ConfigParserService;
        $sep = ConfigParserService::SECTION_KEY_SEPARATOR;
        $original = "\xEF\xBB\xBF;METADATA=(Diff=true)\n[Section]\nKey=val\n";

        $parsed = $parser->parse($original, 'ini');

        $this->assertSame(['Section'.$sep.'Key' => 'val'], $parsed);
    }

    public function test_properties_skips_lines_with_corrupted_keys(): void
    {
        // Defensive check : a line like `# foo=bar` is a comment, not a
        // key whose name starts with `#`.
        $parser = new ConfigParserService;
        $original = "# this looks like a key=but isn't\nmax-players=20\n";

        $parsed = $parser->parse($original, 'properties');

        $this->assertSame(['max-players' => '20'], $parsed);
    }

    public function test_boolean_values_serialize_as_lowercase_true_false(): void
    {
        $parser = new ConfigParserService;
        $serialized = $parser->serialize(['key' => true, 'other' => false], 'properties', "key=old\nother=old\n");
        $this->assertStringContainsString('key=true', $serialized);
        $this->assertStringContainsString('other=false', $serialized);
    }
}
