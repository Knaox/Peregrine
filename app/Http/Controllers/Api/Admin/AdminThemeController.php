<?php

namespace App\Http\Controllers\Api\Admin;

use App\Filament\Pages\Theme\ThemeDefaults;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveThemeRequest;
use App\Http\Requests\Admin\UploadThemeAssetRequest;
use App\Services\SettingsService;
use App\Services\ThemeService;
use App\Support\ThemePresets;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Theme Studio backend — admin-only endpoint that mirrors what the Filament
 * ThemeSettings page does on save, but accepts a single JSON payload from
 * the React studio at /theme-studio.
 *
 * Save semantics are intentionally identical to the Filament flow so a save
 * from either entry point produces the same DB state and UX after reload.
 */
class AdminThemeController extends Controller
{
    public function __construct(
        private SettingsService $settings,
        private ThemeService $theme,
    ) {}

    /**
     * Lists every brand preset (dark + light values) so the studio can
     * one-click swap to a new color set without round-tripping the server
     * each time the admin browses the dropdown.
     */
    public function presets(): JsonResponse
    {
        return response()->json(['presets' => ThemePresets::all()]);
    }

    /**
     * Returns the raw setting values that the Theme Studio editor binds to
     * (no derivation, no caching layer translation). Mirror of the keys
     * persisted by save() — feeds the studio form's initial state and
     * doubles as the "discard draft" snapshot.
     */
    public function state(): JsonResponse
    {
        $intKeys = [
            'theme_shadow_intensity',
            'theme_layout_header_height',
            'theme_sidebar_classic_width',
            'theme_sidebar_rail_width',
            'theme_sidebar_mobile_width',
            'theme_sidebar_blur_intensity',
            'theme_login_background_blur',
            'theme_login_carousel_interval',
            'theme_login_background_opacity',
            'theme_border_width',
            'theme_glass_blur_global',
        ];
        $boolKeys = [
            'theme_layout_header_sticky',
            'theme_sidebar_floating',
            'theme_login_carousel_enabled',
            'theme_login_carousel_random',
            'theme_page_console_fullwidth',
            'theme_page_files_fullwidth',
            'theme_page_dashboard_expanded',
            'theme_footer_enabled',
        ];
        // JSON-encoded settings — surfaced to the studio as native arrays.
        $jsonArrayKeys = ['theme_login_background_images'];

        $draft = [];
        foreach (ThemeDefaults::COLORS as $key => $default) {
            $value = $this->settings->get($key, $default);
            if (in_array($key, $intKeys, true)) {
                $draft[$key] = (int) $value;
                continue;
            }
            if (in_array($key, $boolKeys, true)) {
                $draft[$key] = $value === '1' || $value === 'true' || $value === true;
                continue;
            }
            if (in_array($key, $jsonArrayKeys, true)) {
                $decoded = json_decode((string) $value, true);
                $draft[$key] = is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
                continue;
            }
            $draft[$key] = $value;
        }

        // theme_footer_links is JSON-encoded. Decode for the studio editor.
        $footerLinksRaw = (string) $this->settings->get('theme_footer_links', '[]');
        $footerLinks = json_decode($footerLinksRaw, true);
        $draft['theme_footer_links'] = is_array($footerLinks) ? $footerLinks : [];

        return response()->json([
            'draft' => $draft,
            'card_config' => $this->theme->getCardConfig(),
            'sidebar_config' => $this->theme->getSidebarConfig(),
        ]);
    }

