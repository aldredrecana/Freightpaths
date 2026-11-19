@props(['crumb', 'title', 'subtitle' => null, 'eyebrow' => null])
<div>
    <p class="text-xs font-bold uppercase tracking-widest text-brand-600">{{ $eyebrow ?? $crumb }}</p>
    <div class="mt-3 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-[#12263F] sm:text-4xl">{{ $title }}</h1>
            @if ($subtitle)
                <p class="mt-2 text-gray-500">{{ $subtitle }}</p>
            @endif
        </div>
        @isset($actions)
            <div class="flex items-center gap-3">{{ $actions }}</div>
        @endisset
    </div>
</div>
