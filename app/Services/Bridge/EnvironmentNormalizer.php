<?php

declare(strict_types=1);

namespace App\Services\Bridge;

/**
 * Last pass over the `environment` map before it is sent to Pelican's server
 * creation endpoint: coerce each value into the shape its egg-variable RULES
 * will accept. Pelican validates every variable with its Laravel rules, and
 * two very common egg shapes 422 the whole provisioning otherwise:
 *
 *  - `boolean` rules only accept 0/1/"0"/"1"/true/false — an egg whose
 *    default (or a mapped static value) is the literal string "true"/"false"
 *    is rejected with "The <name> variable field must be true or false".
 *  - `in:A,B` lists are case-sensitive — "true" against `in:True,False`
 *    is rejected even though a human reads them as equal.
 *
 * Values that cannot be fixed automatically (a required variable with an
 * empty default and no usable rule) are reported back so the caller can log
 * a clear diagnostic instead of a truncated Pelican 422.
 */
class EnvironmentNormalizer
{
    private const TRUTHY = ['1', 'true', 'yes', 'on', 'enabled'];

    private const FALSY = ['0', 'false', 'no', 'off', 'disabled'];

    /**
     * @param  array<string, scalar>  $environment
     * @param  list<array{env_variable: string, default: string, rules: string}>  $definitions
     * @return array{environment: array<string, scalar>, unfillable: list<string>}
     */
    public function normalize(array $environment, array $definitions): array
    {
        $unfillable = [];

        foreach ($definitions as $definition) {
            $key = $definition['env_variable'];
            if (! array_key_exists($key, $environment)) {
                continue;
            }

            $rules = strtolower($definition['rules']);
            $value = trim((string) $environment[$key]);

            if (preg_match('/\bbool(ean)?\b/', $rules) === 1) {
                $environment[$key] = $this->toBoolean($value, $definition['default']);

                continue;
            }

            $options = $this->inOptions($definition['rules']);
            if ($options !== []) {
                $environment[$key] = $this->toOption($value, $definition['default'], $options);

                continue;
            }

            if ($value === '' && str_contains($rules, 'required') && ! str_contains($rules, 'nullable')) {
                $unfillable[] = $key;
            }
        }

        return ['environment' => $environment, 'unfillable' => $unfillable];
    }

    /** Coerce any human boolean spelling into the '1'/'0' Laravel accepts. */
    private function toBoolean(string $value, string $default): string
    {
        foreach ([$value, trim($default)] as $candidate) {
            $lower = strtolower($candidate);
            if (in_array($lower, self::TRUTHY, true)) {
                return '1';
            }
            if (in_array($lower, self::FALSY, true)) {
                return '0';
            }
        }

        return '0';
    }

    /**
     * Keep an exact in-list match; otherwise re-canonicalise case-insensitively
     * ("true" → "True" for `in:True,False`), fall back to the default when it
     * is a member, else to the first option — never ship a value the rule is
     * guaranteed to reject.
     *
     * @param  list<string>  $options
     */
    private function toOption(string $value, string $default, array $options): string
    {
        if (in_array($value, $options, true)) {
            return $value;
        }

        foreach ($options as $option) {
            if (strcasecmp($option, $value) === 0) {
                return $option;
            }
        }

        $default = trim($default);
        if (in_array($default, $options, true)) {
            return $default;
        }
        foreach ($options as $option) {
            if (strcasecmp($option, $default) === 0) {
                return $option;
            }
        }

        return $options[0];
    }

    /**
     * Extract the values of an `in:` rule. Rules may arrive as a pipe string;
     * regex rules are ignored (an `in:` never legitimately contains a pipe).
     *
     * @return list<string>
     */
    private function inOptions(string $rules): array
    {
        if (preg_match('/(?:^|\|)in:([^|]+)/i', $rules, $match) !== 1) {
            return [];
        }

        $options = array_map(trim(...), explode(',', $match[1]));

        return array_values(array_filter($options, static fn (string $option): bool => $option !== ''));
    }
}
