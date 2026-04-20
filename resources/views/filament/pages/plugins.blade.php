<x-filament-panels::page>
    {{-- Tab navigation --}}
    <div style="display: flex; gap: 0.5rem; margin-bottom: 1.5rem;">
        <button
            wire:click="$set('activeTab', 'installed')"
            style="padding: 0.5rem 1rem; font-size: 0.875rem; font-weight: 500; border-radius: 0.5rem; cursor: pointer; transition: all 150ms; border: 1px solid {{ $activeTab === 'installed' ? 'rgb(var(--primary-500))' : 'rgba(255,255,255,0.1)' }}; background: {{ $activeTab === 'installed' ? 'rgba(var(--primary-500), 0.15)' : 'rgba(255,255,255,0.05)' }}; color: {{ $activeTab === 'installed' ? 'rgb(var(--primary-400))' : 'rgba(255,255,255,0.5)' }};"
        >
            Installed ({{ count($plugins) }})
        </button>
        @if(config('panel.marketplace.enabled', true))
        <button
            wire:click="$set('activeTab', 'marketplace')"
            style="padding: 0.5rem 1rem; font-size: 0.875rem; font-weight: 500; border-radius: 0.5rem; cursor: pointer; transition: all 150ms; border: 1px solid {{ $activeTab === 'marketplace' ? 'rgb(var(--primary-500))' : 'rgba(255,255,255,0.1)' }}; background: {{ $activeTab === 'marketplace' ? 'rgba(var(--primary-500), 0.15)' : 'rgba(255,255,255,0.05)' }}; color: {{ $activeTab === 'marketplace' ? 'rgb(var(--primary-400))' : 'rgba(255,255,255,0.5)' }};"
        >
            Marketplace ({{ count($marketplacePlugins) }})
        </button>
        @endif
    </div>

    {{-- Installed plugins tab --}}
    @if($activeTab === 'installed')
        @if(empty($plugins))
            <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 4rem 0; color: rgba(255,255,255,0.4);">
                <svg style="width: 3rem; height: 3rem; margin-bottom: 0.75rem; opacity: 0.3;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.25 6.087c0-.355.186-.676.401-.959.221-.29.349-.634.349-1.003 0-1.036-1.007-1.875-2.25-1.875S10.5 3.089 10.5 4.125c0 .369.128.713.349 1.003.215.283.401.604.401.959v0a.64.64 0 01-.657.643 48.39 48.39 0 01-4.163-.3c.186 1.613.293 3.25.315 4.907a.656.656 0 01-.658.663v0c-.355 0-.676-.186-.959-.401a1.647 1.647 0 00-1.003-.349c-1.036 0-1.875 1.007-1.875 2.25s.84 2.25 1.875 2.25c.369 0 .713-.128 1.003-.349.283-.215.604-.401.959-.401v0c.31 0 .555.26.532.57a48.04 48.04 0 01-.642 5.056c1.518.19 3.058.309 4.616.354a.64.64 0 00.657-.643v0c0-.355-.186-.676-.401-.959a1.647 1.647 0 01-.349-1.003c0-1.035 1.008-1.875 2.25-1.875 1.243 0 2.25.84 2.25 1.875 0 .369-.128.713-.349 1.003-.215.283-.4.604-.4.959v0c0 .333.277.599.61.58a48.1 48.1 0 005.427-.63 48.05 48.05 0 00.582-4.717.532.532 0 00-.533-.57v0c-.355 0-.676.186-.959.401-.29.221-.634.349-1.003.349-1.035 0-1.875-1.007-1.875-2.25s.84-2.25 1.875-2.25c.37 0 .713.128 1.003.349.283.215.604.401.96.401v0a.656.656 0 00.657-.663 48.42 48.42 0 00-.37-5.36c-1.886.342-3.81.574-5.766.689a.578.578 0 01-.61-.58v0z" />
                </svg>
                <p style="font-size: 0.875rem;">No plugins found.</p>
                <p style="font-size: 0.75rem; margin-top: 0.25rem; opacity: 0.6;">Drop plugin folders into <code style="padding: 0.125rem 0.375rem; background: rgba(255,255,255,0.08); border-radius: 0.25rem; font-size: 0.75rem;">plugins/</code></p>
            </div>
        @else
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1rem;">
                @foreach($plugins as $plugin)
                    <div style="border-radius: 0.75rem; border: 1px solid rgba(255,255,255,0.08); background: rgba(255,255,255,0.03); padding: 1.25rem; display: flex; flex-direction: column; gap: 0.75rem; transition: border-color 200ms;"
                         onmouseenter="this.style.borderColor='rgba(255,255,255,0.15)'"
                         onmouseleave="this.style.borderColor='rgba(255,255,255,0.08)'">

                        {{-- Header --}}
                        <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 0.75rem;">
                            <div style="min-width: 0;">
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <h3 style="font-size: 0.875rem; font-weight: 600; color: white; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $plugin['name'] }}</h3>
                                    <span style="font-size: 0.625rem; font-family: monospace; padding: 0.125rem 0.375rem; border-radius: 0.25rem; background: rgba(255,255,255,0.06); color: rgba(255,255,255,0.45);">
                                        v{{ $plugin['version'] }}
                                    </span>
                                </div>
                                @if($plugin['author'])
                                    <p style="font-size: 0.75rem; color: rgba(255,255,255,0.35); margin-top: 0.125rem;">{{ $plugin['author'] }}</p>
                                @endif
                            </div>

                            {{-- Status badge --}}
                            @if($plugin['is_active'])
                                <span style="flex-shrink: 0; display: inline-flex; align-items: center; gap: 0.375rem; border-radius: 9999px; padding: 0.25rem 0.625rem; font-size: 0.625rem; font-weight: 500; background: rgba(34,197,94,0.12); color: rgb(74,222,128);">
                                    <span style="height: 0.375rem; width: 0.375rem; border-radius: 9999px; background: rgb(74,222,128);"></span>
                                    Active
                                </span>
                            @elseif($plugin['is_installed'])
                                <span style="flex-shrink: 0; display: inline-flex; align-items: center; gap: 0.375rem; border-radius: 9999px; padding: 0.25rem 0.625rem; font-size: 0.625rem; font-weight: 500; background: rgba(234,179,8,0.12); color: rgb(250,204,21);">
                                    Inactive
                                </span>
                            @else
                                <span style="flex-shrink: 0; display: inline-flex; align-items: center; gap: 0.375rem; border-radius: 9999px; padding: 0.25rem 0.625rem; font-size: 0.625rem; font-weight: 500; background: rgba(255,255,255,0.06); color: rgba(255,255,255,0.4);">
                                    Not installed
                                </span>
                            @endif
                        </div>

                        {{-- Description --}}
                        @if($plugin['description'])
                            <p style="font-size: 0.75rem; color: rgba(255,255,255,0.45); line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">{{ $plugin['description'] }}</p>
                        @endif

                        {{-- Actions --}}
                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-top: auto; padding-top: 0.5rem;">
                            @if($plugin['is_active'])
                                <button wire:click="deactivatePlugin('{{ $plugin['id'] }}')"
                                    style="padding: 0.375rem 0.75rem; font-size: 0.75rem; font-weight: 500; border-radius: 0.5rem; cursor: pointer; background: rgba(239,68,68,0.12); color: rgb(248,113,113); border: 1px solid rgba(239,68,68,0.2); transition: all 150ms;"
                                    onmouseenter="this.style.background='rgba(239,68,68,0.2)'"
                                    onmouseleave="this.style.background='rgba(239,68,68,0.12)'">
                                    Deactivate
                                </button>
                                @if(!empty($plugin['settings_schema']))
                                    <button wire:click="openSettings('{{ $plugin['id'] }}')"
                                        style="padding: 0.375rem 0.75rem; font-size: 0.75rem; font-weight: 500; border-radius: 0.5rem; cursor: pointer; background: rgba(255,255,255,0.06); color: rgba(255,255,255,0.6); border: 1px solid rgba(255,255,255,0.1); transition: all 150ms;"
                                        onmouseenter="this.style.background='rgba(255,255,255,0.1)'"
                                        onmouseleave="this.style.background='rgba(255,255,255,0.06)'">
                                        Settings
                                    </button>
                                @endif
                            @else
                                <button wire:click="activatePlugin('{{ $plugin['id'] }}')"
                                    style="padding: 0.375rem 0.75rem; font-size: 0.75rem; font-weight: 500; border-radius: 0.5rem; cursor: pointer; background: rgba(34,197,94,0.12); color: rgb(74,222,128); border: 1px solid rgba(34,197,94,0.2); transition: all 150ms;"
                                    onmouseenter="this.style.background='rgba(34,197,94,0.2)'"
                                    onmouseleave="this.style.background='rgba(34,197,94,0.12)'">
                                    Activate
                                </button>
                                <button wire:click="uninstallPlugin('{{ $plugin['id'] }}')"
                                    wire:confirm="Are you sure you want to uninstall this plugin? This will delete all plugin files."
                                    style="padding: 0.375rem 0.75rem; font-size: 0.75rem; font-weight: 500; border-radius: 0.5rem; cursor: pointer; background: rgba(239,68,68,0.08); color: rgb(248,113,113); border: 1px solid rgba(239,68,68,0.15); transition: all 150ms;"
                                    onmouseenter="this.style.background='rgba(239,68,68,0.15)'"
                                    onmouseleave="this.style.background='rgba(239,68,68,0.08)'">
                                    Uninstall
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    @endif

    {{-- Marketplace tab --}}
    @if($activeTab === 'marketplace')
        @if(empty($marketplacePlugins))
            <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 4rem 0; color: rgba(255,255,255,0.4);">
                <svg style="width: 3rem; height: 3rem; margin-bottom: 0.75rem; opacity: 0.3;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418" />
                </svg>
                <p style="font-size: 0.875rem;">No plugins available in the marketplace.</p>
            </div>
        @else
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1rem;">
                @foreach($marketplacePlugins as $mp)
                    <div style="border-radius: 0.75rem; border: 1px solid rgba(255,255,255,0.08); background: rgba(255,255,255,0.03); padding: 1.25rem; display: flex; flex-direction: column; gap: 0.75rem; transition: border-color 200ms;"
                         onmouseenter="this.style.borderColor='rgba(255,255,255,0.15)'"
                         onmouseleave="this.style.borderColor='rgba(255,255,255,0.08)'">

                        <div style="min-width: 0;">
                            <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                                <h3 style="font-size: 0.875rem; font-weight: 600; color: white;">{{ $mp['name'] }}</h3>
                                <span style="font-size: 0.625rem; font-family: monospace; padding: 0.125rem 0.375rem; border-radius: 0.25rem; background: rgba(255,255,255,0.06); color: rgba(255,255,255,0.45);">
                                    v{{ $mp['version'] }}
                                </span>
                                @if($mp['official'] ?? false)
                                    <span style="font-size: 0.625rem; font-weight: 500; padding: 0.125rem 0.375rem; border-radius: 0.25rem; background: rgba(59,130,246,0.12); color: rgb(96,165,250);">
                                        Official
                                    </span>
                                @endif
                            </div>
                            @if($mp['author'] ?? null)
                                <p style="font-size: 0.75rem; color: rgba(255,255,255,0.35); margin-top: 0.125rem;">{{ $mp['author'] }}</p>
                            @endif
                        </div>

                        @if($mp['description'] ?? null)
                            <p style="font-size: 0.75rem; color: rgba(255,255,255,0.45); line-height: 1.5;">{{ $mp['description'] }}</p>
                        @endif

                        @if(!empty($mp['tags'] ?? []))
                            <div style="display: flex; flex-wrap: wrap; gap: 0.25rem;">
                                @foreach($mp['tags'] as $tag)
                                    <span style="font-size: 0.625rem; padding: 0.125rem 0.375rem; border-radius: 0.25rem; background: rgba(255,255,255,0.06); color: rgba(255,255,255,0.35);">{{ $tag }}</span>
                                @endforeach
                            </div>
                        @endif

                        <div style="margin-top: auto; padding-top: 0.5rem;">
                            <button wire:click="installFromMarketplace('{{ $mp['id'] }}')"
                                wire:loading.attr="disabled"
                                style="padding: 0.375rem 0.75rem; font-size: 0.75rem; font-weight: 500; border-radius: 0.5rem; cursor: pointer; background: rgba(var(--primary-500), 0.12); color: rgb(var(--primary-400)); border: 1px solid rgba(var(--primary-500), 0.2); transition: all 150ms;"
                                onmouseenter="this.style.background='rgba(var(--primary-500), 0.2)'"
                                onmouseleave="this.style.background='rgba(var(--primary-500), 0.12)'">
                                <span wire:loading.remove wire:target="installFromMarketplace('{{ $mp['id'] }}')">Install</span>
                                <span wire:loading wire:target="installFromMarketplace('{{ $mp['id'] }}')">Installing...</span>
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    @endif

    {{-- Settings modal --}}
    @if($settingsPluginId)
        <x-filament::modal id="plugin-settings" width="lg">
            <x-slot name="heading">Plugin Settings: {{ $settingsPluginId }}</x-slot>

            <form wire:submit="saveSettings">
                {{ $this->form }}

                <div style="margin-top: 1rem; display: flex; justify-content: flex-end; gap: 0.5rem;">
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
