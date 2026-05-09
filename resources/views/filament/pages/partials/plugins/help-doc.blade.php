{{--
    Inline documentation for the .zip upload feature. Auto-translates via
    __() so the language follows whatever locale the admin is in. The
    actual copy lives under `admin/plugins.upload.doc.*` in lang/{en,fr}.

    The page builds a manifest example dynamically from config so the
    quoted limits never drift from the real values.
--}}
@php
    $maxSize = round((int) config('panel.plugin_upload.max_size') / 1024 / 1024).' MB';
    $maxEntries = (int) config('panel.plugin_upload.max_entries');
    $maxExtracted = round((int) config('panel.plugin_upload.max_extracted_size') / 1024 / 1024).' MB';
    $maxRatio = (int) config('panel.plugin_upload.max_compression_ratio');
@endphp

<div class="pg-doc">
    <h4 class="pg-doc-section-title">{{ __('admin/plugins.upload.doc.format_title') }}</h4>
    <p class="pg-doc-text">{{ __('admin/plugins.upload.doc.format_intro') }}</p>
    <pre class="pg-doc-code"><code>my-plugin.zip
├── manifest.json   {{ __('admin/plugins.upload.doc.manifest_required') }}
├── README.md
└── src/
    └── ServiceProvider.php</code></pre>

    <p class="pg-doc-text" style="margin-top: 0.875rem;">{{ __('admin/plugins.upload.doc.manifest_example_title') }}</p>
    <pre class="pg-doc-code"><code>{
  "id": "my-plugin",
  "name": "My Plugin",
  "version": "1.0.0",
  "author": "Your Name",
  "description": "{{ __('admin/plugins.upload.doc.manifest_example_desc') }}"
}</code></pre>

    <h4 class="pg-doc-section-title">{{ __('admin/plugins.upload.doc.security_title') }}</h4>
    <ul class="pg-doc-list">
        <li>{{ __('admin/plugins.upload.doc.security_check_signature') }}</li>
        <li>{{ __('admin/plugins.upload.doc.security_check_traversal') }}</li>
        <li>{{ __('admin/plugins.upload.doc.security_check_symlinks') }}</li>
        <li>{{ __('admin/plugins.upload.doc.security_check_extensions') }}</li>
        <li>{{ __('admin/plugins.upload.doc.security_check_ratio') }}</li>
        <li>{{ __('admin/plugins.upload.doc.security_check_perms') }}</li>
        <li>{{ __('admin/plugins.upload.doc.security_check_logs') }}</li>
    </ul>

    <h4 class="pg-doc-section-title">{{ __('admin/plugins.upload.doc.limits_title') }}</h4>
    <ul class="pg-doc-list pg-doc-grid">
        <li><strong>{{ __('admin/plugins.upload.doc.limit_size') }}</strong><span>{{ $maxSize }}</span></li>
        <li><strong>{{ __('admin/plugins.upload.doc.limit_entries') }}</strong><span>{{ $maxEntries }}</span></li>
        <li><strong>{{ __('admin/plugins.upload.doc.limit_extracted') }}</strong><span>{{ $maxExtracted }}</span></li>
        <li><strong>{{ __('admin/plugins.upload.doc.limit_ratio') }}</strong><span>{{ $maxRatio }}:1</span></li>
    </ul>

    <p class="pg-doc-callout">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" style="flex-shrink: 0;"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 0 1-1.043 3.296 3.745 3.745 0 0 1-3.296 1.043A3.745 3.745 0 0 1 12 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 0 1-3.296-1.043 3.745 3.745 0 0 1-1.043-3.296A3.745 3.745 0 0 1 3 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 0 1 1.043-3.296 3.746 3.746 0 0 1 3.296-1.043A3.746 3.746 0 0 1 12 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 0 1 3.296 1.043 3.746 3.746 0 0 1 1.043 3.296A3.745 3.745 0 0 1 21 12Z" /></svg>
        {{ __('admin/plugins.upload.doc.tip_official') }}
    </p>
</div>

{{-- @once : single CSS emission per request, même si le partial est inclus plusieurs fois. --}}
@once
<style>
    .pg-plugins .pg-doc { margin-top: 1rem; padding: 1rem 1.125rem; border-radius: 0.625rem; background: rgba(255,255,255,0.035); border: 1px solid rgba(255,255,255,0.07); font-size: 0.8125rem; line-height: 1.6; color: var(--pg-text-muted); }
    .pg-plugins .pg-doc-section-title { font-size: 0.75rem; font-weight: 600; color: var(--pg-text-primary); margin: 0 0 0.5rem; text-transform: uppercase; letter-spacing: 0.04em; }
    .pg-plugins .pg-doc-section-title:not(:first-child) { margin-top: 1rem; }
    .pg-plugins .pg-doc-text { margin: 0 0 0.5rem; }
    .pg-plugins .pg-doc-code { margin: 0; padding: 0.75rem 0.875rem; background: rgba(0,0,0,0.25); border: 1px solid rgba(255,255,255,0.05); border-radius: 0.5rem; font-size: 0.75rem; color: var(--pg-text-primary); font-family: ui-monospace, SFMono-Regular, monospace; overflow-x: auto; line-height: 1.5; }
    .pg-plugins .pg-doc-code code { background: none; padding: 0; }
    .pg-plugins .pg-doc-list { margin: 0.25rem 0 0; padding-left: 1.125rem; }
    .pg-plugins .pg-doc-list li { margin-bottom: 0.25rem; }
    .pg-plugins .pg-doc-grid { list-style: none; padding: 0; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.5rem; }
    .pg-plugins .pg-doc-grid li { display: flex; justify-content: space-between; padding: 0.5rem 0.75rem; border-radius: 0.375rem; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.04); }
    .pg-plugins .pg-doc-grid li strong { color: var(--pg-text-primary); font-weight: 500; }
    .pg-plugins .pg-doc-grid li span { color: rgb(var(--primary-300)); font-feature-settings: 'tnum'; font-family: ui-monospace, monospace; font-size: 0.75rem; }
    .pg-plugins .pg-doc-callout { display: inline-flex; align-items: flex-start; gap: 0.5rem; margin: 1rem 0 0; padding: 0.625rem 0.875rem; border-radius: 0.5rem; background: rgba(var(--pg-success), 0.1); border: 1px solid rgba(var(--pg-success), 0.25); font-size: 0.8125rem; color: rgb(var(--pg-success)); }
</style>
@endonce