    public function save(SaveThemeRequest $request): JsonResponse
    {
        $data = $request->validated();
        $boolKeys = [
            'theme_layout_header_sticky',
            'theme_sidebar_floating',
            'theme_login_carousel_enabled',
            'theme_login_carousel_random',
            'theme_page_console_fullwidth',
            'theme_page_files_fullwidth',
            'theme_page_dashboard_expanded',
            'theme_footer_enabled',
        ];
        $jsonArrayKeys = ['theme_login_background_images'];

        foreach (array_keys(ThemeDefaults::COLORS) as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }
            $value = $data[$key];
            if ($value === null) {
                $this->settings->set($key, null);
                continue;
            }
            // Booleans persist as '1'/'0' strings so reads via SettingsService
            // (which always returns string) round-trip cleanly.
            if (in_array($key, $boolKeys, true)) {
                $this->settings->set($key, $value ? '1' : '0');
                continue;
            }
            // Array values land as JSON strings — same pattern as
            // theme_footer_links / card_server_config.
            if (in_array($key, $jsonArrayKeys, true)) {
                $arr = is_array($value) ? array_values(array_filter($value, 'is_string')) : [];
                $this->settings->set($key, json_encode($arr));
                continue;
            }
            $this->settings->set($key, (string) $value);
        }

        // theme_footer_links is a structured array but stored as JSON in
        // the settings table (same pattern as card_server_config).
        if (array_key_exists('theme_footer_links', $data) && is_array($data['theme_footer_links'])) {
            $this->settings->set('theme_footer_links', json_encode(array_values($data['theme_footer_links'])));
        }

        if (array_key_exists('sidebar_preset', $data)) {
            $this->settings->set('sidebar_preset', $data['sidebar_preset'] ?? 'classic');
        }

        if (array_key_exists('card_config', $data) && is_array($data['card_config'])) {
            $merged = array_replace_recursive(ThemeDefaults::CARD_CONFIG, $data['card_config']);
            $this->settings->set('card_server_config', json_encode($merged));
        }

        if (array_key_exists('sidebar_config', $data) && is_array($data['sidebar_config'])) {
            $merged = array_replace_recursive(ThemeDefaults::SIDEBAR_CONFIG, $data['sidebar_config']);
            $this->settings->set('sidebar_server_config', json_encode($merged));
        }

        $this->settings->clearCache();
        $this->theme->clearCache();

        return response()->json([
            'data' => $this->theme->getTheme(),
            'css_variables' => $this->theme->getCssVariables(),
            'mode_variants' => $this->theme->getModeVariants(),
            'card_config' => $this->theme->getCardConfig(),
            'sidebar_config' => $this->theme->getSidebarConfig(),
        ]);
    }

    /**
     * Image upload for theme assets (currently: login background). Stores
     * the file in storage/app/public/branding/{slot}/ — exposed under
     * /storage/branding/{slot}/{hash}.{ext} via the existing public symlink.
     *
     * The studio reads the returned URL and persists it in the matching
     * `theme_*` setting as a path string. Old uploads are left in place so
     * a quick "undo" via setting revert keeps the file accessible.
     */
    public function uploadAsset(UploadThemeAssetRequest $request): JsonResponse
    {
        $slot = (string) $request->validated('slot');
        $file = $request->file('file');

        $extension = strtolower($file->extension() ?: $file->getClientOriginalExtension() ?: 'png');
        $filename = Str::random(24) . '.' . $extension;

        // Laravel 11+ moved the `local` disk root to storage/app/private —
        // we MUST target the `public` disk explicitly so the file lands in
        // storage/app/public (which is what the /storage symlink points to).
        $path = $file->storeAs("branding/{$slot}", $filename, 'public');

        $publicPath = '/storage/' . $path;

        return response()->json([
            'path' => $publicPath,
            'url' => $publicPath,
        ]);
    }

    public function reset(): JsonResponse
    {
        foreach (ThemeDefaults::COLORS as $key => $value) {
            $this->settings->set($key, $value);
        }
        $this->settings->set('sidebar_preset', 'classic');
        $this->settings->set('card_server_config', json_encode(ThemeDefaults::CARD_CONFIG));
        $this->settings->set('sidebar_server_config', json_encode(ThemeDefaults::SIDEBAR_CONFIG));

        $this->settings->clearCache();
        $this->theme->clearCache();

        return response()->json([
            'data' => $this->theme->getTheme(),
            'css_variables' => $this->theme->getCssVariables(),
            'mode_variants' => $this->theme->getModeVariants(),
            'card_config' => $this->theme->getCardConfig(),
            'sidebar_config' => $this->theme->getSidebarConfig(),
        ]);
    }
}
