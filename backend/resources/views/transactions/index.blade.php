<x-app-layout>
    <div class="px-4 py-5 sm:px-6 lg:px-8">

        <x-page-header crumb="Transactions" title="Trip history"
            subtitle="Delivered and cancelled trips, with every step that was recorded.">
            <x-slot name="actions">
                <x-btn variant="secondary" :href="route('transactions.export', ['q' => $q, 'status' => $status])">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4 w-4" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        aria-hidden="true">
                        <path d="M12 4v11M7 10l5 5 5-5M5 20h14" />
                    </svg>
                    Export CSV
                </x-btn>
            </x-slot>
        </x-page-header>

        <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-12">

            {{-- Left: the list of finished trips --}}
            <div class="overflow-hidden rounded-md border border-gray-200 bg-white lg:col-span-4">
                <form method="GET" action="{{ route('transactions.index') }}"
                    class="flex items-center gap-2 border-b border-gray-200 px-3 py-2">
                    <div class="relative flex-1">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                            class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400"
                            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                            aria-hidden="true">
                            <circle cx="11" cy="11" r="7" />
                            <path d="M21 21l-4.3-4.3" />
                        </svg>
                        <input type="text" name="q" value="{{ $q }}"
                            placeholder="Search trips, drivers, places"
                            class="w-full rounded-md border border-gray-200 py-1.5 pl-8 pr-2 text-xs placeholder-gray-400 focus:border-brand-500 focus:ring-brand-500">
                    </div>
                    <select name="status" onchange="this.form.submit()"
                        class="rounded-md border border-gray-200 py-1.5 pl-2 pr-7 text-xs text-gray-700 focus:border-brand-500 focus:ring-brand-500">
                        <option value="">All</option>
                        <option value="delivered" @selected($status === 'delivered')>Delivered</option>
                        <option value="cancelled" @selected($status === 'cancelled')>Cancelled</option>
                    </select>
                    @if ($q !== '' || $status)
                        <a href="{{ route('transactions.index') }}"
                            class="text-xs text-gray-500 hover:underline">Clear</a>
                    @endif
                </form>

                <div class="divide-y divide-gray-100">
                    @forelse ($trips as $trip)
                        @php $active = $selected && $selected->id === $trip->id; @endphp
                        <a href="{{ route('transactions.index', array_merge(request()->query(), ['trip' => $trip->id])) }}"
                            class="block border-l-2 px-4 py-2.5 {{ $active ? 'border-brand-600 bg-brand-50/60' : 'border-transparent hover:bg-gray-50' }}">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-xs font-semibold text-[#0E2F30]">{{ $trip->reference }}</span>
                                <x-status-pill :status="$trip->status" />
                            </div>
                            <div class="mt-1 truncate text-xs text-gray-500">{{ $trip->origin_address }} &rarr;
                                {{ $trip->destination_address }}</div>
                            <div class="mt-0.5 flex justify-between text-[11px] text-gray-400">
                                <span>{{ $trip->driver->user->name }}</span>
                                <span class="tabular-nums">{{ $trip->departure_at->format('M d, Y') }}</span>
                            </div>
                        </a>
                    @empty
                        <p class="px-4 py-8 text-center text-xs text-gray-500">No finished trips yet.</p>
                    @endforelse
                </div>

                <div class="flex items-center justify-between border-t border-gray-200 px-4 py-2 text-xs text-gray-500">
                    <span>{{ $trips->total() }} {{ \Illuminate\Support\Str::plural('trip', $trips->total()) }}</span>
                    @if ($trips->hasPages())
                        <div>{{ $trips->onEachSide(0)->links('pagination::simple-tailwind') }}</div>
                    @endif
                </div>
            </div>

            {{-- Right: the selected trip's journey --}}
            <div class="overflow-hidden rounded-md border border-gray-200 bg-white lg:col-span-8">
                @if ($selected)
                    @php
                        $name = $selected->driver->user->name;
                        $logs = $selected->statusLogs->reverse()->values();
                        $finishedAt = $logs->last()?->logged_at;
                        $labels = [
                            'pending' => 'Trip assigned',
                            'accepted' => 'Trip accepted',
                            'en_route' => 'En route',
                            'delivered' => 'Delivered',
                            'cancelled' => 'Trip cancelled',
                            'needs_reassignment' => 'Driver declined',
                        ];
                        $defaults = [
                            'pending' => 'Assigned to ' . $name,
                            'accepted' => $name . ' accepted the trip',
                            'en_route' => 'Departed from ' . $selected->origin_address,
                            'delivered' => 'Delivered to ' . $selected->destination_address,
                            'cancelled' => 'The trip was cancelled',
                            'needs_reassignment' => 'The driver could not take the trip',
                        ];
                    @endphp

                    <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-5 py-3">
                        <div class="min-w-0">
                            <p class="text-[11px] font-medium uppercase tracking-wide text-gray-400">Trip journey</p>
                            <div class="mt-0.5 flex flex-wrap items-center gap-2">
                                <h2 class="text-lg font-semibold text-[#0E2F30]">{{ $selected->reference }}</h2>
                                <x-status-pill :status="$selected->status" />
                            </div>
                            <p class="mt-0.5 truncate text-xs text-gray-500">{{ $selected->origin_address }} &rarr;
                                {{ $selected->destination_address }}</p>
                        </div>
                        <a href="{{ route('schedules.show', $selected) }}"
                            class="shrink-0 text-xs font-medium text-brand-700 hover:underline">Open trip &rarr;</a>
                    </div>

                    <div
                        class="grid grid-cols-2 gap-4 border-b border-gray-200 bg-gray-50 px-5 py-3 text-xs md:grid-cols-4">
                        <div>
                            <div class="text-[11px] uppercase tracking-wide text-gray-400">Driver</div>
                            <div class="mt-0.5 font-medium text-[#0E2F30]">{{ $name }}</div>
                        </div>
                        <div>
                            <div class="text-[11px] uppercase tracking-wide text-gray-400">Vehicle</div>
                            <div class="mt-0.5 font-medium text-[#0E2F30]">{{ $selected->truck->plate_no }}</div>
                        </div>
                        <div>
                            <div class="text-[11px] uppercase tracking-wide text-gray-400">Date</div>
                            <div class="mt-0.5 font-medium text-[#0E2F30]">
                                {{ $selected->departure_at->format('F j, Y') }}</div>
                        </div>
                        <div>
                            <div class="text-[11px] uppercase tracking-wide text-gray-400">Finished</div>
                            <div class="mt-0.5 font-medium text-[#0E2F30]">
                                {{ $finishedAt ? $finishedAt->format('M d, g:i A') : '-' }}</div>
                        </div>
                    </div>

                    @if ($selected->proof)
                        <a href="{{ route('schedules.show', $selected) }}"
                            class="flex items-center gap-3 border-b border-gray-200 px-5 py-2.5 text-xs hover:bg-gray-50">
                            <img src="{{ route('schedules.proof', [$selected, 'photo']) }}" alt=""
                                class="h-10 w-14 rounded border border-gray-200 object-cover">
                            <span class="text-gray-700"><span class="font-semibold text-[#0E2F30]">Proof of
                                    delivery</span>
                                &middot; received by {{ $selected->proof->received_by }}
                                &middot; {{ $selected->proof->captured_at->format('M d, g:i A') }}</span>
                            <span class="ml-auto font-medium text-brand-700">View photo &amp; signature &rarr;</span>
                        </a>
                    @endif

                    <ol class="px-5 py-5">
                        @forelse ($logs as $log)
                            @php
                                $isLast = $loop->last;
                                $failed = $isLast && $log->status === 'cancelled';
                                $done = $isLast && $log->status === 'delivered';
                                $circle = $failed
                                    ? 'bg-red-500 text-white border-red-500'
                                    : ($done
                                        ? 'bg-emerald-500 text-white border-emerald-500'
                                        : 'bg-brand-50 text-brand-600 border-brand-200');
                                $remark = $log->remarks && $log->remarks !== 'Schedule created.' ? $log->remarks : null;
                            @endphp
                            <li class="relative pl-10 {{ $isLast ? '' : 'pb-5' }}">
                                @unless ($isLast)
                                    <span class="absolute left-3 top-7 bottom-0 w-px bg-gray-200"></span>
                                @endunless
                                <span
                                    class="absolute left-0 top-0 flex h-6 w-6 items-center justify-center rounded-full border {{ $circle }}">
                                    @if ($failed)
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-3.5 w-3.5"
                                            fill="none" stroke="currentColor" stroke-width="2.4"
                                            stroke-linecap="round" aria-hidden="true">
                                            <path d="M6 6l12 12M18 6L6 18" />
                                        </svg>
                                    @else
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-3.5 w-3.5"
                                            fill="none" stroke="currentColor" stroke-width="2.4"
                                            stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M5 12l5 5L20 7" />
                                        </svg>
                                    @endif
                                </span>

                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <p class="text-sm font-semibold text-[#0E2F30]">
                                            {{ $labels[$log->status] ?? ucfirst(str_replace('_', ' ', $log->status)) }}
                                        </p>
                                        <p class="mt-0.5 text-xs text-gray-500">{{ $defaults[$log->status] ?? '' }}
                                        </p>
                                        @if ($remark)
                                            <p class="mt-0.5 text-xs italic text-gray-600">"{{ $remark }}"</p>
                                        @endif
                                        <p class="mt-0.5 text-[11px] text-gray-400">Logged by
                                            {{ $log->loggedBy->name }}
                                        </p>
                                    </div>
                                    <span
                                        class="shrink-0 text-xs tabular-nums text-gray-400">{{ $log->logged_at->format('M d, g:i A') }}</span>
                                </div>
                            </li>
                        @empty
                            <li class="text-sm text-gray-500">No history was recorded for this trip.</li>
                        @endforelse
                    </ol>
                @else
                    <div class="p-12 text-center text-gray-500">
                        <p class="text-sm font-semibold text-[#0E2F30]">No finished trips yet</p>
                        <p class="mt-1 text-xs">Delivered and cancelled trips appear here with their full timeline.
                        </p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
