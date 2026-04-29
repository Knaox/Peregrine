<x-filament-panels::page>
    @php
        $tabClasses = function (bool $active) {
            $base = 'inline-flex items-center gap-2 rounded-lg border px-3.5 py-2 text-sm font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/60';
            return $active
                ? $base . ' border-primary-500/60 bg-primary-500/15 text-primary-600 dark:text-primary-300'
                : $base . ' border-gray-200/60 bg-white/40 text-gray-600 hover:bg-white dark:border-white/10 dark:bg-white/5 dark:text-gray-400 dark:hover:bg-white/10';
        };

        $cardClasses = 'flex flex-col gap-3 rounded-xl border border-gray-200/60 bg-white/40 p-5 transition hover:border-gray-300 hover:shadow-sm dark:border-white/5 dark:bg-white/[0.03] dark:hover:border-white/15';

        $statusPill = function (string $variant, string $label) {
            $map = [
                'active'    => 'bg-emerald-500/12 text-emerald-700 dark:text-emerald-300',
                'inactive'  => 'bg-amber-500/12 text-amber-700 dark:text-amber-300',
                'idle'      => 'bg-gray-500/10 text-gray-600 dark:text-gray-400',
                'installed' => 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300',
                'update'    => 'bg-amber-500/15 text-amber-700 dark:text-amber-300',
            ];
            return '<span class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-2.5 py-1 text-[0.6875rem] font-medium ' . ($map[$variant] ?? $map['idle']) . '">' . e($label) . '</span>';
        };

        $btnClasses = function (string $variant) {
            $base = 'inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-medium transition focus:outline-none focus-visible:ring-2 disabled:cursor-not-allowed disabled:opacity-50';
            return match ($variant) {
                'danger' => $base . ' border-rose-300/40 bg-rose-500/10 text-rose-600 hover:bg-rose-500/20 focus-visible:ring-rose-400/60 dark:text-rose-300',
                'success' => $base . ' border-emerald-300/40 bg-emerald-500/10 text-emerald-700 hover:bg-emerald-500/20 focus-visible:ring-emerald-400/60 dark:text-emerald-300',
                'primary' => $base . ' border-primary-300/40 bg-primary-500/10 text-primary-700 hover:bg-primary-500/20 focus-visible:ring-primary-400/60 dark:text-primary-300',
                'warning' => $base . ' border-transparent bg-amber-500 text-gray-900 hover:bg-amber-400 focus-visible:ring-amber-400/60',
                'indigo' => $base . ' border-indigo-300/40 bg-indigo-500/10 text-indigo-700 hover:bg-indigo-500/20 focus-visible:ring-indigo-400/60 dark:text-indigo-300',
                default => $base . ' border-gray-200/60 bg-white/40 text-gray-700 hover:bg-white dark:border-white/10 dark:bg-white/5 dark:text-gray-200 dark:hover:bg-white/10',
            };
        };
    @endphp

    {{-- Tab navigation --}}
    <div class="mb-6 flex flex-wrap gap-2" role="tablist" aria-label="Plugin views">
        <button
            type="button"
            role="tab"
            aria-selected="{{ $activeTab === 'installed' ? 'true' : 'false' }}"
            wire:click="$set('activeTab', 'installed')"
            class="{{ $tabClasses($activeTab === 'installed') }}"
        >
            <x-filament::icon icon="heroicon-o-puzzle-piece" class="h-4 w-4" />
            Installed
            <span class="ml-1 rounded-full bg-current/10 px-1.5 py-0.5 text-[0.625rem] font-semibold tabular-nums">{{ count($plugins) }}</span>
        </button>
        @if (config('panel.marketplace.enabled', true))
            <button
                type="button"
                role="tab"
                aria-selected="{{ $activeTab === 'marketplace' ? 'true' : 'false' }}"
                wire:click="$set('activeTab', 'marketplace')"
                class="{{ $tabClasses($activeTab === 'marketplace') }}"
            >
                <x-filament::icon icon="heroicon-o-globe-alt" class="h-4 w-4" />
                Marketplace
                <span class="ml-1 rounded-full bg-current/10 px-1.5 py-0.5 text-[0.625rem] font-semibold tabular-nums">{{ count($marketplacePlugins) }}</span>
            </button>
        @endif
    </div>

    {{-- Installed plugins tab --}}
    @if ($activeTab === 'installed')
        @if (empty($plugins))
            <div class="flex flex-col items-center justify-center gap-2 py-16 text-gray-500 dark:text-gray-400">
                <x-filament::icon icon="heroicon-o-puzzle-piece" class="h-12 w-12 opacity-30" />
                <p class="text-sm">No plugins found.</p>
                <p class="text-xs opacity-70">Drop plugin folders into <code class="rounded bg-gray-200/60 px-1.5 py-0.5 font-mono text-xs dark:bg-white/10">plugins/</code></p>
            </div>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($plugins as $plugin)
                    <article class="{{ $cardClasses }}" aria-labelledby="plugin-{{ $plugin['id'] }}-name">
                        {{-- Header --}}
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex min-w-0 items-start gap-3">
                                @include('filament.pages.partials.plugin-logo', ['official' => $plugin['official'] ?? false])
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 id="plugin-{{ $plugin['id'] }}-name" class="text-sm font-semibold text-gray-900 dark:text-gray-50">{{ $plugin['name'] }}</h3>
                                        <span class="rounded bg-gray-200/60 px-1.5 py-0.5 font-mono text-[0.625rem] text-gray-600 dark:bg-white/8 dark:text-gray-400">
                                            v{{ $plugin['version'] }}
                                        </span>
                                        @if ($plugin['official'] ?? false)
                                            @include('filament.pages.partials.plugin-certified-badge')
                                        @endif
                                    </div>
                                    @if ($plugin['author'])
                                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-500">{{ $plugin['author'] }}</p>
                                    @endif
                                </div>
                            </div>

                            @if ($plugin['is_active'])
                                {!! $statusPill('active', 'Active') !!}
                            @elseif ($plugin['is_installed'])
                                {!! $statusPill('inactive', 'Inactive') !!}
                            @else
                                {!! $statusPill('idle', 'Not installed') !!}
                            @endif
                        </div>

                        @if ($plugin['description'])
                            <p class="line-clamp-2 text-xs leading-relaxed text-gray-600 dark:text-gray-400">{{ $plugin['description'] }}</p>
                        @endif

                        @if ($plugin['update_available'] ?? false)
                            <div class="flex items-center justify-between gap-2 rounded-lg border border-amber-300/40 bg-amber-500/10 px-3 py-2">
                                <span class="text-[0.6875rem] text-amber-800 dark:text-amber-300">
                                    Update: v{{ $plugin['version'] }} → v{{ $plugin['latest_version'] }}
                                </span>
                                <button
                                    type="button"
                                    wire:click="updatePlugin('{{ $plugin['id'] }}')"
                                    wire:loading.attr="disabled"
                                    class="{{ $btnClasses('warning') }}"
                                >
                                    <span wire:loading.remove wire:target="updatePlugin('{{ $plugin['id'] }}')">Update</span>
                                    <span wire:loading wire:target="updatePlugin('{{ $plugin['id'] }}')">…</span>
                                </button>
                            </div>
                        @endif

                        <div class="mt-auto flex flex-wrap items-center gap-2 pt-2">
                            @if ($plugin['is_active'])
                                <button type="button" wire:click="deactivatePlugin('{{ $plugin['id'] }}')" class="{{ $btnClasses('danger') }}">
                                    Deactivate
                                </button>
                                @if (! empty($plugin['settings_schema']))
                                    <button type="button" wire:click="openSettings('{{ $plugin['id'] }}')" class="{{ $btnClasses('default') }}">
                                        <x-filament::icon icon="heroicon-o-cog-6-tooth" class="h-3.5 w-3.5" />
                                        Settings
                                    </button>
                                @endif
                                @if (! empty($plugin['manage_url']))
                                    <a href="{{ $plugin['manage_url'] }}" class="{{ $btnClasses('indigo') }}">
                                        <x-filament::icon icon="heroicon-o-cog-8-tooth" class="h-3.5 w-3.5" />
                                        Configure
                                    </a>
                                @endif
                            @else
                                <button type="button" wire:click="activatePlugin('{{ $plugin['id'] }}')" class="{{ $btnClasses('success') }}">
                                    Activate
                                </button>
                                <button
                                    type="button"
                                    wire:click="uninstallPlugin('{{ $plugin['id'] }}')"
                                    wire:confirm="Are you sure you want to uninstall this plugin? This will delete all plugin files."
                                    class="{{ $btnClasses('danger') }}"
                                >
                                    Uninstall
                                </button>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    @endif

    {{-- Marketplace tab --}}
    @if ($activeTab === 'marketplace')
        <div class="mb-3 flex justify-end">
            <button type="button" wire:click="refreshMarketplace" class="{{ $btnClasses('default') }}">
                <x-filament::icon icon="heroicon-o-arrow-path" class="h-3.5 w-3.5" />
                <span wire:loading.remove wire:target="refreshMarketplace">Refresh registry</span>
                <span wire:loading wire:target="refreshMarketplace">Refreshing…</span>
            </button>
        </div>

        @if (empty($marketplacePlugins))
            <div class="flex flex-col items-center justify-center gap-2 py-16 text-gray-500 dark:text-gray-400">
                <x-filament::icon icon="heroicon-o-globe-alt" class="h-12 w-12 opacity-30" />
                <p class="text-sm">No plugins found in the registry.</p>
                <p class="text-xs opacity-70">Check <code class="rounded bg-gray-200/60 px-1.5 py-0.5 font-mono text-xs dark:bg-white/10">{{ config('panel.marketplace.registry_url') }}</code></p>
            </div>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($marketplacePlugins as $mp)
                    <article class="{{ $cardClasses }}" aria-labelledby="mp-{{ $mp['id'] }}-name">
                        <div class="flex min-w-0 items-start gap-3">
                            @include('filament.pages.partials.plugin-logo', ['official' => $mp['official'] ?? false])
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 id="mp-{{ $mp['id'] }}-name" class="text-sm font-semibold text-gray-900 dark:text-gray-50">{{ $mp['name'] }}</h3>
                                    <span class="rounded bg-gray-200/60 px-1.5 py-0.5 font-mono text-[0.625rem] text-gray-600 dark:bg-white/8 dark:text-gray-400">
                                        v{{ $mp['version'] }}
                                    </span>
                                    @if ($mp['official'] ?? false)
                                        @include('filament.pages.partials.plugin-certified-badge')
                                    @endif
                                    @if ($mp['is_installed'] ?? false)
                                        @if ($mp['update_available'] ?? false)
                                            {!! $statusPill('update', 'Update v' . e($mp['installed_version']) . ' → v' . e($mp['version'])) !!}
                                        @else
                                            {!! $statusPill('installed', 'Installed') !!}
                                        @endif
                                    @endif
                                </div>
                                @if ($mp['author'] ?? null)
                                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-500">{{ $mp['author'] }}</p>
                                @endif
                            </div>
                        </div>

                        @if ($mp['description'] ?? null)
                            <p class="text-xs leading-relaxed text-gray-600 dark:text-gray-400">{{ $mp['description'] }}</p>
                        @endif

                        @if (! empty($mp['tags'] ?? []))
                            <div class="flex flex-wrap gap-1">
                                @foreach ($mp['tags'] as $tag)
                                    <span class="rounded bg-gray-200/60 px-1.5 py-0.5 text-[0.625rem] text-gray-600 dark:bg-white/8 dark:text-gray-400">{{ $tag }}</span>
                                @endforeach
                            </div>
                        @endif

                        <div class="mt-auto flex flex-wrap items-center gap-2 pt-2">
                            @if ($mp['is_installed'] ?? false)
                                @if ($mp['update_available'] ?? false)
                                    <button type="button" wire:click="updatePlugin('{{ $mp['id'] }}')" wire:loading.attr="disabled" class="{{ $btnClasses('warning') }}">
                                        <span wire:loading.remove wire:target="updatePlugin('{{ $mp['id'] }}')">Update to v{{ $mp['version'] }}</span>
                                        <span wire:loading wire:target="updatePlugin('{{ $mp['id'] }}')">Updating…</span>
                                    </button>
                                @else
                                    <span class="inline-flex items-center gap-1.5 text-xs text-emerald-700 dark:text-emerald-400">
                                        <x-filament::icon icon="heroicon-o-check-circle" class="h-4 w-4" />
                                        Installed
                                    </span>
                                @endif
                            @else
                                <button type="button" wire:click="installFromMarketplace('{{ $mp['id'] }}')" wire:loading.attr="disabled" class="{{ $btnClasses('primary') }}">
                                    <span wire:loading.remove wire:target="installFromMarketplace('{{ $mp['id'] }}')">Install</span>
                                    <span wire:loading wire:target="installFromMarketplace('{{ $mp['id'] }}')">Installing…</span>
                                </button>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    @endif

    {{-- Settings modal --}}
    @if ($settingsPluginId)
        <x-filament::modal id="plugin-settings" width="lg">
            <x-slot name="heading">Plugin Settings: {{ $settingsPluginId }}</x-slot>

            <form wire:submit="saveSettings">
                {{ $this->form }}

                <div class="mt-4 flex justify-end gap-2">
                    <x-filament::button type="button" color="gray" x-on:click="$dispatch('close-modal', { id: 'plugin-settings' })">
                        Cancel
                    </x-filament::button>
                    <x-filament::button type="submit">
                        Save
                    </x-filament::button>
                </div>
            </form>
        </x-filament::modal>
    @endif
</x-filament-panels::page>
