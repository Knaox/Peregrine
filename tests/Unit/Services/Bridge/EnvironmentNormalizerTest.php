<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Bridge;

use App\Services\Bridge\EnvironmentNormalizer;
use PHPUnit\Framework\TestCase;

class EnvironmentNormalizerTest extends TestCase
{
    private function definition(string $key, string $default, string $rules): array
    {
        return ['env_variable' => $key, 'default' => $default, 'rules' => $rules];
    }

    public function test_boolean_spellings_are_coerced_to_laravel_booleans(): void
    {
        $result = (new EnvironmentNormalizer)->normalize(
            ['FORCE_RESPAWN' => 'true', 'EAC' => 'False', 'CROSSPLAY' => 'on', 'PVP' => '1'],
            [
                $this->definition('FORCE_RESPAWN', 'true', 'required|boolean'),
                $this->definition('EAC', 'false', 'required|bool'),
                $this->definition('CROSSPLAY', '', 'nullable|boolean'),
                $this->definition('PVP', '0', 'required|boolean'),
            ],
        );

        $this->assertSame(
            ['FORCE_RESPAWN' => '1', 'EAC' => '0', 'CROSSPLAY' => '1', 'PVP' => '1'],
            $result['environment'],
        );
        $this->assertSame([], $result['unfillable']);
    }

    public function test_an_empty_boolean_falls_back_to_its_default_then_to_zero(): void
    {
        $result = (new EnvironmentNormalizer)->normalize(
            ['A' => '', 'B' => ''],
            [
                $this->definition('A', 'true', 'required|boolean'),
                $this->definition('B', '', 'required|boolean'),
            ],
        );

        $this->assertSame(['A' => '1', 'B' => '0'], $result['environment']);
    }

    public function test_in_lists_are_recanonicalised_case_insensitively(): void
    {
        $result = (new EnvironmentNormalizer)->normalize(
            ['RESPAWN' => 'true', 'MODE' => 'HARDCORE', 'KEEP' => 'False'],
            [
                $this->definition('RESPAWN', 'False', 'required|string|in:True,False'),
                $this->definition('MODE', 'normal', 'required|in:normal,hardcore'),
                $this->definition('KEEP', 'True', 'required|in:True,False'),
            ],
        );

        $this->assertSame(
            ['RESPAWN' => 'True', 'MODE' => 'hardcore', 'KEEP' => 'False'],
            $result['environment'],
        );
    }

    public function test_an_out_of_list_value_falls_back_to_the_default_then_first_option(): void
    {
        $result = (new EnvironmentNormalizer)->normalize(
            ['A' => 'bogus', 'B' => ''],
            [
                $this->definition('A', 'False', 'required|in:True,False'),
                $this->definition('B', 'nope', 'required|in:x,y'),
            ],
        );

        $this->assertSame(['A' => 'False', 'B' => 'x'], $result['environment']);
    }

    public function test_plain_variables_pass_through_and_empty_required_ones_are_reported(): void
    {
        $result = (new EnvironmentNormalizer)->normalize(
            ['NAME' => 'WildHunt', 'APPID' => '', 'NOTE' => '', 'EXTRA' => 'kept'],
            [
                $this->definition('NAME', 'srv', 'required|string|max:60'),
                $this->definition('APPID', '', 'required|numeric'),
                $this->definition('NOTE', '', 'nullable|string'),
            ],
        );

        $this->assertSame(
            ['NAME' => 'WildHunt', 'APPID' => '', 'NOTE' => '', 'EXTRA' => 'kept'],
            $result['environment'],
        );
        $this->assertSame(['APPID'], $result['unfillable']);
    }

    public function test_a_regex_rule_with_pipes_does_not_confuse_in_extraction(): void
    {
        $result = (new EnvironmentNormalizer)->normalize(
            ['V' => 'latest'],
            [$this->definition('V', 'latest', 'required|regex:/^(latest|1\.\d+)$/|string')],
        );

        $this->assertSame('latest', $result['environment']['V']);
    }
}
