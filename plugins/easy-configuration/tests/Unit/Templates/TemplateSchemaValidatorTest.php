<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Tests\Unit\Templates;

use PHPUnit\Framework\TestCase;
use Plugins\EasyConfiguration\Services\Templates\TemplateSchemaValidator;

final class TemplateSchemaValidatorTest extends TestCase
{
    /** @return array<string, mixed> */
    private function validTemplate(): array
    {
        return [
            'id' => 'minecraft-vanilla',
            'version' => '1.0.0',
            'name' => ['fr' => 'Minecraft', 'en' => 'Minecraft'],
            'target_eggs' => [3, 7],
            'boost' => ['enabled' => true, 'parameter_blacklist' => ['server-port']],
            'files' => [[
                'id' => 'server-properties',
                'path' => 'server.properties',
                'format' => 'properties',
                'enabled' => true,
                'parameters' => [
                    'max-players' => ['display_type' => 'slider', 'config' => ['min' => 1, 'max' => 100], 'label' => ['en' => 'Max']],
                    'pvp' => ['display_type' => 'boolean', 'label' => ['en' => 'PvP']],
                ],
            ]],
        ];
    }

    public function test_a_well_formed_template_has_no_errors(): void
    {
        self::assertSame([], (new TemplateSchemaValidator)->validate($this->validTemplate()));
    }

    public function test_nested_section_parameters_are_accepted(): void
    {
        $template = $this->validTemplate();
        $template['files'][0]['format'] = 'ini';
        $template['files'][0]['parameters'] = [
            'ServerSettings' => [
                'MaxPlayers' => ['display_type' => 'number', 'label' => ['en' => 'Max']],
            ],
        ];

        self::assertSame([], (new TemplateSchemaValidator)->validate($template));
    }

    public function test_it_reports_missing_files(): void
    {
        $template = $this->validTemplate();
        unset($template['files']);

        $errors = (new TemplateSchemaValidator)->validate($template);

        self::assertContains('files: at least one file is required', $errors);
    }

    public function test_it_reports_an_unknown_display_type(): void
    {
        $template = $this->validTemplate();
        $template['files'][0]['parameters']['max-players']['display_type'] = 'wormhole';

        $errors = (new TemplateSchemaValidator)->validate($template);

        self::assertNotEmpty($errors);
        self::assertStringContainsString('display_type', $errors[0]);
    }

    public function test_it_reports_a_non_integer_egg_target(): void
    {
        $template = $this->validTemplate();
        $template['target_eggs'] = ['three'];

        $errors = (new TemplateSchemaValidator)->validate($template);

        self::assertContains('target_eggs: every entry must be an integer egg id', $errors);
    }

    public function test_expanded_by_default_and_section_expanded_flags_are_accepted(): void
    {
        $template = $this->validTemplate();
        $template['files'][0]['expanded_by_default'] = true;
        $template['files'][0]['section_expanded'] = ['ServerSettings' => false];

        self::assertSame([], (new TemplateSchemaValidator)->validate($template));
    }

    public function test_it_reports_a_non_boolean_expanded_by_default(): void
    {
        $template = $this->validTemplate();
        $template['files'][0]['expanded_by_default'] = 'yes';

        $errors = (new TemplateSchemaValidator)->validate($template);

        self::assertContains('files[0].expanded_by_default: must be a boolean', $errors);
    }

    public function test_it_reports_a_non_boolean_section_expanded_entry(): void
    {
        $template = $this->validTemplate();
        $template['files'][0]['section_expanded'] = ['ServerSettings' => 'open'];

        $errors = (new TemplateSchemaValidator)->validate($template);

        self::assertContains('files[0].section_expanded.ServerSettings: must be a boolean', $errors);
    }

    public function test_require_shutdown_flag_is_accepted(): void
    {
        $template = $this->validTemplate();
        $template['require_shutdown'] = false;

        self::assertSame([], (new TemplateSchemaValidator)->validate($template));
    }

    public function test_it_reports_a_non_boolean_require_shutdown(): void
    {
        $template = $this->validTemplate();
        $template['require_shutdown'] = 'always';

        $errors = (new TemplateSchemaValidator)->validate($template);

        self::assertContains('require_shutdown: must be a boolean', $errors);
    }

    public function test_xml_is_an_accepted_format(): void
    {
        $template = $this->validTemplate();
        $template['files'][0]['format'] = 'xml';

        self::assertSame([], (new TemplateSchemaValidator)->validate($template));
    }
}
