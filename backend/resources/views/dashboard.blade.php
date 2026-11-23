<x-app-layout>
    @php
        $first = \Illuminate\Support\Str::of(auth()->user()->name)->before(' ');
        $greeting = now()->hour < 12 ? 'Good morning' : (now()->hour < 18 ? 'Good afternoon' : 'Good evening');
        $activeDrivers = $drivers->get('available', 0) + $drivers->get('on_trip', 0);

        $icons = [
            'users' =>
                '<circle cx="9" cy="8" r="3.5" /><path d="M2.5 20c0-3.6 2.9-6.5 6.5-6.5s6.5 2.9 6.5 6.5" /><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 14c2.2.7 3.5 2.7 3.5 6" />',
            'truck' =>
                '<path d="M3 6h11v10H3z" /><path d="M14 10h4l3 3v3h-7z" /><circle cx="7" cy="17" r="2" /><circle cx="17" cy="17" r="2" />',
            'package' => '<path d="M12 3l8 4.5v9L12 21l-8-4.5v-9z" /><path d="M12 12l8-4.5M12 12v9M12 12L4 7.5" />',
            'calendar' => '<rect x="4" y="5" width="16" height="15" rx="2" /><path d="M4 10h16M8 3v4M16 3v4" />',
        ];

        $stats = [
            [
                'Active drivers',
                $activeDrivers,
                $drivers->get('available', 0) . ' available · ' . $drivers->get('on_trip', 0) . ' on trip',
                route('drivers.index'),
                'users',
            ],
            [
                'Trips today',
                $today->count(),
                $tripCounts['Ongoing'] . ' on the road now',
                route('schedules.index'),
                'truck',
            ],
            [
                'Completed deliveries',
                $tripCounts['Completed'],
                'All time',
                route('transactions.index', ['status' => 'delivered']),
                'package',
            ],
            [
                'Pending schedules',
                $tripCounts['Scheduled'],
                'Waiting to be driven',
                route('schedules.index'),
                'calendar',
            ],
        ];

        $alertItems = collect([
            [
                $alerts['reassign'],
                'trip(s) need a new driver',
                route('schedules.index', ['status' => 'needs_reassignment']),
                'border-red-200 bg-red-50 text-red-800',
            ],
            [
                $alerts['no_signal'],
                'trip(s) with no GPS signal',
                route('map.index'),
                'border-red-200 bg-red-50 text-red-800',
            ],
            [
                $alerts['urgent'],
                'trip(s) urgent or overdue',
                route('schedules.index'),
                'border-amber-200 bg-amber-50 text-amber-800',
            ],
            [
                $alerts['licenses'],
                'license(s) expiring or expired',
                route('drivers.index'),
                'border-amber-200 bg-amber-50 text-amber-800',
            ],
        ])->filter(fn($a) => $a[0] > 0);

        $activityLabels = [
            'pending' => ['Trip assigned', 'bg-gray-400'],
            'accepted' => ['Trip accepted', 'bg-amber-500'],
            'en_route' => ['Trip started', 'bg-brand-500'],
            'delivered' => ['Delivery completed', 'bg-emerald-500'],
            'needs_reassignment' => ['Driver declined', 'bg-red-500'],
            'cancelled' => ['Trip cancelled', 'bg-red-500'],
        ];
    @endphp

    {{-- Dense, work-tool layout: compact header, flat bordered panels, full width. --}}
    <div class="px-4 py-5 sm:px-6 lg:px-8">

        {{-- Header: title and date on the left, the main action on the right --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold text-[#0E2F30]">Dashboard</h1>
                <p class="text-xs text-gray-500">{{ $greeting }}, {{ $first }} &middot;
                    {{ now()->format('l, F j, Y') }}</p>
            </div>
            <a href="{{ route('schedules.create') }}"
                class="inline-flex items-center gap-1.5 rounded-md bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700">
                <span aria-hidden="true">+</span> New schedule
            </a>
        </div>

        {{-- Alerts: one slim strip --}}
        @if ($alertItems->isEmpty())
            <div
                class="mt-4 flex items-center gap-2 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-medium text-emerald-800">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                All clear. Nothing needs your attention right now.
            </div>
        @else
            <div class="mt-4 flex flex-wrap gap-2">
                @foreach ($alertItems as [$n, $label, $href, $cls])
                    <a href="{{ $href }}"
                        class="inline-flex items-center gap-2 rounded-md border px-3 py-1.5 text-xs font-medium hover:brightness-95 {{ $cls }}">
                        <span class="font-bold">{{ $n }}</span> {{ $label }} <span
                            aria-hidden="true">&rarr;</span>
                    </a>
                @endforeach
            </div>
        @endif

        {{-- Key numbers: one panel split into four --}}
        <div
            class="mt-4 grid grid-cols-2 divide-gray-200 rounded-md border border-gray-200 bg-white xl:grid-cols-4 xl:divide-x">
            @foreach ($stats as [$label, $value, $note, $href, $icon])
                <a href="{{ $href }}" class="group block px-4 py-3 hover:bg-gray-50">
                    <div
                        class="flex items-center gap-1.5 text-[11px] font-medium uppercase tracking-wide text-gray-500">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            aria-hidden="true">{!! $icons[$icon] !!}</svg>
                        {{ $label }}
                    </div>
                    <div class="mt-1 text-2xl font-semibold tabular-nums text-[#0E2F30]">{{ $value }}</div>
                    <div class="text-xs text-gray-500">{{ $note }}</div>
                </a>
            @endforeach
        </div>

        <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
            {{-- Fleet map: edge to edge inside its panel --}}
            <section class="flex flex-col overflow-hidden rounded-md border border-gray-200 bg-white lg:col-span-2">
                <div class="flex items-center justify-between border-b border-gray-200 px-4 py-2.5">
                    <div class="flex items-center gap-2">
                        <h2 class="text-sm font-semibold text-[#0E2F30]">Fleet map</h2>
                        <span id="fleet-note" class="text-xs text-gray-500">Loading...</span>
                    </div>
                    <a href="{{ route('map.index') }}" class="text-xs font-medium text-brand-700 hover:underline">Open
                        GPS
                        Tracking &rarr;</a>
                </div>
                <div id="fleet-map" style="z-index: 0;" class="min-h-[20rem] w-full flex-1"></div>
            </section>

            {{-- Recent activity --}}
            <section class="rounded-md border border-gray-200 bg-white">
                <div class="border-b border-gray-200 px-4 py-2.5">
                    <h2 class="text-sm font-semibold text-[#0E2F30]">Recent activity</h2>
                </div>
                <div class="divide-y divide-gray-100">
                    @forelse ($activity as $log)
                        @php [$activityLabel, $chip] = $activityLabels[$log->status] ?? [ucfirst(str_replace('_', ' ', $log->status)), 'bg-gray-400']; @endphp
                        <a href="{{ route('schedules.show', $log->schedule) }}"
                            class="flex items-start gap-2.5 px-4 py-2.5 hover:bg-gray-50">
                            <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $chip }}"></span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-baseline justify-between gap-2">
                                    <p class="truncate text-sm font-medium text-[#0E2F30]">{{ $activityLabel }}</p>
                                    <p class="shrink-0 text-[11px] text-gray-400">
                                        {{ $log->logged_at->diffForHumans(null, false, true) }}</p>
                                </div>
                                <p class="truncate text-xs text-gray-500">{{ $log->schedule->reference }} &middot;
                                    {{ $log->schedule->driver->user->name }}</p>
                            </div>
                        </a>
                    @empty
                        <p class="px-4 py-6 text-xs text-gray-500">No activity yet.</p>
                    @endforelse
                </div>
            </section>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
            {{-- Today's trips: a real table --}}
            <section class="overflow-hidden rounded-md border border-gray-200 bg-white lg:col-span-2">
                <div class="flex items-center justify-between border-b border-gray-200 px-4 py-2.5">
                    <h2 class="text-sm font-semibold text-[#0E2F30]">Today's trips <span
                            class="ml-1 rounded bg-gray-100 px-1.5 py-0.5 text-[11px] font-semibold text-gray-600">{{ $today->count() }}</span>
                    </h2>
                    <a href="{{ route('schedules.index') }}"
                        class="text-xs font-medium text-brand-700 hover:underline">View
                        all &rarr;</a>
                </div>
                @if ($today->isEmpty())
                    <x-empty-state title="No trips today" message="Assign a trip to get the day started."
                        action-label="Assign trip" :action-href="route('schedules.create')" />
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead
                                class="border-b border-gray-200 bg-gray-50 text-[11px] uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-4 py-2 font-medium">Time</th>
                                    <th class="px-4 py-2 font-medium">Trip</th>
                                    <th class="px-4 py-2 font-medium">Driver</th>
                                    <th class="px-4 py-2 font-medium">Truck</th>
                                    <th class="px-4 py-2 font-medium">Route</th>
                                    <th class="px-4 py-2 font-medium">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($today as $trip)
                                    <tr class="cursor-pointer hover:bg-gray-50"
                                        onclick="window.location='{{ route('schedules.show', $trip) }}'">
                                        <td class="whitespace-nowrap px-4 py-2 tabular-nums text-gray-700">
                                            {{ $trip->departure_at->format('g:i A') }}</td>
                                        <td class="whitespace-nowrap px-4 py-2">
                                            <a href="{{ route('schedules.show', $trip) }}"
                                                class="font-medium text-brand-700 hover:underline">{{ $trip->reference }}</a>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-2 text-gray-800">
                                            {{ $trip->driver->user->name }}</td>
                                        <td class="whitespace-nowrap px-4 py-2 font-mono text-gray-700">
                                            {{ $trip->truck->plate_no }}</td>
                                        <td class="max-w-[18rem] truncate px-4 py-2 text-gray-500"
                                            title="{{ $trip->origin_address }} → {{ $trip->destination_address }}">
                                            {{ $trip->origin_address }} &rarr; {{ $trip->destination_address }}</td>
                                        <td class="whitespace-nowrap px-4 py-2"><x-status-pill :status="$trip->status" /></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            {{-- Licenses to renew --}}
            <section class="rounded-md border border-gray-200 bg-white">
                <div class="border-b border-gray-200 px-4 py-2.5">
                    <h2 class="text-sm font-semibold text-[#0E2F30]">Licenses to renew</h2>
                </div>
                <div class="divide-y divide-gray-100 text-xs">
                    @forelse ($expiring as $d)
                        <a href="{{ route('drivers.show', $d) }}"
                            class="flex items-center justify-between gap-2 px-4 py-2.5 hover:bg-gray-50">
                            <div class="min-w-0">
                                <div class="truncate font-medium text-gray-800">{{ $d->user->name }}</div>
                                <div class="text-gray-500">Expires {{ $d->license_expiry->format('M d, Y') }}</div>
                            </div>
                            <span
                                class="shrink-0 rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase {{ $d->license_alert === 'expired' ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-700' }}">{{ $d->license_alert === 'expired' ? 'Expired' : 'Expiring' }}</span>
                        </a>
                    @empty
                        <p class="px-4 py-6 text-gray-500">No licenses expire in the next 30 days.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>

    <script type="module">
        (function() {
            const colors = {
                resting: '#2563eb',
                tracking: '#0B6E6E',
                offline: '#dc2626',
                waiting: '#6b7280'
            };

            const map = L.map('fleet-map', {
                scrollWheelZoom: false
            }).setView([14.17, 121.24], 9);
            L.tileLayer(@json(config('freightpath.map_tile_url')), {
                maxZoom: 19,
                attribution: @json(config('freightpath.map_attribution')),
            }).addTo(map);
            const layer = L.layerGroup().addTo(map);
            let fitted = false;

            async function load() {
                const note = document.getElementById('fleet-note');
                try {
                    const res = await fetch(@json(route('map.data')), {
                        headers: {
                            Accept: 'application/json'
                        },
                        credentials: 'same-origin',
                    });
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                    const data = await res.json();

                    layer.clearLayers();
                    const bounds = [];

                    data.trips.forEach(function(t) {
                        if (!t.last) return;
                        const ll = [t.last.lat, t.last.lng];
                        const marker = L.circleMarker(ll, {
                            radius: 11,
                            color: '#fff',
                            weight: 3,
                            fillColor: colors[t.state] || colors.waiting,
                            fillOpacity: 1,
                        }).addTo(layer);
                        const tip = document.createElement('span');
                        tip.textContent = t.driver + ' \u00b7 ' + t.label;
                        marker.bindTooltip(tip);
                        marker.on('click', function() {
                            window.location = t.url;
                        });
                        bounds.push(ll);
                    });

                    note.textContent = data.trips.length ?
                        data.trips.length + (data.trips.length === 1 ? ' trip' : ' trips') + ' on the road' :
                        'No trucks on the road right now';

                    if (bounds.length && !fitted) {
                        map.fitBounds(bounds, {
                            padding: [40, 40],
                            maxZoom: 13
                        });
                        fitted = true;
                    }
                } catch (e) {
                    note.textContent = 'Could not load positions. Retrying...';
                }
            }

            load();
            setInterval(load, 15000);
        })();
    </script>
</x-app-layout>
