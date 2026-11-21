@php
    $user = auth()->user();
    $initials = collect(preg_split('/\s+/', trim($user->name)))
        ->map(fn($part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->take(2)
        ->implode('');
@endphp

<header
    class="sticky top-0 z-20 flex h-20 items-center justify-between border-b border-gray-200 bg-white px-4 sm:px-6 lg:px-10">
    <div class="flex items-center gap-3">
        <button @click="sidebar = true" class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 lg:hidden"
            aria-label="Open menu">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>
        <p class="text-sm text-gray-500">
            Workspace <span class="mx-1.5">/</span>
            <span class="font-semibold text-[#12263F]">{{ $section }}</span>
        </p>
    </div>

    <div class="flex items-center gap-2">
        @if ($user->hasRole('admin') || $user->hasRole('dispatcher'))
            {{-- Alerts bell: checks the server every 30 seconds. --}}
            <div x-data="alertsBell(@js(route('alerts.index')))" x-init="start()" @click.outside="open = false" class="relative">
                <button @click="open = !open" class="relative rounded-full p-2 text-gray-500 hover:bg-gray-100"
                    aria-label="Alerts">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-6 w-6" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        aria-hidden="true">
                        <path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9" />
                        <path d="M13.7 21a2 2 0 0 1-3.4 0" />
                    </svg>
                    <span x-show="count > 0" x-text="count" style="display: none;"
                        class="absolute -right-0.5 -top-0.5 flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-red-600 px-1 text-[11px] font-bold text-white"></span>
                </button>

                <div x-show="open" x-transition style="display: none;"
                    class="absolute right-0 z-30 mt-2 w-80 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-lg">
                    <div class="border-b border-gray-100 px-4 py-3 text-sm font-semibold text-[#12263F]">Alerts</div>
                    <template x-if="items.length === 0">
                        <p class="px-4 py-6 text-sm text-gray-500">All clear. Nothing needs your attention.</p>
                    </template>
                    <div class="max-h-96 overflow-y-auto">
                        <template x-for="item in items" :key="item.key">
                            <a :href="item.url" class="block border-b border-gray-100 px-4 py-3 hover:bg-gray-50">
                                <p class="text-sm font-semibold"
                                    :class="item.type === 'urgent' ? 'text-amber-700' : 'text-red-700'"
                                    x-text="item.title"></p>
                                <p class="mt-0.5 text-xs text-gray-600" x-text="item.detail"></p>
                                <p class="mt-1 text-[11px] text-gray-400" x-text="item.ago"></p>
                            </a>
                        </template>
                    </div>
                </div>
            </div>

            <script>
                function alertsBell(url) {
                    return {
                        open: false,
                        count: 0,
                        items: [],
                        async load() {
                            try {
                                const res = await fetch(url, {
                                    headers: {
                                        Accept: 'application/json'
                                    },
                                    credentials: 'same-origin',
                                });
                                if (!res.ok) return;
                                const data = await res.json();
                                this.count = data.count;
                                this.items = data.items;
                                // Show the number in the browser tab too, e.g. "(2) FreightPath".
                                document.title = (this.count ? '(' + this.count + ') ' : '') +
                                    document.title.replace(/^\(\d+\) /, '');
                            } catch (e) {
                                // No internet for a moment: try again next time.
                            }
                        },
                        start() {
                            this.load();
                            setInterval(() => this.load(), 30000);
                        },
                    };
                }
            </script>
        @endif

        <x-dropdown align="right" width="48">
            <x-slot name="trigger">
                <button
                    class="flex items-center gap-3 rounded-full py-1 pl-1 pr-3 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-brand-500"
                    aria-label="Account menu">
                    <span
                        class="flex h-10 w-10 items-center justify-center rounded-full bg-brand-100 text-sm font-semibold text-brand-700">{{ $initials }}</span>
                    <span class="hidden text-sm font-semibold text-[#12263F] sm:block">{{ $user->name }}</span>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4 w-4 text-gray-400"
                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round" aria-hidden="true">
                        <path d="M6 9l6 6 6-6" />
                    </svg>
                </button>
            </x-slot>

            <x-slot name="content">
                <div class="px-4 py-2 text-xs text-gray-500">
                    <div class="font-semibold text-gray-700">{{ $user->name }}</div>
                    <div>{{ ucfirst($user->role?->name ?? '') }}</div>
                </div>
                <x-dropdown-link :href="route('profile.edit')">Profile</x-dropdown-link>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-dropdown-link :href="route('logout')"
                        onclick="event.preventDefault(); this.closest('form').submit();">Log
                        Out</x-dropdown-link>
                </form>
            </x-slot>
        </x-dropdown>
    </div>
</header>
