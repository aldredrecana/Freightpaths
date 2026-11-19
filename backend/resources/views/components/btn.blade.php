@props(['variant' => 'primary', 'href' => null, 'type' => 'submit'])
@php
    $styles =
        [
            'primary' => 'bg-brand-600 text-white hover:bg-brand-700',
            'secondary' => 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50',
            'danger' => 'bg-red-600 text-white hover:bg-red-700',
        ][$variant] ?? 'bg-brand-600 text-white hover:bg-brand-700';
    $base =
        'inline-flex items-center justify-center gap-2 rounded-lg px-5 py-3 text-sm font-semibold transition focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2';
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $base . ' ' . $styles]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}"
        {{ $attributes->merge(['class' => $base . ' ' . $styles]) }}>{{ $slot }}</button>
@endif
