<?php

namespace App\Support;

/**
 * Four radically different layouts for the server-detail navigation.
 * Each preset changes the *position* of the nav on the screen, not just
 * its visual accent:
 *
 *   - classic → full left panel with labels
 *   - rail    → narrow icon-only column pinned left (no labels)
 *   - tabs    → horizontal tab bar above the content (no vertical sidebar)
 *   - dock    → floating glass dock at the bottom-center of the viewport
 *
 * The entry list (order/icons/enabled) lives in sidebar_server_config and
 * is NOT overwritten by a preset — admins keep control of route visibility.
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
                'label' => 'Classic sidebar',
                'description' => 'Full-width left panel with labels and a left-edge accent on the active route.',
                'values' => [
                    'position' => 'left',
                    'style' => 'default',
                    'show_server_status' => true,
                    'show_server_name' => true,
                ],
            ],
            'rail' => [
                'label' => 'Floating rail',
                'description' => 'Narrow 64px column on the left — icon-only with hover tooltips. Gives the content more breathing room.',
                'values' => [
                    'position' => 'left',
                    'style' => 'compact',
                    'show_server_status' => true,
                    'show_server_name' => false,
                ],
            ],
            'tabs' => [
                'label' => 'Top tab bar',
                'description' => 'Horizontal tabs above the content. No vertical sidebar — ideal for ultrawide monitors.',
                'values' => [
                    'position' => 'top',
                    'style' => 'default',
                    'show_server_status' => false,
                    'show_server_name' => false,
                ],
            ],
            'dock' => [
                'label' => 'Floating dock',
                'description' => 'macOS-style glass dock floating at the bottom-center. The content takes the whole screen; the dock follows you while scrolling.',
                'values' => [
                    'position' => 'dock',
                    'style' => 'pills',
                    'show_server_status' => true,
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
