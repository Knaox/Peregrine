<?php

namespace App\Filament\Pages\BridgeSettings;

use Illuminate\Support\HtmlString;

/**
 * Pure HTML rendering helpers for the BridgeSettings + PelicanWebhookSettings
 * admin pages. Inline styles only — Filament's default theme does not compile
 * arbitrary Tailwind utility classes from custom blade output, so all visual
 * styling lives directly in the HTML.
 *
 * Each method returns an `HtmlString` so it can be slotted directly into
 * Filament `Placeholder::make(...)->content(...)` calls. No state, no
 * service dependencies.
 */
final class BridgeSettingsHtmlHelpers
{
    private const CARD_STYLE = 'border-radius: 0.5rem; border: 1px solid rgba(255,255,255,0.08); background: rgba(0,0,0,0.25); padding: 0.25rem 0.75rem; overflow: hidden;';

    private const ROW_STYLE = 'display: flex; align-items: flex-start; gap: 0.875rem; padding: 0.625rem 0; border-bottom: 1px solid rgba(255,255,255,0.06);';

    private const ROW_LAST_STYLE = 'display: flex; align-items: flex-start; gap: 0.875rem; padding: 0.625rem 0;';

    private const KEY_STYLE = 'flex-shrink: 0; min-width: 8rem; font-size: 0.75rem; font-weight: 600; color: rgba(255,255,255,0.65);';

    private const VALUE_STYLE = 'flex: 1 1 auto; min-width: 0; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 0.8125rem; color: rgba(255,255,255,0.95); word-break: break-all;';

    private const NOTE_STYLE = 'display: block; margin-top: 0.25rem; font-family: system-ui, -apple-system, sans-serif; font-size: 0.75rem; color: rgba(255,255,255,0.55); word-break: normal;';

    private const HINT_STYLE = 'margin: 0.5rem 0 0 0; font-size: 0.75rem; color: rgba(255,255,255,0.55);';

    public static function renderUrlBox(string $url, string $hint): HtmlString
    {
        return new HtmlString(
            '<div style="' . self::CARD_STYLE . ' padding: 0.75rem 1rem;">'
            . '<code style="display: block; font-family: ui-monospace, monospace; font-size: 0.8125rem; color: rgba(255,255,255,0.95); word-break: break-all;">'
            . e($url)
            . '</code>'
            . '</div>'
            . '<p style="' . self::HINT_STYLE . '">' . e($hint) . '</p>'
        );
    }

    /**
     * @param  array<int, array{0: string, 1: string, 2: string}>  $endpoints
     */
    public static function renderEndpointList(array $endpoints): HtmlString
    {
        $rows = '';
        $last = count($endpoints) - 1;

        foreach ($endpoints as $i => [$verb, $url, $description]) {
            $verbStyle = match ($verb) {
                'POST' => 'background: rgba(var(--primary-500), 0.18); color: rgb(var(--primary-300));',
                'DELETE' => 'background: rgba(239,68,68,0.15); color: rgb(252,165,165);',
                'GET' => 'background: rgba(34,197,94,0.15); color: rgb(134,239,172);',
                default => 'background: rgba(255,255,255,0.08); color: rgba(255,255,255,0.85);',
            };

            $rows .= '<div style="' . ($i === $last ? self::ROW_LAST_STYLE : self::ROW_STYLE) . '">'
                . '<span style="flex-shrink: 0; display: inline-block; min-width: 4rem; text-align: center; padding: 0.125rem 0.5rem; border-radius: 0.25rem; font-size: 0.6875rem; font-weight: 700; letter-spacing: 0.05em; ' . $verbStyle . '">'
                . e($verb)
                . '</span>'
                . '<div style="flex: 1 1 auto; min-width: 0;">'
                . '<code style="display: block; font-family: ui-monospace, monospace; font-size: 0.75rem; color: rgba(255,255,255,0.95); word-break: break-all;">'
                . e($url)
                . '</code>'
                . '<span style="' . self::NOTE_STYLE . '">' . e($description) . '</span>'
                . '</div>'
                . '</div>';
        }

        return new HtmlString('<div style="' . self::CARD_STYLE . '">' . $rows . '</div>');
    }

    /**
     * @param  array<int, string>  $items
     */
    public static function renderTagList(array $items, ?string $note = null): HtmlString
    {
        $pills = '<div style="display: flex; flex-wrap: wrap; gap: 0.375rem;">';
        foreach ($items as $item) {
            $pills .= '<span style="display: inline-block; padding: 0.25rem 0.5rem; border-radius: 0.25rem; background: rgba(var(--primary-500), 0.15); color: rgb(var(--primary-300)); font-family: ui-monospace, monospace; font-size: 0.75rem; line-height: 1;">'
                . e($item)
                . '</span>';
        }
        $pills .= '</div>';

        $noteHtml = $note !== null
            ? '<p style="' . self::HINT_STYLE . '">' . e($note) . '</p>'
            : '';

        return new HtmlString('<div>' . $pills . $noteHtml . '</div>');
    }

    /**
     * @param  array<int, array{0: string, 1: string}>  $rows
     */
    public static function renderKeyValueList(array $rows): HtmlString
    {
        $body = '';
        $last = count($rows) - 1;

        foreach ($rows as $i => [$key, $value]) {
            $body .= '<div style="' . ($i === $last ? self::ROW_LAST_STYLE : self::ROW_STYLE) . '">'
                . '<span style="' . self::KEY_STYLE . '">' . e($key) . '</span>'
                . '<code style="' . self::VALUE_STYLE . '">' . e($value) . '</code>'
                . '</div>';
        }

        return new HtmlString('<div style="' . self::CARD_STYLE . '">' . $body . '</div>');
    }

    /**
     * @param  array<int, array{0: string, 1: string, 2: string}>  $rows
     */
    public static function renderHeadersList(array $rows): HtmlString
    {
        $body = '';
        $last = count($rows) - 1;

        foreach ($rows as $i => [$key, $value, $note]) {
            $body .= '<div style="' . ($i === $last ? self::ROW_LAST_STYLE : self::ROW_STYLE) . '">'
                . '<span style="' . self::KEY_STYLE . ' min-width: 9rem;">' . e($key) . '</span>'
                . '<div style="flex: 1 1 auto; min-width: 0;">'
                // $value is allowed to contain pre-escaped entities (&lt;…&gt;)
                . '<code style="display: block; font-family: ui-monospace, monospace; font-size: 0.8125rem; color: rgba(255,255,255,0.95); word-break: break-all;">'
                . $value
                . '</code>'
                . '<span style="' . self::NOTE_STYLE . '">' . e($note) . '</span>'
                . '</div>'
                . '</div>';
        }

        return new HtmlString('<div style="' . self::CARD_STYLE . '">' . $body . '</div>');
    }

    public static function renderDocLink(string $url, string $description): HtmlString
    {
        return new HtmlString(
            '<a href="' . e($url) . '" target="_blank" rel="noopener" '
            . 'style="display: inline-flex; align-items: center; gap: 0.25rem; color: rgb(var(--primary-400)); text-decoration: underline; text-underline-offset: 2px; word-break: break-all;">'
            . e($url) . ' <span aria-hidden="true">↗</span></a>'
            . '<p style="' . self::HINT_STYLE . '">' . e($description) . '</p>'
        );
    }
}
