{{--
    Top-level "X updates available — [Update all]" banner. Only rendered
    when at least one installed plugin has a pending registry upgrade.

    Variables :
      - $stats : array{updates: int}
--}}
<div class="pg-banner">
    <span class="pg-banner-icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
        </svg>
    </span>
    <div class="pg-banner-text">
        <p class="pg-banner-title">
            @if ($stats['updates'] === 1)
                {{ __('admin/plugins.updates_banner.title_one') }}
            @else
                {{ __('admin/plugins.updates_banner.title_other', ['count' => $stats['updates']]) }}
            @endif
        </p>
        <p class="pg-banner-sub">{{ __('admin/plugins.updates_banner.subtitle') }}</p>
    </div>
    <button type="button" class="pg-btn pg-btn-warning" wire:click="updateAllPlugins" wire:loading.attr="disabled" wire:target="updateAllPlugins">
        <span wire:loading.remove wire:target="updateAllPlugins" style="display: inline-flex; align-items: center; gap: 0.375rem;">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
            </svg>
            {{ __('admin/plugins.updates_banner.action') }}
        </span>
        <span wire:loading wire:target="updateAllPlugins">{{ __('admin/plugins.updates_banner.action_running') }}</span>
    </button>
</div>
