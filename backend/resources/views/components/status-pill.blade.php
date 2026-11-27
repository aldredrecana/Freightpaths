@props(['status'])
@php
    // Same colors on every page: green = good / moving, blue = busy, amber = waiting or a warning,
    // red = needs action now, gray = not active.
    $map = [
        'available' => ['bg-emerald-50 text-emerald-700', 'bg-emerald-500', 'Available'],
        'on_trip' => ['bg-blue-50 text-blue-700', 'bg-blue-500', 'On trip'],
        'off_duty' => ['bg-gray-100 text-gray-600', 'bg-gray-400', 'Off duty'],
        'maintenance' => ['bg-amber-50 text-amber-700', 'bg-amber-500', 'Maintenance'],
        'pending' => ['bg-amber-50 text-amber-700', 'bg-amber-500', 'Pending'],
        'accepted' => ['bg-blue-50 text-blue-700', 'bg-blue-500', 'Accepted'],
        'en_route' => ['bg-emerald-50 text-emerald-700', 'bg-emerald-500', 'En route'],
        'delivered' => ['bg-emerald-50 text-emerald-700', 'bg-emerald-500', 'Delivered'],
        'cancelled' => ['bg-gray-100 text-gray-600', 'bg-gray-400', 'Cancelled'],
        'needs_reassignment' => ['bg-red-50 text-red-700', 'bg-red-500', 'Needs reassignment'],
        'open' => ['bg-red-50 text-red-700', 'bg-red-500', 'Open'],
        'resolved' => ['bg-emerald-50 text-emerald-700', 'bg-emerald-500', 'Resolved'],
    ];
    [$cls, $dot, $label] = $map[$status] ?? [
        'bg-gray-100 text-gray-600',
        'bg-gray-400',
        ucfirst(str_replace('_', ' ', $status)),
    ];
@endphp
<span class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-xs font-medium {{ $cls }}">
    <span class="h-1.5 w-1.5 rounded-full {{ $dot }}"></span>{{ $label }}
</span>
