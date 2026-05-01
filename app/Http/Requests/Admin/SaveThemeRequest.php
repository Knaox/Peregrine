<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the theme payload posted by the React Theme Studio. Mirrors what
 * the Filament ThemeSettings page persists — every color is hex (incl. shorts),
 * radius is a CSS length token, density is a 3-value enum, custom CSS is
 * loose text (sanitized by being injected into a single <style> element).
 *
 * Card and sidebar configs are nested arrays — kept lenient on inner keys so
 * the studio stays compatible with newer fields without breaking older saves.
 */
class SaveThemeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->is_admin);
    }

    public function rules(): array
    {
        $hex = ['nullable', 'string', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/'];

        return [
            'theme_preset' => ['nullable', 'string', 'max:32'],
            'theme_mode' => ['nullable', 'in:dark,light,auto'],
            'theme_primary' => $hex,
            'theme_primary_hover' => $hex,
            'theme_secondary' => $hex,
            'theme_ring' => $hex,
            'theme_danger' => $hex,
            'theme_warning' => $hex,
            'theme_success' => $hex,
            'theme_info' => $hex,
            'theme_suspended' => $hex,
            'theme_installing' => $hex,
            'theme_background' => $hex,
            'theme_surface' => $hex,
            'theme_surface_hover' => $hex,
            'theme_surface_elevated' => $hex,
            'theme_border' => $hex,
            'theme_border_hover' => $hex,
            'theme_text_primary' => $hex,
            'theme_text_secondary' => $hex,
            'theme_text_muted' => $hex,
            'theme_radius' => ['nullable', 'string', 'max:16'],
            'theme_font' => ['nullable', 'string', 'max:64'],
            'theme_shadow_intensity' => ['nullable', 'integer', 'min:0', 'max:100'],
            'theme_density' => ['nullable', 'in:compact,comfortable,spacious'],
            'theme_custom_css' => ['nullable', 'string', 'max:20000'],

            // Layout (Vague 3 démarrage). Defaults reproduce the prior
            // hardcoded AppLayout. Persisted as plain strings in the
            // settings table — controller and ThemeService cast on read.
            'theme_layout_header_height' => ['nullable', 'integer', 'min:48', 'max:96'],
            'theme_layout_header_sticky' => ['nullable', 'boolean'],
            'theme_layout_header_align' => ['nullable', 'in:default,centered,split'],
            'theme_layout_container_max' => ['nullable', 'in:1280,1440,1536,full'],
            'theme_layout_page_padding' => ['nullable', 'in:compact,comfortable,spacious'],

            // Sidebar in-server (Vague 3 complète).
            'theme_sidebar_classic_width' => ['nullable', 'integer', 'min:180', 'max:280'],
            'theme_sidebar_rail_width' => ['nullable', 'integer', 'min:56', 'max:96'],
            'theme_sidebar_mobile_width' => ['nullable', 'integer', 'min:200', 'max:320'],
            'theme_sidebar_blur_intensity' => ['nullable', 'integer', 'min:0', 'max:32'],
            'theme_sidebar_floating' => ['nullable', 'boolean'],

            // Login templates (Vague 3 complète).
            'theme_login_template' => ['nullable', 'in:centered,split,overlay,minimal'],
            'theme_login_background_image' => ['nullable', 'string', 'max:500'],
            'theme_login_background_blur' => ['nullable', 'integer', 'min:0', 'max:24'],
            'theme_login_background_pattern' => ['nullable', 'in:none,gradient,mesh,dots,grid,aurora,orbs,noise'],
            // Carousel — optional multi-image rotation behind the form.
            'theme_login_background_images' => ['nullable', 'array', 'max:8'],
            'theme_login_background_images.*' => ['string', 'max:500'],
            'theme_login_carousel_enabled' => ['nullable', 'boolean'],
            'theme_login_carousel_interval' => ['nullable', 'integer', 'min:2000', 'max:30000'],
            'theme_login_carousel_random' => ['nullable', 'boolean'],
            'theme_login_background_opacity' => ['nullable', 'integer', 'min:0', 'max:100'],

            // Per-page overrides (Vague 3 complète).
            'theme_page_console_fullwidth' => ['nullable', 'boolean'],
            'theme_page_files_fullwidth' => ['nullable', 'boolean'],
            'theme_page_dashboard_expanded' => ['nullable', 'boolean'],

            // Footer (Vague 3 complète).
            'theme_footer_enabled' => ['nullable', 'boolean'],
            'theme_footer_text' => ['nullable', 'string', 'max:500'],
            'theme_footer_links' => ['nullable', 'array'],
            'theme_footer_links.*.label' => ['required_with:theme_footer_links', 'string', 'max:64'],
            'theme_footer_links.*.url' => ['required_with:theme_footer_links', 'string', 'max:300'],

            // Refinements (Vague 3 complète).
            'theme_animation_speed' => ['nullable', 'in:instant,slower,default,faster'],
            'theme_hover_scale' => ['nullable', 'in:subtle,default,pronounced'],
            'theme_border_width' => ['nullable', 'integer', 'min:1', 'max:3'],
            'theme_glass_blur_global' => ['nullable', 'integer', 'min:0', 'max:48'],
            'theme_font_size_scale' => ['nullable', 'in:small,default,large,xl'],
            'theme_app_background_pattern' => ['nullable', 'in:none,gradient,mesh,dots,grid,aurora,orbs,noise'],

            'sidebar_preset' => ['nullable', 'string', 'max:32'],
            'card_config' => ['nullable', 'array'],
            'sidebar_config' => ['nullable', 'array'],
            'sidebar_config.entries' => ['nullable', 'array'],
            'sidebar_config.entries.*.id' => ['required_with:sidebar_config.entries', 'string', 'max:32'],
            'sidebar_config.entries.*.label_key' => ['nullable', 'string', 'max:128'],
            'sidebar_config.entries.*.icon' => ['nullable', 'string', 'max:32'],
            'sidebar_config.entries.*.enabled' => ['nullable', 'boolean'],
            'sidebar_config.entries.*.route_suffix' => ['nullable', 'string', 'max:64'],
            'sidebar_config.entries.*.order' => ['nullable', 'integer'],
        ];
    }
}
