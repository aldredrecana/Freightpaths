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

    <x-dropdown align="right" width="48">
        <x-slot name="trigger">
            <button
                class="flex items-center gap-3 rounded-full py-1 pl-1 pr-3 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-brand-500"
                aria-label="Account menu">
                <span
                    class="flex h-10 w-10 items-center justify-center rounded-full bg-brand-100 text-sm font-semibold text-brand-700">{{ $initials }}</span>
                <span class="hidden text-sm font-semibold text-[#12263F] sm:block">{{ $user->name }}</span>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4 w-4 text-gray-400" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                    aria-hidden="true">
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
                <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">Log
                    Out</x-dropdown-link>
            </form>
        </x-slot>
    </x-dropdown>
</header>
