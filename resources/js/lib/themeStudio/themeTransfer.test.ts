import { describe, expect, it } from 'vitest';
import { buildExportPayload, parseImport, serializeExport } from './themeTransfer';
import type { ThemeDraft } from '@/types/themeStudio.types';
import type { CardConfig } from '@/hooks/useCardConfig';

function draftStub(): ThemeDraft {
    return {
        theme_preset: 'biomebounty', theme_mode: 'dark',
        theme_primary: '#e11d48', theme_primary_hover: '#f43f5e', theme_secondary: '#fb7185',
        theme_ring: '#f43f5e', theme_danger: '#ef4444', theme_warning: '#f59e0b',
        theme_success: '#10b981', theme_info: '#3b82f6', theme_suspended: '#f59e0b',
        theme_installing: '#3b82f6', theme_background: '#120a0c', theme_surface: '#1c1013',
        theme_surface_hover: '#26161a', theme_surface_elevated: '#22141a', theme_border: '#3a2127',
        theme_border_hover: '#4d2a32', theme_text_primary: '#f7eef0', theme_text_secondary: '#c5a3aa',
        theme_text_muted: '#8a6a72', theme_radius: '1rem', theme_font: 'Inter',
        theme_shadow_intensity: 65, theme_density: 'comfortable', theme_custom_css: '',
        theme_layout_header_height: 64, theme_layout_header_sticky: true, theme_layout_header_align: 'default',
        theme_layout_container_max: '1440', theme_layout_page_padding: 'comfortable',
        theme_sidebar_classic_width: 224, theme_sidebar_rail_width: 64, theme_sidebar_mobile_width: 256,
        theme_sidebar_blur_intensity: 14, theme_sidebar_floating: false, theme_login_template: 'split',
        theme_login_background_image: '', theme_login_background_blur: 0, theme_login_background_pattern: 'biome',
        theme_login_background_images: [], theme_login_carousel_enabled: false, theme_login_carousel_interval: 6000,
        theme_login_carousel_random: true, theme_login_background_opacity: 100, theme_login_oauth_first: false,
        theme_page_console_fullwidth: false, theme_page_files_fullwidth: false, theme_page_dashboard_expanded: false,
        theme_footer_enabled: false, theme_footer_text: '', theme_footer_links: [],
        theme_animation_speed: 'default', theme_hover_scale: 'default', theme_border_width: 1,
        theme_glass_blur_global: 18, theme_font_size_scale: 'default', theme_app_background_pattern: 'biome',
        theme_app_shell_variant: 'default', theme_workspace_rail_width: 72,
    };
}

const cardStub = { card_layout_variant: 'biome' } as unknown as CardConfig;

describe('themeTransfer', () => {
    it('round-trips a valid export → import', () => {
        const payload = buildExportPayload(draftStub(), cardStub, null);
        const result = parseImport(serializeExport(payload));
        expect(result.ok).toBe(true);
        if (result.ok) {
            expect(result.value.draft.theme_primary).toBe('#e11d48');
            expect(result.value.cardConfig?.card_layout_variant).toBe('biome');
        }
    });

    it('rejects blacklisted custom CSS', () => {
        const bad = { draft: { theme_custom_css: '@import url(http://evil.test/x.css);' } };
        const result = parseImport(JSON.stringify(bad));
        expect(result).toEqual({ ok: false, error: 'unsafe_css' });
    });

    it('strips unknown keys but keeps known ones', () => {
        const result = parseImport(JSON.stringify({ draft: { theme_primary: '#fff', hax: 1 } }));
        expect(result.ok).toBe(true);
        if (result.ok) {
            expect(result.value.draft.theme_primary).toBe('#fff');
            expect('hax' in result.value.draft).toBe(false);
        }
    });

    it('tolerates a draft missing theme_suspended / theme_installing', () => {
        const result = parseImport(JSON.stringify({ draft: { theme_primary: '#abc' } }));
        expect(result.ok).toBe(true);
        if (result.ok) {
            expect(result.value.draft.theme_suspended).toBeUndefined();
        }
    });

    it('fails on non-JSON and on missing draft', () => {
        expect(parseImport('not json')).toEqual({ ok: false, error: 'not_json' });
        expect(parseImport('{}')).toEqual({ ok: false, error: 'missing_draft' });
    });
});
