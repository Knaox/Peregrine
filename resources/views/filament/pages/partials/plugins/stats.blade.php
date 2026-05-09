{{--
    Hero header for /admin/plugins. Wraps the page title, subtitle and
    the four big-number stat cards in a single panel with a subtle
    primary-tinted radial gradient background. The mesh-gradient via
    ::before is the difference between "yet another admin grid" and
    "the page feels alive".

    State (tab + statusFilter) is owned by the page-level Alpine root.

    Variables :
      - $stats              : array{installed,active,updates,marketplace}
      - $marketplaceEnabled : bool
--}}
<div class="pg-hero">
    <div class="pg-hero-inner">
        <div>
            <h1 class="pg-hero-title">{{ __('admin/plugins.page.title') }}</h1>
            <p class="pg-hero-sub">{{ __('admin/plugins.page.subtitle') }}</p>
        </div>

        <div class="pg-stats">
            {{-- Installed (primary accent) --}}
            <x-pg.stat
                accent="primary"
                :value="$stats['installed']"
                :label="__('admin/plugins.stats.installed')"
                x-on:click="tab = 'installed'; statusFilter = 'all'"
                ::class="{ 'is-active': tab === 'installed' && statusFilter === 'all' }"
            >
                <x-slot name="icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.25 6.087c0-.355.186-.676.401-.959.221-.29.349-.634.349-1.003 0-1.036-1.007-1.875-2.25-1.875S10.5 3.089 10.5 4.125c0 .369.128.713.349 1.003.215.283.401.604.401.959v0a.64.64 0 0 1-.657.643 48.39 48.39 0 0 1-4.163-.3c.186 1.613.293 3.25.315 4.907a.656.656 0 0 1-.658.663v0c-.355 0-.676-.186-.959-.401a1.647 1.647 0 0 0-1.003-.349c-1.036 0-1.875 1.007-1.875 2.25s.84 2.25 1.875 2.25c.369 0 .713-.128 1.003-.349.283-.215.604-.401.959-.401v0c.31 0 .555.26.532.57a48.04 48.04 0 0 1-.642 5.056c1.518.19 3.058.309 4.616.354a.64.64 0 0 0 .657-.643v0c0-.355-.186-.676-.401-.959a1.647 1.647 0 0 1-.349-1.003c0-1.035 1.008-1.875 2.25-1.875 1.243 0 2.25.84 2.25 1.875 0 .369-.128.713-.349 1.003-.215.283-.4.604-.4.959v0c0 .333.277.599.61.58a48.1 48.1 0 0 0 5.427-.63 48.05 48.05 0 0 0 .582-4.717.532.532 0 0 0-.533-.57v0c-.355 0-.676.186-.959.401-.29.221-.634.349-1.003.349-1.035 0-1.875-1.007-1.875-2.25s.84-2.25 1.875-2.25c.37 0 .713.128 1.003.349.283.215.604.401.96.401v0a.656.656 0 0 0 .657-.663 48.42 48.42 0 0 0-.37-5.36c-1.886.342-3.81.574-5.766.689a.578.578 0 0 1-.61-.58v0Z" />
                    </svg>
                </x-slot>
            </x-pg.stat>

            {{-- Active (success accent) --}}
            <x-pg.stat
                accent="success"
                :value="$stats['active']"
                :label="__('admin/plugins.stats.active')"
                x-on:click="tab = 'installed'; statusFilter = 'active'"
                ::class="{ 'is-active': tab === 'installed' && statusFilter === 'active' }"
            >
                <x-slot name="icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </x-slot>
            </x-pg.stat>

            {{-- Updates (warning accent) --}}
            <x-pg.stat
                accent="warning"
                :value="$stats['updates']"
                :label="__('admin/plugins.stats.updates')"
                x-on:click="tab = 'installed'; statusFilter = 'updates'"
                ::class="{ 'is-active': tab === 'installed' && statusFilter === 'updates' }"
            >
                <x-slot name="icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                    </svg>
                </x-slot>
            </x-pg.stat>

            {{-- Marketplace (info / indigo accent) --}}
            @if ($marketplaceEnabled)
                <x-pg.stat
                    accent="info"
                    :value="$stats['marketplace']"
                    :label="__('admin/plugins.stats.in_marketplace')"
                    x-on:click="tab = 'marketplace'"
                    ::class="{ 'is-active': tab === 'marketplace' }"
                >
                    <x-slot name="icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 0 0 3.75.614m-16.5 0a3.004 3.004 0 0 1-.621-4.72l1.189-1.19A1.5 1.5 0 0 1 5.378 3h13.243a1.5 1.5 0 0 1 1.06.44l1.19 1.189a3.001 3.001 0 0 1-.621 4.72M6.75 18h3.75a.75.75 0 0 0 .75-.75V13.5a.75.75 0 0 0-.75-.75H6.75a.75.75 0 0 0-.75.75v3.75c0 .415.336.75.75.75Z" />
                        </svg>
                    </x-slot>
                </x-pg.stat>
            @endif
        </div>
    </div>
</div>
