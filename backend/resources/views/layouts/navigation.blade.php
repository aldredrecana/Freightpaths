@php
    $user = auth()->user();
    $initials = collect(preg_split('/\s+/', trim($user->name)))
        ->map(fn($part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->take(2)
        ->implode('');
@endphp

{{-- Dark overlay behind the menu on phones --}}
<div x-show="sidebar" x-cloak x-transition.opacity @click="sidebar = false"
    class="fixed inset-0 z-30 bg-black/50 lg:hidden"></div>

<aside :class="sidebar ? 'translate-x-0' : '-translate-x-full'"
    class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col bg-[#073B3C]
     text-white transition-transform duration-200 lg:translate-x-0">
    <div class="flex h-20 items-center justify-between px-6">
        <a href="{{ route('dashboard') }}"><x-brand-logo :light="true" /></a>
        <button @click="sidebar = false" class="rounded-lg p-1.5 text-white/60 hover:bg-white/10 lg:hidden"
            aria-label="Close menu">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <p class="px-7 pb-2 pt-4 text-[11px] font-semibold uppercase tracking-widest text-white/40">Workspace</p>

    <nav class="flex-1 space-y-1 overflow-y-auto px-3">
        @foreach ($items as [$label, $route, $pattern, $icon])
            <x-nav-item :href="route($route)" :active="request()->routeIs($pattern)" :icon="$icon">{{ $label }}</x-nav-item>
        @endforeach
    </nav>

    <div class="border-t border-white/10 p-4">
        <div class="flex items-center gap-3 px-2 py-2">
            <span
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white/10 text-sm font-semibold">{{ $initials }}</span>
            <div class="min-w-0">
                <p class="truncate text-sm font-semibold">{{ $user->name }}</p>
                <p class="truncate text-xs text-white/50">{{ ucfirst($user->role?->name ?? '') }}</p>
            </div>
        </div>
    </div>
</aside>

@if (auth()->user()->hasRole('driver'))
    <div class="fixed inset-x-0 bottom-0 z-20 border-t border-gray-200 bg-white lg:hidden">
        <div class="grid grid-cols-2">
            @foreach ([['Home', 'dashboard', 'dashboard', 'M3 12l9-9 9 9M5 10v10h14V10'], ['My Trips', 'schedules.mine', 'schedules.*', 'M3 6h11v10H3zM14 10h4l3 3v3h-7z']] as [$label, $route, $pattern, $icon])
                <a href="{{ route($route) }}"
                    class="flex flex-col items-center gap-1 py-3 text-base font-semibold {{ request()->routeIs($pattern) ? 'text-brand-700' : 'text-gray-600' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-7 w-7" fill="none"
                        stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
                        aria-hidden="true">
                        <path d="{{ $icon }}" />
                    </svg>
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </div>
@endif

<x-toast />
