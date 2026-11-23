@props(['crumb', 'title', 'subtitle' => null, 'eyebrow' => null])
{{-- Compact page header: a small title (and one line of help) on the left, actions on the right. --}}
<div class="flex flex-wrap items-center justify-between gap-3">
    <div class="min-w-0">
        <h1 class="text-xl font-semibold text-[#0E2F30]">{{ $title }}</h1>
        @if ($subtitle)
            <p class="text-xs text-gray-500">{{ $subtitle }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
