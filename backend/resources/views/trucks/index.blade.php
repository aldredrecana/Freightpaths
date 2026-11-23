<x-app-layout>
    <div class="px-4 py-5 sm:px-6 lg:px-8">

        <x-page-header crumb="Trucks" title="Trucks" subtitle="Which trucks are ready, on the road, or in the shop.">
            @if (auth()->user()->hasRole('admin'))
                <x-slot name="actions">
                    <x-btn :href="route('trucks.create')">+ Add truck</x-btn>
                </x-slot>
            @endif
        </x-page-header>

        @if ($errors->any())
            <div class="mt-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-800">
                {{ $errors->first() }}</div>
        @endif

        <x-card class="mt-4 overflow-hidden">
            @if ($counts->sum() === 0)
                <x-empty-state title="No trucks yet" message="Add your first truck so you can assign it to trips."
                    :action-label="auth()->user()->hasRole('admin') ? 'Add truck' : null" :action-href="route('trucks.create')" />
            @else
                <x-list-toolbar :action="route('trucks.index')" :q="$q" :status="$status"
                    placeholder="Search plate number or model" :tabs="[
                        '' => ['All', $counts->sum()],
                        'available' => ['Available', $counts->get('available', 0)],
                        'on_trip' => ['On trip', $counts->get('on_trip', 0)],
                        'maintenance' => ['Maintenance', $counts->get('maintenance', 0)],
                    ]" />

                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-xs">
                        <thead
                            class="border-b border-gray-200 bg-gray-50 text-[11px] uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-2 font-medium">Plate no.</th>
                                <th class="px-4 py-2 font-medium">Model</th>
                                <th class="px-4 py-2 text-right font-medium">Capacity</th>
                                <th class="px-4 py-2 font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($trucks as $truck)
                                <tr class="cursor-pointer hover:bg-gray-50"
                                    onclick="window.location='{{ route('trucks.show', $truck) }}'">
                                    <td class="whitespace-nowrap px-4 py-2.5">
                                        <a href="{{ route('trucks.show', $truck) }}"
                                            class="font-mono font-semibold text-[#0E2F30] hover:underline">{{ $truck->plate_no }}</a>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-2.5 text-gray-700">{{ $truck->model }}</td>
                                    <td class="whitespace-nowrap px-4 py-2.5 text-right tabular-nums text-gray-700">
                                        {{ number_format($truck->capacity_kg) }} kg</td>
                                    <td class="whitespace-nowrap px-4 py-2.5"><x-status-pill :status="$truck->status" /></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-8 text-center text-gray-500">No trucks match your
                                        search.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="flex items-center justify-between border-t border-gray-200 px-4 py-2 text-xs text-gray-500">
                    <span>{{ $trucks->total() }} {{ \Illuminate\Support\Str::plural('truck', $trucks->total()) }}</span>
                    @if ($trucks->hasPages())
                        <div>{{ $trucks->links() }}</div>
                    @endif
                </div>
            @endif
        </x-card>
    </div>
</x-app-layout>
