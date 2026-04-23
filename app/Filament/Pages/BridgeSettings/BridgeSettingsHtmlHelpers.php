<?php

namespace App\Filament\Pages\BridgeSettings;

use Illuminate\Support\HtmlString;

/**
 * Pure HTML rendering helpers for the BridgeSettings admin page.
 *
 * Each method returns an `HtmlString` so it can be slotted directly into
 * Filament `Placeholder::make(...)->content(...)` calls. No state, no
 * service dependencies — extracted from BridgeSettings.php to honour the
 * 300-line file budget.
 *
 * Pattern mirrors `Filament\Pages\Settings\SettingsFormSchema` (sibling
 * class next to the page that owns it).
 */
final class BridgeSettingsHtmlHelpers
{
    /**
     * Renders the "URL to copy" card — large mono URL on a contrast surface
     * with a one-line instruction underneath. Used everywhere we want the
     * admin to grab a value and paste it elsewhere.
     */
    public static function renderUrlBox(string $url, string $hint): HtmlString
    {
        return new HtmlString(
            '<div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40 p-3">'
            .'<code class="block text-sm font-mono break-all text-gray-900 dark:text-gray-100">'
            .e($url).'</code>'
            .'</div>'
            .'<p class="mt-2 text-xs text-gray-500">'.e($hint).'</p>'
        );
    }

    /**
     * Renders an HTTP endpoint list with colored verb badges (POST=primary,
     * DELETE=danger). Each entry is verb + URL + description.
     *
     * @param  array<int, array{0: string, 1: string, 2: string}>  $endpoints
     */
    public static function renderEndpointList(array $endpoints): HtmlString
    {
        $rows = '';
        foreach ($endpoints as [$verb, $url, $description]) {
            $verbClasses = match ($verb) {
                'POST'   => 'bg-primary-500/15 text-primary-700 dark:text-primary-300',
                'DELETE' => 'bg-danger-500/15 text-danger-700 dark:text-danger-300',
                'GET'    => 'bg-success-500/15 text-success-700 dark:text-success-300',
                default  => 'bg-gray-500/15 text-gray-700 dark:text-gray-300',
            };
            $rows .= '<div class="flex items-start gap-3 py-1.5">'
                .'<span class="inline-block min-w-[58px] text-center rounded-md px-2 py-0.5 text-xs font-semibold '.$verbClasses.'">'.e($verb).'</span>'
                .'<div class="flex-1 min-w-0">'
                .'<code class="block text-xs font-mono break-all text-gray-900 dark:text-gray-100">'.e($url).'</code>'
                .'<span class="text-xs text-gray-500">'.e($description).'</span>'
                .'</div>'
                .'</div>';
        }

        return new HtmlString(
            '<div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40 px-3 py-1 divide-y divide-gray-200 dark:divide-gray-800">'
            .$rows
            .'</div>'
        );
    }

    /**
     * Renders a list of "tags" (short event identifiers) as styled pills.
     *
     * @param  array<int, string>  $items
     */
    public static function renderTagList(array $items, ?string $note = null): HtmlString
    {
        $pills = '';
        foreach ($items as $item) {
            $pills .= '<span class="inline-block rounded-md bg-primary-500/10 text-primary-700 dark:text-primary-300 px-2 py-0.5 text-xs font-mono mr-1.5 mb-1.5">'
                .e($item).'</span>';
        }

        $noteHtml = $note !== null
            ? '<p class="mt-2 text-xs text-gray-500">'.e($note).'</p>'
            : '';

        return new HtmlString('<div>'.$pills.'</div>'.$noteHtml);
    }

    /**
     * Renders a 2-column key/value table inside a contrast-surface card.
     *
     * @param  array<int, array{0: string, 1: string}>  $rows
     */
    public static function renderKeyValueList(array $rows): HtmlString
    {
        $body = '';
        foreach ($rows as [$key, $value]) {
            $body .= '<div class="flex items-start gap-3 py-1.5">'
                .'<span class="min-w-[110px] text-xs font-medium text-gray-600 dark:text-gray-400">'.e($key).'</span>'
                .'<code class="flex-1 text-xs font-mono break-all text-gray-900 dark:text-gray-100">'.e($value).'</code>'
                .'</div>';
        }

        return new HtmlString(
            '<div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40 px-3 py-1 divide-y divide-gray-200 dark:divide-gray-800">'
            .$body
            .'</div>'
        );
    }

    /**
     * Renders a header table (key / value / note) — the value is allowed to
     * contain pre-escaped HTML entities (e.g. &lt;token&gt;) so we don't
     * escape it again here.
     *
     * @param  array<int, array{0: string, 1: string, 2: string}>  $rows
     */
    public static function renderHeadersList(array $rows): HtmlString
    {
        $body = '';
        foreach ($rows as [$key, $value, $note]) {
            $body .= '<div class="flex items-start gap-3 py-1.5">'
                .'<span class="min-w-[140px] text-xs font-semibold text-gray-700 dark:text-gray-300">'.e($key).'</span>'
                .'<div class="flex-1 min-w-0">'
                .'<code class="block text-xs font-mono break-all text-gray-900 dark:text-gray-100">'.$value.'</code>'
                .'<span class="text-xs text-gray-500">'.e($note).'</span>'
                .'</div>'
                .'</div>';
        }

        return new HtmlString(
            '<div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40 px-3 py-1 divide-y divide-gray-200 dark:divide-gray-800">'
            .$body
            .'</div>'
        );
    }

    /**
     * Renders a documentation link with arrow + a short description below.
     */
    public static function renderDocLink(string $url, string $description): HtmlString
    {
        return new HtmlString(
            '<a href="'.e($url).'" target="_blank" rel="noopener" '
            .'class="inline-flex items-center gap-1 text-primary-600 underline hover:text-primary-500 break-all">'
            .e($url).' <span aria-hidden="true">↗</span></a>'
            .'<p class="mt-1 text-xs text-gray-500">'.e($description).'</p>'
        );
    }
}
