{{--
    Single card rendered on the Marketplace tab of /admin/plugins.

    Variables :
      - $mp        : array — registry entry as returned by MarketplaceService::listWithStatus()
      - $featured  : bool  — true for the "Official" section (adds a primary-tinted gradient background)

    Filtering is done client-side by Alpine.js — the `x-show` expression
    references `query`, `category` and the dynamic search/match helpers
    declared on the page-level <div class="pg-plugins" x-data="..."> root.
--}}
@php
    $isInstalled = (bool) ($mp['is_installed'] ?? false);
    $hasUpdate = (bool) ($mp['update_available'] ?? false);
    $tags = $mp['tags'] ?? [];
    $official = (bool) ($mp['official'] ?? false);
    // External URL = plugin hosted off-platform (typically BuiltByBit).
    // Disables in-panel install : we redirect the admin to the vendor's
    // page so they buy / download there, then come back to import the
    // .zip via the upload feature.
    $externalUrl = $mp['external_url'] ?? null;
    $isExternal = $externalUrl !== null && filter_var($externalUrl, FILTER_VALIDATE_URL) !== false;
    $isBuiltByBit = $isExternal && str_contains(strtolower($externalUrl), 'builtbybit.com');
    $cardClass = 'pg-card';
    if ($featured ?? false) $cardClass .= ' is-featured';
    if ($hasUpdate) $cardClass .= ' is-update';
    if ($isExternal) $cardClass .= ' is-external';
@endphp

<article
    class="{{ $cardClass }}"
    x-show="matchesSearch(@js($mp['name'] ?? ''), @js($mp['author'] ?? ''), @js($tags)) && matchesMarketplace({{ $official ? 'true' : 'false' }}, @js($tags))"
>
    <header class="pg-card-head">
        <div class="pg-card-id">
            @include('filament.pages.partials.plugin-logo', [
                'official' => $official,
                'iconUrl' => $mp['icon_url'] ?? null,
            ])
            <div class="pg-card-title-wrap">
                <div class="pg-card-title-row">
                    <h3 class="pg-card-title">{{ $mp['name'] }}</h3>
                    <span class="pg-card-version">v{{ $mp['version'] }}</span>
                    @if ($official)
                        @include('filament.pages.partials.plugin-certified-badge')
                    @endif
                    @if ($isExternal)
                        <x-pg.pill variant="external" title="{{ $externalUrl }}">
                            @if ($isBuiltByBit)
                                <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5 9 8.25 13.5 12.75 21 5.25M21 5.25H15.75M21 5.25v5.25" /></svg>
                                BuiltByBit
                            @else
                                <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
                                {{ __('admin/plugins.external.badge') }}
                            @endif
                        </x-pg.pill>
                    @endif
                </div>
                @if (! empty($mp['author']))
                    <p class="pg-card-author">{{ __('admin/plugins.card.by', ['author' => $mp['author']]) }}</p>
                @endif
            </div>
        </div>

        @if ($isInstalled && ! $hasUpdate)
            <x-pg.pill variant="installed">
                <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                </svg>
                {{ __('admin/plugins.status.installed') }}
            </x-pg.pill>
        @endif
    </header>

    <p class="pg-card-desc">{{ $mp['description'] ?? __('admin/plugins.card.no_description') }}</p>

    @if (! empty($tags))
        <div class="pg-tags">
            @foreach ($tags as $tag)
                <button type="button" class="pg-tag" @click="category = (category === '{{ $tag }}') ? '' : '{{ $tag }}'" :class="{ 'is-active': category === '{{ $tag }}' }">#{{ $tag }}</button>
            @endforeach
        </div>
    @endif

    @if ($hasUpdate)
        <div class="pg-update-alert">
            <span class="pg-update-text">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2" style="flex-shrink: 0;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                </svg>
                v{{ $mp['installed_version'] ?? $mp['version'] }} → <strong>v{{ $mp['version'] }}</strong>
            </span>
        </div>
    @endif

    <div class="pg-actions">
        @if ($isInstalled)
            @if ($hasUpdate)
                <button type="button" wire:click="updatePlugin('{{ $mp['id'] }}')" wire:loading.attr="disabled" wire:target="updatePlugin('{{ $mp['id'] }}')" class="pg-btn pg-btn-warning">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                    </svg>
                    <span wire:loading.remove wire:target="updatePlugin('{{ $mp['id'] }}')">{{ __('admin/plugins.actions.update_to', ['version' => 'v'.$mp['version']]) }}</span>
                    <span wire:loading wire:target="updatePlugin('{{ $mp['id'] }}')">{{ __('admin/plugins.actions.updating') }}</span>
                </button>
            @else
                <span class="pg-btn pg-btn-success" style="cursor: default; pointer-events: none;">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                    {{ __('admin/plugins.status.installed') }}
                </span>
            @endif
        @elseif ($isExternal)
            <a href="{{ $externalUrl }}" target="_blank" rel="noopener noreferrer" class="pg-btn pg-btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
                @if ($isBuiltByBit)
                    {{ __('admin/plugins.external.view_on_builtbybit') }}
                @else
                    {{ __('admin/plugins.external.view_external') }}
                @endif
            </a>
        @else
            <button type="button" wire:click="installFromMarketplace('{{ $mp['id'] }}')" wire:loading.attr="disabled" wire:target="installFromMarketplace('{{ $mp['id'] }}')" class="pg-btn pg-btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9.75v6.75m0 0-3-3m3 3 3-3m-8.25 6a4.5 4.5 0 0 1-1.41-8.775 5.25 5.25 0 0 1 10.233-2.33 3 3 0 0 1 3.758 3.848A3.752 3.752 0 0 1 18 19.5H6.75Z" />
                </svg>
                <span wire:loading.remove wire:target="installFromMarketplace('{{ $mp['id'] }}')">{{ __('admin/plugins.actions.install') }}</span>
                <span wire:loading wire:target="installFromMarketplace('{{ $mp['id'] }}')">{{ __('admin/plugins.actions.installing') }}</span>
            </button>
        @endif
    </div>
</article>
