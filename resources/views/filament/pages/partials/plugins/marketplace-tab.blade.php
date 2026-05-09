{{--
    "Marketplace" tab body. Owns the search/filter toolbar, the optional
    category chips, and the two sections (Official + Community). Card
    rendering is delegated to partials.marketplace-card.

    Variables :
      - $marketplacePlugins   : array — full registry list
      - $officialMarketplace  : array — pre-filtered by `official=true`
      - $communityMarketplace : array — pre-filtered by `official=false`
      - $categories           : array<string> — distinct tags
--}}
<div x-show="tab === 'marketplace'" x-cloak>
    @if (empty($marketplacePlugins))
        <div class="pg-empty">
            <svg class="pg-empty-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418" />
            </svg>
            <p class="pg-empty-title">{{ __('admin/plugins.empty_marketplace') }}</p>
            <p class="pg-empty-hint">
                {{ __('admin/plugins.empty_marketplace_hint') }}
                <code>{{ config('panel.marketplace.registry_url') }}</code>
            </p>
            <button type="button" class="pg-btn pg-btn-default pg-empty-cta" wire:click="refreshMarketplace">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" wire:loading.class="pg-spin" wire:target="refreshMarketplace"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" /></svg>
                <span wire:loading.remove wire:target="refreshMarketplace">{{ __('admin/plugins.actions.refresh_registry') }}</span>
                <span wire:loading wire:target="refreshMarketplace">{{ __('admin/plugins.actions.refreshing') }}</span>
            </button>
        </div>
    @else
        {{-- Toolbar : search + filters + refresh ------------------ --}}
        <div class="pg-toolbar">
            <div class="pg-search">
                <span class="pg-search-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" /></svg>
                </span>
                <input type="search" x-model="query" placeholder="{{ __('admin/plugins.filters.search_placeholder') }}" autocomplete="off" />
                <button type="button" class="pg-search-clear" x-show="query.length > 0" @click="query = ''" x-cloak aria-label="Clear">
                    <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                </button>
            </div>

            <div class="pg-chips">
                <button type="button" class="pg-chip" :class="{ 'is-active': filter === 'all' }" @click="filter = 'all'">
                    {{ __('admin/plugins.filters.all') }}
                    <span class="pg-chip-count">{{ count($marketplacePlugins) }}</span>
                </button>
                <button type="button" class="pg-chip" :class="{ 'is-active': filter === 'official' }" @click="filter = 'official'">
                    <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 0 1-1.043 3.296 3.745 3.745 0 0 1-3.296 1.043A3.745 3.745 0 0 1 12 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 0 1-3.296-1.043 3.745 3.745 0 0 1-1.043-3.296A3.745 3.745 0 0 1 3 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 0 1 1.043-3.296 3.746 3.746 0 0 1 3.296-1.043A3.746 3.746 0 0 1 12 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 0 1 3.296 1.043 3.746 3.746 0 0 1 1.043 3.296A3.745 3.745 0 0 1 21 12Z" /></svg>
                    {{ __('admin/plugins.filters.official') }}
                    <span class="pg-chip-count">{{ count($officialMarketplace) }}</span>
                </button>
                <button type="button" class="pg-chip" :class="{ 'is-active': filter === 'community' }" @click="filter = 'community'">
                    {{ __('admin/plugins.filters.community') }}
                    <span class="pg-chip-count">{{ count($communityMarketplace) }}</span>
                </button>
            </div>

            <button type="button" wire:click="refreshMarketplace" class="pg-btn pg-btn-ghost" style="margin-left: auto;" title="{{ __('admin/plugins.actions.refresh_registry') }}">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" wire:loading.class="pg-spin" wire:target="refreshMarketplace"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" /></svg>
                <span wire:loading.remove wire:target="refreshMarketplace">{{ __('admin/plugins.actions.refresh_registry') }}</span>
                <span wire:loading wire:target="refreshMarketplace">{{ __('admin/plugins.actions.refreshing') }}</span>
            </button>
        </div>

        {{-- Category chips (only when there are categories) ------- --}}
        @if (! empty($categories))
            <div class="pg-chips" style="margin-bottom: 1.25rem;">
                <button type="button" class="pg-chip" :class="{ 'is-active': category === '' }" @click="category = ''">
                    {{ __('admin/plugins.filters.category_all') }}
                </button>
                @foreach ($categories as $cat)
                    <button type="button" class="pg-chip" :class="{ 'is-active': category === '{{ $cat }}' }" @click="category = (category === '{{ $cat }}') ? '' : '{{ $cat }}'">
                        #{{ $cat }}
                    </button>
                @endforeach
                <button type="button" class="pg-chip pg-chip-reset" x-show="query || filter !== 'all' || category" @click="resetFilters()" x-cloak>
                    <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                    {{ __('admin/plugins.filters.reset') }}
                </button>
            </div>
        @endif

        {{-- Section 1 : Officiels --------------------------------- --}}
        @if (! empty($officialMarketplace))
            <div class="pg-section" x-show="filter !== 'community'">
                <div class="pg-section-head">
                    <h2 class="pg-section-title">
                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2" style="color: rgb(var(--primary-300));"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 0 1-1.043 3.296 3.745 3.745 0 0 1-3.296 1.043A3.745 3.745 0 0 1 12 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 0 1-3.296-1.043 3.745 3.745 0 0 1-1.043-3.296A3.745 3.745 0 0 1 3 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 0 1 1.043-3.296 3.746 3.746 0 0 1 3.296-1.043A3.746 3.746 0 0 1 12 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 0 1 3.296 1.043 3.746 3.746 0 0 1 1.043 3.296A3.745 3.745 0 0 1 21 12Z" /></svg>
                        {{ __('admin/plugins.sections.official_marketplace') }}
                    </h2>
                    <span class="pg-section-count">({{ count($officialMarketplace) }})</span>
                    <p class="pg-section-hint">{{ __('admin/plugins.sections.official_marketplace_hint') }}</p>
                </div>
                <div class="pg-grid">
                    @foreach ($officialMarketplace as $mp)
                        @include('filament.pages.partials.marketplace-card', ['mp' => $mp, 'featured' => true])
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Section 2 : Communauté -------------------------------- --}}
        @if (! empty($communityMarketplace))
            <div class="pg-section" x-show="filter !== 'official'">
                <div class="pg-section-head">
                    <h2 class="pg-section-title">
                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="color: rgba(255,255,255,0.6);"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" /></svg>
                        {{ __('admin/plugins.sections.community_marketplace') }}
                    </h2>
                    <span class="pg-section-count">({{ count($communityMarketplace) }})</span>
                    <p class="pg-section-hint">{{ __('admin/plugins.sections.community_marketplace_hint') }}</p>
                </div>
                <div class="pg-grid">
                    @foreach ($communityMarketplace as $mp)
                        @include('filament.pages.partials.marketplace-card', ['mp' => $mp, 'featured' => false])
                    @endforeach
                </div>
            </div>
        @endif
    @endif
</div>
