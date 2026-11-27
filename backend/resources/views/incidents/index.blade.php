<x-app-layout>
    <div class="px-4 py-5 sm:px-6 lg:px-8">

        <x-page-header crumb="Problem Reports" title="Problem reports"
            subtitle="Problems drivers reported from the road. Call the driver, then mark the report resolved." />

        @if (session('status'))
            <div class="mt-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-800">
                {{ session('status') }}</div>
        @endif

        <x-card class="mt-4 overflow-hidden">
            <x-list-toolbar :action="route('incidents.index')" :q="$q" :status="$status"
                placeholder="Search driver, plate or note" :tabs="[
                    'open' => ['Open', $counts['open'], 'red'],
                    'resolved' => ['Resolved', $counts['resolved'], 'green'],
                    'all' => ['All', $counts['open'] + $counts['resolved']],
                ]" />

            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-xs">
                    <thead class="border-b border-gray-200 bg-gray-50 text-[11px] uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="py-2 pl-5 pr-4 font-medium">Problem</th>
                            <th class="px-4 py-2 font-medium">Driver</th>
                            <th class="px-4 py-2 font-medium">Trip</th>
                            <th class="px-4 py-2 font-medium">Reported</th>
                            <th class="px-4 py-2 font-medium">Location</th>
                            <th class="px-4 py-2 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($incidents as $incident)
                            <tr class="align-top {{ $incident->resolved_at ? '' : 'bg-red-50/40' }}">
                                {{-- Problem: red stripe while open, green once resolved --}}
                                <x-stripe-cell :status="$incident->resolved_at ? 'resolved' : 'open'" wrap>
                                    <span
                                        class="inline-flex items-center gap-1.5 rounded px-1.5 py-0.5 text-[11px] font-semibold {{ $incident->resolved_at ? 'bg-gray-100 text-gray-600' : 'bg-red-100 text-red-800' }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-3 w-3"
                                            fill="none" stroke="currentColor" stroke-width="2.4"
                                            stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M12 3l9.5 17h-19z" />
                                            <path d="M12 10v4M12 17.5v.01" />
                                        </svg>
                                        {{ $incident->label }}
                                    </span>
                                    @if ($incident->note)
                                        <p class="mt-1 max-w-xs text-gray-700">{{ $incident->note }}</p>
                                    @endif
                                </x-stripe-cell>

                                {{-- Driver --}}
                                <td class="whitespace-nowrap px-4 py-2.5">
                                    <div class="font-medium text-gray-800">{{ $incident->driver->user->name }}</div>
                                    <a href="tel:{{ $incident->driver->contact_no }}"
                                        class="tabular-nums text-brand-700 hover:underline">{{ $incident->driver->contact_no }}</a>
                                </td>

                                {{-- Trip --}}
                                <td class="whitespace-nowrap px-4 py-2.5">
                                    @if ($incident->schedule)
                                        <a href="{{ route('schedules.show', $incident->schedule) }}"
                                            class="font-medium text-brand-700 hover:underline">{{ $incident->schedule->reference }}</a>
                                        <div class="font-mono text-gray-500">{{ $incident->schedule->truck->plate_no }}
                                        </div>
                                    @else
                                        <span class="text-gray-400">&mdash;</span>
                                    @endif
                                </td>

                                {{-- Reported: how long ago, big, so an old open report stands out --}}
                                <td class="whitespace-nowrap px-4 py-2.5 tabular-nums">
                                    <div
                                        class="text-sm font-bold {{ $incident->resolved_at ? 'text-gray-700' : 'text-red-700' }}">
                                        {{ $incident->reported_at->diffForHumans() }}</div>
                                    <div class="text-[11px] text-gray-400">
                                        {{ $incident->reported_at->format('M d, g:i A') }}</div>
                                </td>

                                {{-- Location --}}
                                <td class="whitespace-nowrap px-4 py-2.5">
                                    @if ($incident->map_url)
                                        <a href="{{ $incident->map_url }}" target="_blank" rel="noopener"
                                            class="font-medium text-brand-700 hover:underline">Open in Maps &rarr;</a>
                                    @else
                                        <span class="text-gray-400">No location</span>
                                    @endif
                                </td>

                                {{-- Status --}}
                                <td class="px-4 py-2.5">
                                    @if ($incident->resolved_at)
                                        <span
                                            class="rounded bg-emerald-50 px-1.5 py-0.5 text-[11px] font-semibold text-emerald-700">Resolved</span>
                                        <div class="mt-1 text-gray-500">
                                            {{ $incident->resolved_at->format('M d, g:i A') }}
                                            by {{ $incident->resolver?->name ?? 'staff' }}</div>
                                        @if ($incident->resolution_note)
                                            <div class="mt-0.5 max-w-xs italic text-gray-600">
                                                "{{ $incident->resolution_note }}"</div>
                                        @endif
                                    @else
                                        <form method="POST" action="{{ route('incidents.resolve', $incident) }}"
                                            class="flex min-w-[16rem] items-center gap-2">
                                            @csrf
                                            <input type="text" name="resolution_note" maxlength="300"
                                                placeholder="What was done? (optional)"
                                                class="flex-1 rounded-md border border-gray-200 px-2 py-1.5 text-xs placeholder-gray-400 focus:border-brand-500 focus:ring-brand-500">
                                            <button type="submit"
                                                class="whitespace-nowrap rounded-md bg-brand-600 px-2.5 py-1.5 text-xs font-medium text-white hover:bg-brand-700">Mark
                                                resolved</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                    {{ $status === 'open' ? 'No open problem reports. All trucks are fine.' : 'No problem reports found.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="flex items-center justify-between border-t border-gray-200 px-4 py-2 text-xs text-gray-500">
                <span>{{ $incidents->total() }}
                    {{ \Illuminate\Support\Str::plural('report', $incidents->total()) }}</span>
                @if ($incidents->hasPages())
                    <div>{{ $incidents->links() }}</div>
                @endif
            </div>
        </x-card>
    </div>
</x-app-layout>
