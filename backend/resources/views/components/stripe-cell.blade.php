@props(['status' => null, 'tone' => null, 'wrap' => false])
{{-- First cell of a table row with a thick colored stripe on its left edge.
     Pass a status (on_trip, pending, ...) or a tone (green, blue, amber, red, gray). --}}
@php
    $tone ??= match ($status) {
        'available', 'delivered', 'resolved', 'en_route' => 'green',
        'on_trip', 'accepted' => 'blue',
        'pending', 'maintenance' => 'amber',
        'needs_reassignment', 'open' => 'red',
        default => 'gray',
    };
    $stripe =
        [
            'green' => 'shadow-[inset_5px_0_0_#10b981]',
            'blue' => 'shadow-[inset_5px_0_0_#3b82f6]',
            'amber' => 'shadow-[inset_5px_0_0_#f59e0b]',
            'red' => 'shadow-[inset_5px_0_0_#ef4444]',
            'gray' => 'shadow-[inset_5px_0_0_#d1d5db]',
        ][$tone] ?? 'shadow-[inset_5px_0_0_#d1d5db]';
@endphp
<td {{ $attributes->merge(['class' => ($wrap ? '' : 'whitespace-nowrap ') . 'py-2.5 pl-5 pr-4 ' . $stripe]) }}>
    {{ $slot }}</td>
