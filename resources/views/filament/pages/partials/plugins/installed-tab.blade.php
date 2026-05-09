{{--
    "Installed" tab body. Owns its empty state, status filter chips, and
    the cards grid. Cards themselves live in installed-card.blade.php.

    Variables :
      - $plugins             : array<array> — installed plugins
      - $stats               : array{installed,active,updates,marketplace}
      - $marketplaceEnabled  : bool
--}}
<div x-show="tab === 'installed'" x-cloak>
    @if (config('panel.plugin_upload.enabled', true))
        @include('filament.pages.partials.plugins.upload-zone')
    @endif

    @if (empty($plugins))
        <div class="pg-empty">
            <svg class="pg-empty-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M14.25 6.087c0-.355.186-.676.401-.959.221-.29.349-.634.349-1.003 0-1.036-1.007-1.875-2.25-1.875S10.5 3.089 10.5 4.125c0 .369.128.713.349 1.003.215.283.401.604.401.959v0a.64.64 0 0 1-.657.643 48.39 48.39 0 0 1-4.163-.3c.186 1.613.293 3.25.315 4.907a.656.656 0 0 1-.658.663v0c-.355 0-.676-.186-.959-.401a1.647 1.647 0 0 0-1.003-.349c-1.036 0-1.875 1.007-1.875 2.25s.84 2.25 1.875 2.25c.369 0 .713-.128 1.003-.349.283-.215.604-.401.959-.401v0c.31 0 .555.26.532.57a48.04 48.04 0 0 1-.642 5.056c1.518.19 3.058.309 4.616.354a.64.64 0 0 0 .657-.643v0c0-.355-.186-.676-.401-.959a1.647 1.647 0 0 1-.349-1.003c0-1.035 1.008-1.875 2.25-1.875 1.243 0 2.25.84 2.25 1.875 0 .369-.128.713-.349 1.003-.215.283-.4.604-.4.959v0c0 .333.277.599.61.58a48.1 48.1 0 0 0 5.427-.63 48.05 48.05 0 0 0 .582-4.717.532.532 0 0 0-.533-.57v0c-.355 0-.676.186-.959.401-.29.221-.634.349-1.003.349-1.035 0-1.875-1.007-1.875-2.25s.84-2.25 1.875-2.25c.37 0 .713.128 1.003.349.283.215.604.401.96.401v0a.656.656 0 0 0 .657-.663 48.42 48.42 0 0 0-.37-5.36c-1.886.342-3.81.574-5.766.689a.578.578 0 0 1-.61-.58v0Z" />
            </svg>
            <p class="pg-empty-title">{{ __('admin/plugins.empty_installed') }}</p>
            <p class="pg-empty-hint">
                {{ __('admin/plugins.empty_installed_hint') }}
                <code>plugins/</code>
                @if ($marketplaceEnabled)
                    — {{ __('admin/plugins.empty_installed_cta') }}
                @endif
            </p>
            @if ($marketplaceEnabled)
                <button type="button" class="pg-btn pg-btn-primary pg-empty-cta" @click="tab = 'marketplace'">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" /></svg>
                    {{ __('admin/plugins.actions.discover_marketplace') }}
                </button>
            @endif
        </div>
    @else
        {{-- Status filter chips ----------------------------------- --}}
        <div class="pg-toolbar">
            <div class="pg-chips">
                <button type="button" class="pg-chip" :class="{ 'is-active': statusFilter === 'all' }" @click="statusFilter = 'all'">
                    {{ __('admin/plugins.filters.all') }}
                    <span class="pg-chip-count">{{ count($plugins) }}</span>
                </button>
                <button type="button" class="pg-chip" :class="{ 'is-active': statusFilter === 'active' }" @click="statusFilter = 'active'">
                    <span class="pg-dot" style="background: rgb(var(--pg-success));"></span>
                    {{ __('admin/plugins.filters.active') }}
                    <span class="pg-chip-count">{{ $stats['active'] }}</span>
                </button>
                <button type="button" class="pg-chip" :class="{ 'is-active': statusFilter === 'inactive' }" @click="statusFilter = 'inactive'">
                    <span class="pg-dot" style="background: rgb(var(--pg-warning));"></span>
                    {{ __('admin/plugins.filters.inactive') }}
                    <span class="pg-chip-count">{{ $stats['installed'] - $stats['active'] }}</span>
                </button>
                @if ($stats['updates'] > 0)
                    <button type="button" class="pg-chip" :class="{ 'is-active': statusFilter === 'updates' }" @click="statusFilter = 'updates'">
                        <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" /></svg>
                        {{ __('admin/plugins.filters.updates') }}
                        <span class="pg-chip-count">{{ $stats['updates'] }}</span>
                    </button>
                @endif
            </div>
        </div>

        <div class="pg-grid">
            @foreach ($plugins as $plugin)
                @include('filament.pages.partials.plugins.installed-card', ['plugin' => $plugin])
            @endforeach
        </div>
    @endif
</div>
