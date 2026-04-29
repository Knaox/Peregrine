{{--
    "Certified" badge displayed inline next to the plugin name on cards in
    `/admin/plugins`. Indicates a plugin published + audited by the Peregrine
    Team (entry has `official: true` in the registry, or local author equals
    "Peregrine Team").

    Visual : primary-tinted pill + check-shield SVG so the trust signal is
    distinguishable from purely decorative status badges (Active / Installed
    / Update available).
--}}
<span style="
    font-size: 0.625rem;
    font-weight: 600;
    padding: 0.125rem 0.5rem;
    border-radius: 9999px;
    background: rgba(var(--primary-500), 0.14);
    color: rgb(var(--primary-300));
    border: 1px solid rgba(var(--primary-500), 0.28);
    display: inline-flex; align-items: center; gap: 0.25rem;
    line-height: 1.3;
" aria-label="{{ __('admin.partials.plugin_certified.aria') }}">
    <svg style="width: 0.75rem; height: 0.75rem;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
        <path stroke-linecap="round" stroke-linejoin="round"
              d="M9 12.75 11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 0 1-1.043 3.296 3.745 3.745 0 0 1-3.296 1.043A3.745 3.745 0 0 1 12 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 0 1-3.296-1.043 3.745 3.745 0 0 1-1.043-3.296A3.745 3.745 0 0 1 3 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 0 1 1.043-3.296 3.746 3.746 0 0 1 3.296-1.043A3.746 3.746 0 0 1 12 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 0 1 3.296 1.043 3.746 3.746 0 0 1 1.043 3.296A3.745 3.745 0 0 1 21 12Z" />
    </svg>
    {{ __('admin.partials.plugin_certified.label') }}
</span>
