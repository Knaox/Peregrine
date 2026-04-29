<x-filament-panels::page>
    @php
        $card = 'border-radius: 0.75rem; border: 1px solid rgba(255,255,255,0.08); background: rgba(255,255,255,0.03); padding: 1.5rem;';
        $label = 'font-size: 0.6875rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; color: rgba(255,255,255,0.5); margin: 0;';
        $statusBase = 'flex: 1; padding: 0.625rem 0.875rem; border-radius: 0.5rem; font-size: 0.875rem;';
    @endphp

    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
        {{-- Version header --}}
        <div style="{{ $card }}">
            <div style="display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 1rem;">
                <div style="display: flex; flex-direction: column; gap: 0.25rem;">
                    <p style="{{ $label }}">{{ __('admin.about.installed_version') }}</p>
                    <div style="display: flex; align-items: center; gap: 0.625rem;">
                        <span style="font-family: monospace; font-size: 1.5rem; font-weight: 700; color: rgba(255,255,255,0.95);">
                            {{ $currentVersion }}
                        </span>
                        @if ($isDocker)
                            <span style="display: inline-flex; align-items: center; padding: 0.125rem 0.625rem; border-radius: 9999px; font-size: 0.6875rem; font-weight: 500; background: rgba(var(--primary-500), 0.15); color: rgb(var(--primary-400));">
                                Docker
                            </span>
                        @endif
                    </div>
                </div>

                <div style="display: flex; flex-direction: column; gap: 0.25rem; text-align: right;">
                    <p style="{{ $label }}">{{ __('admin.about.latest_release') }}</p>
                    <div style="display: flex; align-items: center; gap: 0.5rem; justify-content: flex-end;">
                        @if ($latestVersion)
                            <span style="font-family: monospace; font-size: 1.125rem; font-weight: 600; color: rgba(255,255,255,0.95);">
                                {{ $latestVersion }}
                            </span>
                            @if ($latestReleaseUrl)
                                <a href="{{ $latestReleaseUrl }}" target="_blank" rel="noopener"
                                    style="font-size: 0.75rem; color: rgb(var(--primary-400)); text-decoration: none;">
                                    {{ __('admin.about.view_on_github') }} ↗
                                </a>
                            @endif
                        @else
                            <span style="font-size: 0.875rem; color: rgba(255,255,255,0.5);">
                                {{ __('admin.about.no_release') }}
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            <div style="display: flex; align-items: center; gap: 0.75rem; margin-top: 1rem;">
                @if ($checkError)
                    <div style="{{ $statusBase }} background: rgba(239, 68, 68, 0.1); color: rgb(248, 113, 113); border: 1px solid rgba(239, 68, 68, 0.2);">
                        {{ __('admin.about.check_error', ['error' => $checkError]) }}
                    </div>
                @elseif (! $latestVersion)
                    <div style="{{ $statusBase }} background: rgba(255,255,255,0.04); color: rgba(255,255,255,0.6); border: 1px solid rgba(255,255,255,0.08);">
                        {{ __('admin.about.dev_build_warning') }}
                    </div>
                @elseif ($updateAvailable)
                    <div style="{{ $statusBase }} background: rgba(245, 158, 11, 0.1); color: rgb(252, 211, 77); border: 1px solid rgba(245, 158, 11, 0.2);">
                        {{ __('admin.about.update_available') }}
                    </div>
                @else
                    <div style="{{ $statusBase }} background: rgba(34, 197, 94, 0.1); color: rgb(134, 239, 172); border: 1px solid rgba(34, 197, 94, 0.2);">
                        {{ __('admin.about.up_to_date') }}
                    </div>
                @endif

                <button wire:click="refreshUpdate" type="button"
                    style="display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.5rem 0.875rem; font-size: 0.8125rem; font-weight: 500; border-radius: 0.5rem; cursor: pointer; background: rgba(255,255,255,0.06); color: rgba(255,255,255,0.85); border: 1px solid rgba(255,255,255,0.1);">
                    <svg style="width: 0.875rem; height: 0.875rem;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    {{ __('admin.about.check_again') }}
                </button>
            </div>
        </div>

        @if ($updateAvailable)
            <div style="{{ $card }}">
                <div style="margin-bottom: 1.25rem;">
                    <h2 style="font-size: 1rem; font-weight: 600; color: rgba(255,255,255,0.95); margin: 0 0 0.25rem 0;">
                        {{ $isDocker ? __('admin.about.docker_commands') : __('admin.about.manual_commands') }}
                    </h2>
                    <p style="font-size: 0.8125rem; color: rgba(255,255,255,0.5); margin: 0;">
                        {{ __('admin.about.commands_help') }}
                    </p>
                </div>

                <ol style="display: flex; flex-direction: column; gap: 0.875rem; padding: 0; margin: 0; list-style: none;">
                    @foreach ($this->getUpdateCommands() as $i => $cmd)
                        <li style="border-radius: 0.5rem; border: 1px solid rgba(255,255,255,0.06); background: rgba(255,255,255,0.02); padding: 0.875rem;">
                            <div style="display: flex; align-items: flex-start; gap: 0.625rem; margin-bottom: 0.625rem;">
                                <span style="display: inline-flex; flex-shrink: 0; width: 1.5rem; height: 1.5rem; align-items: center; justify-content: center; border-radius: 9999px; background: rgb(var(--primary-500)); color: white; font-size: 0.6875rem; font-weight: 700;">
                                    {{ $i + 1 }}
                                </span>
                                <div style="min-width: 0; flex: 1;">
                                    <p style="font-size: 0.875rem; font-weight: 500; color: rgba(255,255,255,0.9); margin: 0;">
                                        {{ $cmd['title'] }}
                                    </p>
                                    <p style="font-size: 0.75rem; color: rgba(255,255,255,0.5); margin: 0.125rem 0 0 0;">
                                        {{ $cmd['description'] }}
                                    </p>
                                </div>
                            </div>

                            <div style="position: relative; border-radius: 0.375rem; background: rgba(0,0,0,0.4); padding: 0.75rem 3rem 0.75rem 0.875rem;">
                                <code id="cmd-{{ $i }}" style="display: block; font-family: monospace; font-size: 0.8125rem; color: rgba(255,255,255,0.85); user-select: all; word-break: break-all; white-space: pre-wrap;">{{ $cmd['command'] }}</code>
                                <button type="button"
                                    aria-label="{{ __('admin.about.copy_command') }}"
                                    onclick="const c=document.getElementById('cmd-{{ $i }}').textContent.trim(); navigator.clipboard.writeText(c); const s=this.querySelector('.copy-label'); const o=s.textContent; s.textContent='{{ __('admin.about.copied') }}'; setTimeout(()=>{s.textContent=o;},1500);"
                                    style="position: absolute; top: 0.5rem; right: 0.5rem; display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.25rem 0.625rem; border-radius: 0.25rem; background: rgba(255,255,255,0.1); color: rgba(255,255,255,0.85); border: 1px solid rgba(255,255,255,0.08); font-size: 0.6875rem; cursor: pointer;">
                                    <svg style="width: 0.75rem; height: 0.75rem;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                    </svg>
                                    <span class="copy-label">{{ __('admin.about.copy') }}</span>
                                </button>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>
        @endif

        {{-- About metadata --}}
        <div style="{{ $card }}">
            <h2 style="{{ $label }} margin-bottom: 0.875rem;">{{ __('admin.about.about_peregrine') }}</h2>
            <dl style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 0.875rem; margin: 0;">
                <div>
                    <dt style="font-size: 0.75rem; color: rgba(255,255,255,0.5); margin-bottom: 0.125rem;">{{ __('admin.about.repository') }}</dt>
                    <dd style="font-family: monospace; font-size: 0.875rem; color: rgba(255,255,255,0.9); margin: 0;">
                        {{ config('panel.update_repo', 'Knaox/Peregrine') }}
                    </dd>
                </div>
                <div>
                    <dt style="font-size: 0.75rem; color: rgba(255,255,255,0.5); margin-bottom: 0.125rem;">{{ __('admin.about.license') }}</dt>
                    <dd style="font-size: 0.875rem; color: rgba(255,255,255,0.9); margin: 0;">{{ __('admin.about.license_value') }}</dd>
                </div>
                @if ($latestReleasedAt)
                    <div>
                        <dt style="font-size: 0.75rem; color: rgba(255,255,255,0.5); margin-bottom: 0.125rem;">{{ __('admin.about.latest_release_date') }}</dt>
                        <dd style="font-size: 0.875rem; color: rgba(255,255,255,0.9); margin: 0;">
                            {{ \Carbon\Carbon::parse($latestReleasedAt)->locale(app()->getLocale())->translatedFormat('j F Y') }}
                        </dd>
                    </div>
                @endif
                <div>
                    <dt style="font-size: 0.75rem; color: rgba(255,255,255,0.5); margin-bottom: 0.125rem;">{{ __('admin.about.install_mode') }}</dt>
                    <dd style="font-size: 0.875rem; color: rgba(255,255,255,0.9); margin: 0;">
                        {{ $isDocker ? 'Docker' : __('admin.about.bare_metal') }}
                    </dd>
                </div>
            </dl>
        </div>
    </div>
</x-filament-panels::page>
