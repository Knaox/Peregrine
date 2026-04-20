<?php

namespace App\Support;

/**
 * Named layout presets for the server-detail sidebar. Each preset bundles a
 * position (left/top), a style (default/compact/pills) and display flags so
 * admins can pick a complete visual identity in one click — unlike the legacy
 * position+style radios which only covered 2×3=6 combinations with no curation.
 *
 * The entry list itself (order/icons/enabled) lives in sidebar_server_config
 * and is NOT overwritten by a preset — admins keep control of route visibility.
 */
final class SidebarPresets
{
    /**
     * @return array<string, array{label: string, description: string, values: array<string, mixed>}>
     */
    public static function all(): array
    {
        return [
            'classic' => [
                'label' => 'Classic',
                'description' => 'Left sidebar with full labels and a left-edge accent on the active route.',
                'values' => [
                    'position' => 'left',
                    'style' => 'default',
                    'show_server_status' => true,
                    'show_server_name' => true,
                ],
            ],
            'rail' => [
                'label' => 'Rail (icon only)',
                'description' => 'Narrow vertical rail on the left — icon-only entries with hover tooltips. Great on large screens when you want more room for the content.',
                'values' => [
                    'position' => 'left',
                    'style' => 'compact',
                    'show_server_status' => true,
                    'show_server_name' => false,
                ],
            ],
            'pills' => [
                'label' => 'Pills',
                'description' => 'Left sidebar with fully rounded pill buttons. Softer, modern vibe — each entry floats like a chip.',
                'values' => [
                    'position' => 'left',
                    'style' => 'pills',
                    'show_server_status' => true,
                    'show_server_name' => true,
                ],
            ],
            'tabs' => [
                'label' => 'Top tabs',
                'description' => 'Horizontal tab bar above the content. Frees vertical space on the left — ideal for ultrawide monitors.',
                'values' => [
                    'position' => 'top',
                    'style' => 'default',
                    'show_server_status' => false,
                    'show_server_name' => false,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function get(string $id): array
    {
        return self::all()[$id]['values'] ?? self::all()['classic']['values'];
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $map = [];
        foreach (self::all() as $id => $preset) {
            $map[$id] = $preset['label'];
        }
        $map['custom'] = 'Custom';

        return $map;
    }

    /**
     * Short helper text shown next to the dropdown.
     *
     * @return array<string, string>
     */
    public static function descriptions(): array
    {
        $map = [];
        foreach (self::all() as $id => $preset) {
            $map[$id] = $preset['description'];
        }

        return $map;
    }
}
