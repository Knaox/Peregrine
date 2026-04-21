<?php

namespace App\Support;

use App\Support\ThemePresets\Amber;
use App\Support\ThemePresets\Crimson;
use App\Support\ThemePresets\Emerald;
use App\Support\ThemePresets\Indigo;
use App\Support\ThemePresets\Orange;
use App\Support\ThemePresets\Slate;
use App\Support\ThemePresets\Violet;

/**
 * Named bundles of theme_* settings the admin can apply in one click.
 *
 * Public API preserved for backwards compatibility — `get()`, `all()` and
 * `options()` are unchanged. Each preset now lives in its own file under
 * `app/Support/ThemePresets/{Orange,Amber,...}.php` with paired dark/light
 * variants validated against WCAG AA/AAA.
 */
final class ThemePresets
{
    /**
     * @return array<string, array{label: string, dark: array<string, string>, light: array<string, string>}>
     */
    public static function all(): array
    {
        return [
            'orange'  => ['label' => Orange::label(),  'dark' => Orange::dark(),  'light' => Orange::light()],
            'amber'   => ['label' => Amber::label(),   'dark' => Amber::dark(),   'light' => Amber::light()],
            'crimson' => ['label' => Crimson::label(), 'dark' => Crimson::dark(), 'light' => Crimson::light()],
            'emerald' => ['label' => Emerald::label(), 'dark' => Emerald::dark(), 'light' => Emerald::light()],
            'indigo'  => ['label' => Indigo::label(),  'dark' => Indigo::dark(),  'light' => Indigo::light()],
            'violet'  => ['label' => Violet::label(),  'dark' => Violet::dark(),  'light' => Violet::light()],
            'slate'   => ['label' => Slate::label(),   'dark' => Slate::dark(),   'light' => Slate::light()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function get(string $id, string $mode = 'dark'): array
    {
        $preset = self::all()[$id] ?? self::all()['orange'];
        $variant = $mode === 'light' ? 'light' : 'dark';

        return $preset[$variant];
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
}
