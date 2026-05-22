<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Services\Config;

/**
 * Server-side value validation against a template parameter definition. This is
 * the defence-in-depth the beta editor lacked: the frontend soft-reverts bad
 * input, but the backend re-checks every value before it touches a file.
 * Returns a short error message, or null when the value is acceptable.
 */
final class ConfigValueValidator
{
    /** @param array<string, mixed> $def */
    public function validate(array $def, string $value): ?string
    {
        $type = (string) ($def['display_type'] ?? 'text');
        $config = is_array($def['config'] ?? null) ? $def['config'] : [];

        return match ($type) {
            'number', 'slider' => $this->number($config, $value),
            'select' => $this->select($config, $value),
            'multiselect' => $this->multiselect($config, $value),
            'boolean' => $this->boolean($config, $value),
            'text' => $this->text($config, $value),
            'textarea' => $this->maxLength($config, $value),
            'color' => $this->color($config, $value),
            default => null,
        };
    }

    /** @param array<string, mixed> $config */
    private function number(array $config, string $value): ?string
    {
        if (! is_numeric($value)) {
            return 'must be a number';
        }
        $number = (float) $value;
        if (isset($config['min']) && is_numeric($config['min']) && $number < (float) $config['min']) {
            return 'below the minimum of '.$config['min'];
        }
        if (isset($config['max']) && is_numeric($config['max']) && $number > (float) $config['max']) {
            return 'above the maximum of '.$config['max'];
        }
        if (empty($config['float']) && str_contains($value, '.')) {
            return 'must be a whole number';
        }

        return null;
    }

    /** @param array<string, mixed> $config */
    private function select(array $config, string $value): ?string
    {
        return in_array($value, $this->optionValues($config), true) ? null : 'is not one of the allowed options';
    }

    /** @param array<string, mixed> $config */
    private function multiselect(array $config, string $value): ?string
    {
        $separator = is_string($config['separator'] ?? null) && $config['separator'] !== '' ? $config['separator'] : ',';
        $allowed = $this->optionValues($config);
        if ($allowed === []) {
            return null;
        }

        foreach (explode($separator, $value) as $item) {
            $item = trim($item);
            if ($item !== '' && ! in_array($item, $allowed, true)) {
                return "contains an invalid option: {$item}";
            }
        }

        return null;
    }

    /** @param array<string, mixed> $config */
    private function boolean(array $config, string $value): ?string
    {
        $true = (string) ($config['true_value'] ?? 'true');
        $false = (string) ($config['false_value'] ?? 'false');

        return in_array($value, [$true, $false], true) ? null : "must be \"{$true}\" or \"{$false}\"";
    }

    /** @param array<string, mixed> $config */
    private function text(array $config, string $value): ?string
    {
        if (($error = $this->maxLength($config, $value)) !== null) {
            return $error;
        }
        if (isset($config['regex']) && is_string($config['regex']) && $config['regex'] !== '') {
            $pattern = '/'.str_replace('/', '\/', $config['regex']).'/';
            if (@preg_match($pattern, $value) !== 1) {
                return 'does not match the required format';
            }
        }

        return null;
    }

    /** @param array<string, mixed> $config */
    private function maxLength(array $config, string $value): ?string
    {
        if (isset($config['max_length']) && is_numeric($config['max_length']) && mb_strlen($value) > (int) $config['max_length']) {
            return 'is longer than '.$config['max_length'].' characters';
        }

        return null;
    }

    /** @param array<string, mixed> $config */
    private function color(array $config, string $value): ?string
    {
        $format = (string) ($config['format'] ?? 'hex');
        if ($format === 'hex') {
            return preg_match('/^#?[0-9a-fA-F]{6}$/', $value) === 1 ? null : 'must be a hex colour';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    private function optionValues(array $config): array
    {
        $options = $config['options'] ?? [];
        if (! is_array($options)) {
            return [];
        }

        $values = [];
        foreach ($options as $option) {
            if (is_array($option) && isset($option['value'])) {
                $values[] = (string) $option['value'];
            }
        }

        return $values;
    }
}
