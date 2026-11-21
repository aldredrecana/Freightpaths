<x-app-layout>
    <div class="py-10">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

            <x-page-header crumb="Schedules" eyebrow="Delivery planning" title="Schedule Management"
                subtitle="Assign drivers and trucks to upcoming deliveries. Finished trips move to Trip history.">
                <x-slot name="actions">
                    <x-btn :href="route('schedules.create')">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4 w-4" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                            <path d="M12 5v14M5 12h14" />
                        </svg>
                        Assign trip
                    </x-btn>
                </x-slot>
            </x-page-header>

            @if ($errors->any())
                <div class="mt-6 rounded-lg bg-red-100 p-3 text-sm text-red-800">{{ $errors->first() }}</div>
            @endif

            <div class="mt-8 rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="flex items-start justify-between gap-4 px-6 pt-6">
                    <div>
                        <h2 class="text-lg font-semibold text-[#0E2F30]">Upcoming deliveries</h2>
                        <p class="mt-1 text-xs text-gray-500">Scheduled assignments and routes</p>
                    </div>
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-gray-50 text-[#0E2F30]">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-5 w-5" fill="none"
                            stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
                            aria-hidden="true">
                            <rect x="4" y="5" width="16" height="15" rx="2" />
                            <path d="M4 10h16M8 3v4M16 3v4" />
                        </svg>
                    </span>
                </div>

                <form method="GET" action="{{ route('schedules.index') }}"
                    class="flex flex-wrap items-center justify-between gap-4 p-6">
                    <div class="relative w-full max-w-md">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                            class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400"
                            fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                            aria-hidden="true">
                            <circle cx="11" cy="11" r="7" />
                            <path d="M21 21l-4.3-4.3" />
                        </svg>
                        <input type="text" name="q" value="{{ $q }}"
                            placeholder="Search trips, drivers, or destinations..."
                            class="w-full rounded-lg border border-gray-200 py-3 pl-11 pr-4 text-sm placeholder-gray-400 focus:border-brand-500 focus:ring-brand-500">
                    </div>
                    <div class="flex items-center gap-4">
                        @if ($q !== '' || $status)
                            <a href="{{ route('schedules.index') }}" class="text-sm text-gray-500 underline">Clear</a>
                        @endif
                        <select name="status" onchange="this.form.submit()"
                            class="rounded-lg border border-gray-200 py-3 pl-4 pr-10 text-sm text-gray-700 focus:border-brand-500 focus:ring-brand-500">
                            <option value="">All active trips</option>
                            @foreach (['needs_reassignment' => 'Needs reassignment', 'pending' => 'Pending', 'accepted' => 'Accepted', 'en_route' => 'En route'] as $value => $label)
                                <option value="{{ $value }}" @selected($status === $value)>{{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </form>

                <div class="divide-y divide-gray-100 border-t border-gray-100">
                    @forelse ($schedules as $schedule)
                        <a href="{{ route('schedules.show', $schedule) }}"
                            class="flex flex-wrap items-center justify-between gap-4 px-6 py-5 hover:bg-gray-50/60">
                            <div class="flex min-w-0 items-center gap-4">
                                <x-avatar :name="$schedule->driver->user->name" :seed="$schedule->driver_id" />
                                <div class="min-w-0">
                                    <div class="font-semibold text-[#0E2F30]">{{ $schedule->origin_address }}
                                        &rarr; {{ $schedule->destination_address }}</div>
                                    <div class="mt-0.5 text-sm text-gray-500">{{ $schedule->driver->user->name }}
                                        &middot; {{ $schedule->truck->plate_no }}</div>
                                    <div class="mt-0.5 text-xs text-gray-400">{{ $schedule->reference }} &middot;
                                        {{ $schedule->departure_at->format('M d, Y') }} &middot;
                                        {{ $schedule->departure_at->format('g:i A') }}</div>
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <div class="text-right">
                                    <x-status-pill :status="$schedule->status" />
                                    @if ($schedule->urgency)
                                        <span
                                            class="ml-2 rounded px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white {{ $schedule->urgency === 'overdue' ? 'bg-red-600' : 'bg-amber-500' }}">{{ $schedule->urgency }}</span>
                                    @endif
                                    @if ($schedule->awaiting_since)
                                        <div
                                            class="mt-1 text-xs {{ $schedule->awaiting_since->lt(now()->subMinutes((int) config('freightpath.response_window_minutes', 30))) ? 'text-red-600' : 'text-gray-400' }}">
                                            Awaiting driver &middot;
                                            {{ $schedule->awaiting_since->diffForHumans(null, true) }}
                                        </div>
                                    @endif
                                </div>
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                    class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M9 6l6 6-6 6" />
                                </svg>
                            </div>
                        </a>
                    @empty
                        <p class="px-6 py-10 text-center text-gray-500">No trips found.</p>
                    @endforelse
                </div>

                @if ($schedules->hasPages())
                    <div class="border-t border-gray-100 px-6 py-4">{{ $schedules->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
