@props(['action', 'q' => '', 'status' => null, 'tabs' => [], 'placeholder' => 'Search...', 'extra' => []])
{{-- The bar above a table: status tabs (with counts) on the left, search on the right.
     $tabs = ['' => ['All', 12], 'available' => ['Available', 5, 'green'], ...]
     The optional third item colors the count: green, blue, amber, red or gray. --}}
@php
    $chip = [
        'green' => 'bg-emerald-100 text-emerald-700',
        'blue' => 'bg-blue-100 text-blue-700',
        'amber' => 'bg-amber-100 text-amber-800',
        'red' => 'bg-red-100 text-red-700',
        'gray' => 'bg-gray-100 text-gray-600',
    ];
@endphp
<div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-4 py-2">
    <nav class="-mb-2 flex flex-wrap gap-1 text-xs" aria-label="Filter by status">
        @foreach ($tabs as $value => $tab)
            @php
                [$label, $count] = $tab;
                $tone = $tab[2] ?? 'gray';
                $on = (string) $status === (string) $value;
            @endphp
            <a href="{{ $action . '?' . http_build_query(array_filter($extra + ['q' => $q, 'status' => $value])) }}"
                class="inline-flex items-center gap-1.5 border-b-[3px] px-2 pb-2 pt-1 {{ $on ? 'border-brand-600 font-semibold text-brand-700' : 'border-transparent font-medium text-gray-500 hover:text-gray-800' }}">
                {{ $label }}
                @if ($count !== null)
                    <span
                        class="rounded-full px-1.5 py-px text-[11px] font-bold tabular-nums {{ $on ? 'bg-brand-600 text-white' : ($count > 0 ? $chip[$tone] ?? $chip['gray'] : $chip['gray']) }}">{{ $count }}</span>
                @endif
            </a>
        @endforeach
    </nav>
    <form method="GET" action="{{ $action }}" class="flex items-center gap-2">
        @foreach ($extra as $name => $value)
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endforeach
        @if ($status)
            <input type="hidden" name="status" value="{{ $status }}">
        @endif
        <div class="relative">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400"
                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                <circle cx="11" cy="11" r="7" />
                <path d="M21 21l-4.3-4.3" />
            </svg>
            <input type="text" name="q" value="{{ $q }}" placeholder="{{ $placeholder }}"
                class="w-64 rounded-md border border-gray-200 py-1.5 pl-8 pr-2 text-xs placeholder-gray-400 focus:border-brand-500 focus:ring-brand-500">
        </div>
        @if ($q !== '' || $status)
            <a href="{{ $action . ($extra ? '?' . http_build_query($extra) : '') }}"
                class="text-xs text-gray-500 hover:underline">Clear</a>
        @endif
    </form>
</div>
