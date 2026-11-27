<x-app-layout>
    <div class="px-4 py-5 sm:px-6 lg:px-8">

        <x-page-header crumb="Drivers" title="Drivers" subtitle="Everyone who can be assigned to a trip.">
            @if (auth()->user()->hasRole('admin'))
                <x-slot name="actions">
                    <x-btn :href="route('drivers.create')">+ Add driver</x-btn>
                </x-slot>
            @endif
        </x-page-header>

        @if ($errors->any())
            <div class="mt-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-800">
                {{ $errors->first() }}</div>
        @endif

        <x-card class="mt-4 overflow-hidden">
            <x-list-toolbar :action="route('drivers.index')" :q="$q" :status="$status"
                placeholder="Search name, license or phone" :tabs="[
                    '' => ['All', $counts->sum()],
                    'available' => ['Available', $counts->get('available', 0), 'green'],
                    'on_trip' => ['On trip', $counts->get('on_trip', 0), 'blue'],
                    'off_duty' => ['Off duty', $counts->get('off_duty', 0), 'gray'],
                ]" />

            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-xs">
                    <thead
                        class="border-b border-gray-200 bg-gray-50 text-[11px] uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="py-2 pl-5 pr-4 font-medium">Driver</th>
                            <th class="px-4 py-2 font-medium">License no.</th>
                            <th class="px-4 py-2 font-medium">License expiry</th>
                            <th class="px-4 py-2 font-medium">Phone</th>
                            <th class="px-4 py-2 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($drivers as $driver)
                            <tr class="cursor-pointer hover:bg-gray-50"
                                onclick="window.location='{{ route('drivers.show', $driver) }}'">
                                {{-- A bad license turns the stripe red (expired) or amber (expiring soon) --}}
                                <x-stripe-cell :status="$driver->status" :tone="match ($driver->license_alert) {
                                    'expired' => 'red',
                                    'expiring' => 'amber',
                                    default => null,
                                }">
                                    <a href="{{ route('drivers.show', $driver) }}" class="flex items-center gap-2.5">
                                        <x-avatar :name="$driver->user->name" :seed="$driver->id" size="h-8 w-8 text-[11px]" />
                                        <span class="text-gray-700">{{ $driver->user->name }}</span>
                                    </a>
                                </x-stripe-cell>
                                <td class="whitespace-nowrap px-4 py-2 font-mono text-gray-700">
                                    {{ $driver->license_no }}</td>
                                <td class="whitespace-nowrap px-4 py-2 tabular-nums text-gray-700">
                                    <span
                                        class="{{ $driver->license_alert === 'expired' ? 'font-semibold text-red-700' : ($driver->license_alert ? 'font-semibold text-amber-700' : '') }}">{{ $driver->license_expiry->format('M d, Y') }}</span>
                                    @if ($driver->license_alert)
                                        <span
                                            class="ml-1 rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase {{ $driver->license_alert === 'expired' ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-700' }}">{{ $driver->license_alert === 'expired' ? 'Expired' : 'Expiring' }}</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-2 tabular-nums text-gray-700">
                                    {{ $driver->contact_no }}</td>
                                <td class="whitespace-nowrap px-4 py-2"><x-status-pill :status="$driver->status" /></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-gray-500">No drivers found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="flex items-center justify-between border-t border-gray-200 px-4 py-2 text-xs text-gray-500">
                <span>{{ $drivers->total() }}
                    {{ \Illuminate\Support\Str::plural('driver', $drivers->total()) }}</span>
                @if ($drivers->hasPages())
                    <div>{{ $drivers->links() }}</div>
                @endif
            </div>
        </x-card>
    </div>
</x-app-layout>
