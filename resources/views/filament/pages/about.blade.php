<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Version card --}}
        <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="space-y-1">
                    <div class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Installed version
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="font-mono text-2xl font-bold text-gray-950 dark:text-white">
                            {{ $currentVersion }}
                        </span>
                        @if ($isDocker)
                            <span class="inline-flex items-center rounded-full bg-primary-100 px-2 py-0.5 text-xs font-medium text-primary-700 dark:bg-primary-400/10 dark:text-primary-400">
                                Docker
                            </span>
                        @endif
                    </div>
                </div>

                <div class="space-y-1 text-right">
                    <div class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Latest release
                    </div>
                    <div class="flex items-center gap-2">
                        @if ($latestVersion)
                            <span class="font-mono text-lg font-semibold text-gray-950 dark:text-white">
                                {{ $latestVersion }}
                            </span>
                            @if ($latestReleaseUrl)
                                <a href="{{ $latestReleaseUrl }}" target="_blank" rel="noopener"
                                    class="text-xs text-primary-600 hover:underline dark:text-primary-400">
                                    View on GitHub ↗
                                </a>
                            @endif
                        @else
                            <span class="text-sm text-gray-500 dark:text-gray-400">Unknown</span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="mt-4 flex items-center gap-3">
                @if ($checkError)
                    <div class="flex-1 rounded-lg bg-danger-50 px-3 py-2 text-sm text-danger-700 dark:bg-danger-950 dark:text-danger-400">
                        Couldn't check for updates: {{ $checkError }}
                    </div>
                @elseif ($updateAvailable)
                    <div class="flex-1 rounded-lg bg-warning-50 px-3 py-2 text-sm text-warning-800 dark:bg-warning-950 dark:text-warning-300">
                        A newer version is available. Follow the commands below to upgrade.
                    </div>
                @else
                    <div class="flex-1 rounded-lg bg-success-50 px-3 py-2 text-sm text-success-800 dark:bg-success-950 dark:text-success-300">
                        You're running the latest version.
                    </div>
                @endif

                <button wire:click="refreshUpdate" type="button"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-gray-100 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    Check again
                </button>
            </div>
        </div>

        {{-- Update commands --}}
        @if ($updateAvailable)
            <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="mb-4">
                    <h2 class="text-lg font-semibold text-gray-950 dark:text-white">
                        {{ $isDocker ? 'Docker update commands' : 'Manual update commands' }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Run these commands in order on the host machine.
                    </p>
                </div>

                <ol class="space-y-4">
                    @foreach ($this->getUpdateCommands() as $i => $cmd)
                        <li class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-gray-800/50">
                            <div class="mb-2 flex items-start justify-between gap-3">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="inline-flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-primary-500 text-xs font-bold text-white">
                                            {{ $i + 1 }}
                                        </span>
                                        <span class="font-medium text-gray-950 dark:text-white">{{ $cmd['title'] }}</span>
                                    </div>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $cmd['description'] }}</p>
                                </div>
                            </div>

                            <div class="relative rounded-md bg-gray-900 p-3 font-mono text-sm text-gray-100">
                                <code class="block select-all break-all pr-12">{{ $cmd['command'] }}</code>
                                <button type="button"
                                    onclick="navigator.clipboard.writeText(this.previousElementSibling.textContent.trim()); this.querySelector('.copy-label').textContent='Copied'; setTimeout(()=>{this.querySelector('.copy-label').textContent='Copy';},1500);"
                                    class="absolute right-2 top-2 inline-flex items-center gap-1 rounded bg-gray-700 px-2 py-1 text-xs text-gray-200 hover:bg-gray-600">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
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

        {{-- Meta info --}}
        <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">About Peregrine</h2>
            <dl class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Repository</dt>
                    <dd class="font-mono text-gray-950 dark:text-white">{{ config('panel.update_repo', 'Knaox/Peregrine') }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">License</dt>
                    <dd class="text-gray-950 dark:text-white">MIT — open source</dd>
                </div>
                @if ($latestReleasedAt)
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Latest release date</dt>
                        <dd class="text-gray-950 dark:text-white">{{ \Carbon\Carbon::parse($latestReleasedAt)->format('M j, Y') }}</dd>
                    </div>
                @endif
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Install mode</dt>
                    <dd class="text-gray-950 dark:text-white">{{ $isDocker ? 'Docker' : 'Bare metal / manual' }}</dd>
                </div>
            </dl>
        </div>
    </div>
</x-filament-panels::page>
