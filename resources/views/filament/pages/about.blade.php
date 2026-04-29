<x-filament-panels::page>
    @php
        $cardClasses = 'rounded-xl border border-gray-200/60 bg-white/40 p-6 shadow-sm dark:border-white/5 dark:bg-white/[0.02]';
        $labelClasses = 'text-[0.6875rem] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400';
    @endphp

    <div class="flex flex-col gap-6">
        {{-- Version header card --}}
        <div class="{{ $cardClasses }}">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex flex-col gap-1">
                    <p class="{{ $labelClasses }}">Installed version</p>
                    <div class="flex items-center gap-2.5">
                        <span class="font-mono text-2xl font-bold text-gray-900 dark:text-gray-50">
                            {{ $currentVersion }}
                        </span>
                        @if ($isDocker)
                            <span class="inline-flex items-center rounded-full bg-primary-500/15 px-2.5 py-0.5 text-[0.6875rem] font-medium text-primary-600 dark:text-primary-400">
                                Docker
                            </span>
                        @endif
                    </div>
                </div>

                <div class="flex flex-col gap-1 text-right">
                    <p class="{{ $labelClasses }}">Latest release</p>
                    <div class="flex items-center justify-end gap-2">
                        @if ($latestVersion)
                            <span class="font-mono text-lg font-semibold text-gray-900 dark:text-gray-50">
                                {{ $latestVersion }}
                            </span>
                            @if ($latestReleaseUrl)
                                <a
                                    href="{{ $latestReleaseUrl }}"
                                    target="_blank"
                                    rel="noopener"
                                    class="text-xs text-primary-600 underline-offset-2 hover:underline dark:text-primary-400"
                                >
                                    View on GitHub ↗
                                </a>
                            @endif
                        @else
                            <span class="text-sm text-gray-500 dark:text-gray-400">
                                No release published yet
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-3">
                @if ($checkError)
                    <div class="flex-1 rounded-lg border border-rose-200/60 bg-rose-50 px-3.5 py-2.5 text-sm text-rose-700 dark:border-rose-400/20 dark:bg-rose-500/10 dark:text-rose-300">
                        Couldn't check for updates: {{ $checkError }}
                    </div>
                @elseif (! $latestVersion)
                    <div class="flex-1 rounded-lg border border-gray-200/60 bg-gray-50 px-3.5 py-2.5 text-sm text-gray-600 dark:border-white/5 dark:bg-white/5 dark:text-gray-400">
                        The upstream repository hasn't published a release yet — you're on a development build.
                    </div>
                @elseif ($updateAvailable)
                    <div class="flex-1 rounded-lg border border-amber-200/60 bg-amber-50 px-3.5 py-2.5 text-sm text-amber-800 dark:border-amber-400/20 dark:bg-amber-500/10 dark:text-amber-300">
                        A newer version is available. Follow the commands below to upgrade.
                    </div>
                @else
                    <div class="flex-1 rounded-lg border border-emerald-200/60 bg-emerald-50 px-3.5 py-2.5 text-sm text-emerald-700 dark:border-emerald-400/20 dark:bg-emerald-500/10 dark:text-emerald-300">
                        You're running the latest version.
                    </div>
                @endif

                <button
                    wire:click="refreshUpdate"
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200/60 bg-white/60 px-3.5 py-2 text-sm font-medium text-gray-700 transition hover:bg-white dark:border-white/10 dark:bg-white/5 dark:text-gray-200 dark:hover:bg-white/10"
                >
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    Check again
                </button>
            </div>
        </div>

        {{-- Update commands (only if update available) --}}
        @if ($updateAvailable)
            <div class="{{ $cardClasses }}">
                <div class="mb-5">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-gray-50">
                        {{ $isDocker ? 'Docker update commands' : 'Manual update commands' }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Run these commands in order on the host machine.
                    </p>
                </div>

                <ol class="flex list-none flex-col gap-3.5 p-0">
                    @foreach ($this->getUpdateCommands() as $i => $cmd)
                        <li class="rounded-lg border border-gray-200/60 bg-white/30 p-3.5 dark:border-white/5 dark:bg-white/[0.02]">
                            <div class="mb-2.5 flex items-start gap-2.5">
                                <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary-500 text-[0.6875rem] font-bold text-white">
                                    {{ $i + 1 }}
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                        {{ $cmd['title'] }}
                                    </p>
                                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                        {{ $cmd['description'] }}
                                    </p>
                                </div>
                            </div>

                            <div class="relative rounded-md bg-gray-950/80 px-3.5 py-3 pr-12">
                                <code id="cmd-{{ $i }}" class="block whitespace-pre-wrap break-all font-mono text-sm text-gray-100 select-all">{{ $cmd['command'] }}</code>
                                <button
                                    type="button"
                                    aria-label="Copy command"
                                    onclick="const c=document.getElementById('cmd-{{ $i }}').textContent.trim(); navigator.clipboard.writeText(c); const s=this.querySelector('.copy-label'); const o=s.textContent; s.textContent='Copied'; setTimeout(()=>{s.textContent=o;},1500);"
                                    class="absolute right-2 top-2 inline-flex items-center gap-1 rounded border border-white/10 bg-white/10 px-2.5 py-1 text-[0.6875rem] text-gray-200 transition hover:bg-white/20"
                                >
                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                    </svg>
                                    <span class="copy-label">Copy</span>
                                </button>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>
        @endif

        {{-- About metadata grid --}}
        <div class="{{ $cardClasses }}">
            <h2 class="{{ $labelClasses }} mb-3.5">About Peregrine</h2>
            <dl class="grid gap-3.5 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <dt class="text-xs text-gray-500 dark:text-gray-400">Repository</dt>
                    <dd class="mt-0.5 font-mono text-sm text-gray-900 dark:text-gray-100">
                        {{ config('panel.update_repo', 'Knaox/Peregrine') }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500 dark:text-gray-400">License</dt>
                    <dd class="mt-0.5 text-sm text-gray-900 dark:text-gray-100">MIT — open source</dd>
                </div>
                @if ($latestReleasedAt)
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">Latest release date</dt>
                        <dd class="mt-0.5 text-sm text-gray-900 dark:text-gray-100">
                            {{ \Carbon\Carbon::parse($latestReleasedAt)->format('M j, Y') }}
                        </dd>
                    </div>
                @endif
                <div>
                    <dt class="text-xs text-gray-500 dark:text-gray-400">Install mode</dt>
                    <dd class="mt-0.5 text-sm text-gray-900 dark:text-gray-100">
                        {{ $isDocker ? 'Docker' : 'Bare metal / manual' }}
                    </dd>
                </div>
            </dl>
        </div>
    </div>
</x-filament-panels::page>
