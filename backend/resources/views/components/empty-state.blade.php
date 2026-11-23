@props(['title', 'message' => null, 'actionLabel' => null, 'actionHref' => null])
<div class="px-4 py-10 text-center">
    <div class="mx-auto flex h-9 w-9 items-center justify-center rounded-full bg-gray-100 text-gray-500">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor"
            stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M4 13l2-7h12l2 7v6H4z" />
            <path d="M4 13h5l1 2h4l1-2h5" />
        </svg>
    </div>
    <p class="mt-3 text-sm font-semibold text-[#0E2F30]">{{ $title }}</p>
    @if ($message)
        <p class="mt-1 text-xs text-gray-500">{{ $message }}</p>
    @endif
    @if ($actionLabel && $actionHref)
        <div class="mt-4"><x-btn :href="$actionHref">{{ $actionLabel }}</x-btn></div>
    @endif
</div>
