<x-filament-panels::page>
    @php
        // ------------------------------------------------------------------
        // Page-level data assembly. The controller (App\Filament\Pages\Plugins)
        // exposes the raw lists; we derive the marketplace split + categories
        // here so the partials can stay presentation-only.
        // ------------------------------------------------------------------
        $stats = $this->getStats();
        $categories = $this->getCategories();
        $marketplaceEnabled = config('panel.marketplace.enabled', true);
        $officialMarketplace = collect($this->marketplacePlugins)->filter(fn ($p) => ! empty($p['official']))->values()->all();
        $communityMarketplace = collect($this->marketplacePlugins)->filter(fn ($p) => empty($p['official']))->values()->all();
    @endphp

    @include('filament.pages.partials.plugins.styles')

    {{--
        Root Alpine container — owns ALL UI state for this page :
          tab          — 'installed' | 'marketplace' (entangled with Livewire)
          statusFilter — 'all' | 'active' | 'inactive' | 'updates'
          query        — live search string
          filter       — 'all' | 'official' | 'community' (marketplace)
          category     — selected tag, '' = all

        All filtering is done client-side : the marketplace registry is
        small (max ~50 plugins) and instant filtering feels much better
        than round-tripping through Livewire on every keystroke.
    --}}
    <div
        class="pg-plugins"
        x-data="{
            tab: $wire.entangle('activeTab'),
            statusFilter: 'all',
            query: '',
            filter: 'all',
            category: '',
            normalize(s) { return (s ?? '').toString().toLowerCase().trim(); },
            matchesSearch(name, author, tags) {
                const q = this.normalize(this.query);
                if (!q) return true;
                const hay = [this.normalize(name), this.normalize(author), (tags || []).map(t => this.normalize(t)).join(' ')].join(' ');
                return hay.includes(q);
            },
            matchesInstalled(isActive, isInstalled, hasUpdate) {
                if (this.statusFilter === 'all') return true;
                if (this.statusFilter === 'active') return !!isActive;
                if (this.statusFilter === 'inactive') return !isActive && !!isInstalled;
                if (this.statusFilter === 'updates') return !!hasUpdate;
                return true;
            },
            matchesMarketplace(official, tags) {
                if (this.filter === 'official' && !official) return false;
                if (this.filter === 'community' && official) return false;
                if (this.category && !(tags || []).includes(this.category)) return false;
                return true;
            },
            resetFilters() { this.query = ''; this.filter = 'all'; this.category = ''; this.statusFilter = 'all'; },
        }"
    >
        @include('filament.pages.partials.plugins.stats', compact('stats', 'marketplaceEnabled'))

        @if ($stats['updates'] > 0)
            @include('filament.pages.partials.plugins.updates-banner', compact('stats'))
        @endif

        @include('filament.pages.partials.plugins.tabs', compact('plugins', 'marketplacePlugins', 'marketplaceEnabled'))

        @include('filament.pages.partials.plugins.installed-tab', compact('plugins', 'stats', 'marketplaceEnabled'))

        @if ($marketplaceEnabled)
            @include('filament.pages.partials.plugins.marketplace-tab', compact('marketplacePlugins', 'officialMarketplace', 'communityMarketplace', 'categories'))
        @endif

        @if ($settingsPluginId)
            @include('filament.pages.partials.plugins.settings-modal', compact('settingsPluginId', 'plugins'))
        @endif
    </div>
</x-filament-panels::page>
