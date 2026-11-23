@props(['action', 'q' => '', 'status' => null, 'tabs' => [], 'placeholder' => 'Search...'])
{{-- The bar above a table: status tabs (with counts) on the left, search on the right.
     $tabs = ['' => ['All', 12], 'available' => ['Available', 5], ...] --}}
<div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-4 py-2">
    <nav class="-mb-2 flex flex-wrap gap-1 text-xs" aria-label="Filter by status">
        @foreach ($tabs as $value => [$label, $count])
            @php $on = (string) $status === (string) $value; @endphp
            <a href="{{ $action . '?' . http_build_query(array_filter(['q' => $q, 'status' => $value])) }}"
                class="inline-flex items-center gap-1.5 border-b-2 px-2 pb-2 pt-1 font-medium {{ $on ? 'border-brand-600 text-brand-700' : 'border-transparent text-gray-500 hover:text-gray-800' }}">
                {{ $label }}
                @if ($count !== null)
                    <span
                        class="rounded bg-gray-100 px-1.5 text-[11px] font-semibold text-gray-600">{{ $count }}</span>
                @endif
            </a>
        @endforeach
    </nav>
    <form method="GET" action="{{ $action }}" class="flex items-center gap-2">
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
            <a href="{{ $action }}" class="text-xs text-gray-500 hover:underline">Clear</a>
        @endif
    </form>
</div>
