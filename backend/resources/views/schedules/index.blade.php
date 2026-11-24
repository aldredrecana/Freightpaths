<x-app-layout>
    <div class="px-4 py-5 sm:px-6 lg:px-8">

        <x-page-header crumb="Schedules" title="Schedules"
            subtitle="Upcoming and ongoing trips. Finished trips move to Trip history.">
            <x-slot name="actions">
                <x-btn :href="route('schedules.create')">+ Assign trip</x-btn>
            </x-slot>
        </x-page-header>

        @if ($errors->any())
            <div class="mt-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-800">
                {{ $errors->first() }}</div>
        @endif

        <x-card class="mt-4 overflow-hidden">
            <x-list-toolbar :action="route('schedules.index')" :q="$q" :status="$status"
                placeholder="Search trip no., driver, plate or place" :tabs="[
                    '' => ['All active', $counts->sum()],
                    'needs_reassignment' => ['Needs reassignment', $counts->get('needs_reassignment', 0)],
                    'pending' => ['Pending', $counts->get('pending', 0)],
                    'accepted' => ['Accepted', $counts->get('accepted', 0)],
                    'en_route' => ['En route', $counts->get('en_route', 0)],
                ]" />

            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-xs">
                    <thead
                        class="border-b border-gray-200 bg-gray-50 text-[11px] uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-2 font-medium">Trip</th>
                            <th class="px-4 py-2 font-medium">Departure</th>
                            <th class="px-4 py-2 font-medium">Driver</th>
                            <th class="px-4 py-2 font-medium">Truck</th>
                            <th class="px-4 py-2 font-medium">Route</th>
                            <th class="px-4 py-2 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($schedules as $schedule)
                            <tr class="cursor-pointer hover:bg-gray-50 {{ $schedule->status === 'needs_reassignment' ? 'bg-red-50/40' : '' }}"
                                onclick="window.location='{{ route('schedules.show', $schedule) }}'">
                                <td class="whitespace-nowrap px-4 py-2.5">
                                    <a href="{{ route('schedules.show', $schedule) }}"
                                        class="font-medium text-brand-700 hover:underline">{{ $schedule->reference }}</a>

                                    @if ($schedule->backload_of_id)
                                        <span
                                            class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold uppercase text-amber-800">Backload</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-2.5 tabular-nums text-gray-700">
                                    {{ $schedule->departure_at->format('M d') }}
                                    <span class="text-gray-400">{{ $schedule->departure_at->format('g:i A') }}</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-gray-800">
                                    {{ $schedule->driver->user->name }}</td>
                                <td class="whitespace-nowrap px-4 py-2.5 font-mono text-gray-700">
                                    {{ $schedule->truck->plate_no }}</td>
                                <td class="max-w-[22rem] truncate px-4 py-2.5 text-gray-500"
                                    title="{{ $schedule->origin_address }} → {{ $schedule->destination_address }}">
                                    {{ $schedule->origin_address }} &rarr; {{ $schedule->destination_address }}</td>
                                <td class="whitespace-nowrap px-4 py-2.5">
                                    <div class="flex items-center gap-1.5">
                                        <x-status-pill :status="$schedule->status" />
                                        @if ($schedule->urgency)
                                            <span
                                                class="rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase {{ $schedule->urgency === 'overdue' ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-700' }}">{{ $schedule->urgency }}</span>
                                        @endif
                                    </div>
                                    @if ($schedule->awaiting_since)
                                        <div
                                            class="mt-0.5 text-[11px] {{ $schedule->awaiting_since->lt(now()->subMinutes((int) config('freightpath.response_window_minutes', 30))) ? 'text-red-600' : 'text-gray-400' }}">
                                            Awaiting driver &middot;
                                            {{ $schedule->awaiting_since->diffForHumans(null, true) }}
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-gray-500">No trips found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="flex items-center justify-between border-t border-gray-200 px-4 py-2 text-xs text-gray-500">
                <span>{{ $schedules->total() }}
                    {{ \Illuminate\Support\Str::plural('trip', $schedules->total()) }}</span>
                @if ($schedules->hasPages())
                    <div>{{ $schedules->links() }}</div>
                @endif
            </div>
        </x-card>
    </div>
</x-app-layout>
