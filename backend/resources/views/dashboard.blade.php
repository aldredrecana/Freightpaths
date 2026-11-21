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
                'All finished trips',
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
            'pending' => ['Trip assigned', 'bg-gray-100 text-gray-600'],
            'accepted' => ['Trip accepted', 'bg-emerald-50 text-emerald-600'],
            'en_route' => ['Trip started', 'bg-brand-50 text-brand-600'],
            'delivered' => ['Delivery completed', 'bg-emerald-50 text-emerald-600'],
            'needs_reassignment' => ['Driver declined', 'bg-red-50 text-red-600'],
            'cancelled' => ['Trip cancelled', 'bg-red-50 text-red-600'],
        ];
    @endphp

    <div class="py-10">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

            <x-page-header crumb="Dashboard" eyebrow="Overview" :title="$greeting . ', ' . $first"
                subtitle="Here's what's happening across your fleet today.">
                <x-slot name="actions">
                    <x-btn :href="route('schedules.create')">+ New schedule</x-btn>
                </x-slot>
            </x-page-header>

            @if ($alertItems->isEmpty())
                <div
                    class="mt-8 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-800">
                    All clear. Nothing needs your attention right now.
                </div>
            @else
                <div class="mt-8 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4"></div>
                @foreach ($alertItems as [$n, $label, $href, $cls])
                    <a href="{{ $href }}"
                        class="block rounded-2xl border p-4 text-sm font-medium hover:shadow-sm {{ $cls }}">
                        <span class="text-2xl font-bold">{{ $n }}</span> {{ $label }}
                    </a>
                @endforeach
        </div>
        @endif

        <div class="mt-6 grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($stats as [$label, $value, $note, $href, $icon])
                <a href="{{ $href }}"
                    class="block rounded-2xl border border-gray-200 bg-white p-6 shadow-sm transition hover:shadow-md">
                    <div class="flex items-start justify-between">
                        <p class="text-sm font-medium text-gray-500">{{ $label }}</p>
                        <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-5 w-5" fill="none"
                                stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
                                aria-hidden="true">{!! $icons[$icon] !!}</svg>
                        </span>
                    </div>
                    <p class="mt-4 text-4xl font-bold text-[#12263F]">{{ $value }}</p>
                    <p class="mt-2 text-xs text-gray-500">{{ $note }}</p>
                </a>
            @endforeach
        </div>

        <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
            <x-card class="lg:col-span-2">
                <div class="flex items-start justify-between px-6 pt-6">
                    <div>
                        <h2 class="text-lg font-semibold text-[#12263F]">Fleet map preview</h2>
                        <p id="fleet-note" class="mt-1 text-xs text-gray-500">Live positions from driver phones</p>
                    </div>
                    <a href="{{ route('map.index') }}" class="text-sm font-semibold text-brand-600 hover:underline">View
                        live map &rarr;</a>
                </div>
                <div class="mt-4 px-6 pb-6">
                    <div id="fleet-map" style="height: 18rem; z-index: 0;"
                        class="w-full overflow-hidden rounded-xl border border-gray-200"></div>
                </div>
            </x-card>

            <x-card>
                <h2 class="px-6 pt-6 text-lg font-semibold text-[#12263F]">Recent activity</h2>
                <p class="px-6 text-xs text-gray-500">Latest trip updates</p>
                <div class="mt-3 divide-y divide-gray-100">
                    @forelse ($activity as $log)
                        @php [$activityLabel, $chip] = $activityLabels[$log->status] ?? [ucfirst(str_replace('_', ' ', $log->status)), 'bg-gray-100 text-gray-600']; @endphp
                        <a href="{{ route('schedules.show', $log->schedule) }}"
                            class="flex items-start gap-3 px-6 py-4 hover:bg-gray-50/60">
                            <span
                                class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg {{ $chip }}">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4 w-4"
                                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" aria-hidden="true">
                                    @if ($log->status === 'en_route')
                                        <path d="M3 6h11v10H3z" />
                                        <path d="M14 10h4l3 3v3h-7z" />
                                        <circle cx="7" cy="17" r="2" />
                                        <circle cx="17" cy="17" r="2" />
                                    @else
                                        <path d="M5 13l4 4L19 7" />
                                    @endif
                                </svg>
                            </span>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-[#12263F]">{{ $activityLabel }}</p>
                                <p class="truncate text-xs text-gray-500">{{ $log->schedule->reference }} &middot;
                                    {{ $log->schedule->driver->user->name }}</p>
                                <p class="text-xs text-gray-400">{{ $log->logged_at->diffForHumans() }}</p>
                            </div>
                        </a>
                    @empty
                        <p class="px-6 py-6 text-sm text-gray-500">No activity yet.</p>
                    @endforelse
                </div>
            </x-card>
        </div>

        <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
            <x-card class="lg:col-span-2">
                <div class="flex items-center justify-between px-6 pt-6">
                    <h2 class="text-lg font-semibold text-[#12263F]">Today's trips</h2>
                    <a href="{{ route('schedules.index') }}"
                        class="text-sm font-semibold text-brand-600 hover:underline">View all</a>
                </div>
                <div class="mt-3 divide-y divide-gray-100 text-sm">
                    @forelse ($today as $trip)
                        <a href="{{ route('schedules.show', $trip) }}"
                            class="flex flex-wrap items-center justify-between gap-3 px-6 py-4 hover:bg-gray-50/60">
                            <div>
                                <div class="font-medium text-[#12263F]">
                                    {{ $trip->departure_at->format('g:i A') }} &middot; {{ $trip->reference }}
                                    &middot; {{ $trip->driver->user->name }}
                                </div>
                                <div class="text-gray-500">{{ $trip->origin_address }} &rarr;
                                    {{ $trip->destination_address }}</div>
                            </div>
                            <x-status-pill :status="$trip->status" />
                        </a>
                    @empty
                        <x-empty-state title="No trips today" message="Assign a trip to get the day started."
                            action-label="Assign trip" :action-href="route('schedules.create')" />
                    @endforelse
                </div>
            </x-card>

            <x-card>
                <h2 class="px-6 pt-6 text-lg font-semibold text-[#12263F]">Licenses to renew</h2>
                <div class="mt-3 divide-y divide-gray-100 text-sm">
                    @forelse ($expiring as $d)
                        <a href="{{ route('drivers.show', $d) }}" class="block px-6 py-4 hover:bg-gray-50/60">
                            <div class="font-medium text-[#12263F]">{{ $d->user->name }}</div>
                            <div class="text-gray-500">
                                Expires {{ $d->license_expiry->format('M d, Y') }}
                                <span
                                    class="ml-1 rounded px-1.5 py-0.5 text-[10px] font-bold uppercase text-white {{ $d->license_alert === 'expired' ? 'bg-red-600' : 'bg-amber-500' }}">{{ $d->license_alert === 'expired' ? 'Expired' : 'Expiring' }}</span>
                            </div>
                        </a>
                    @empty
                        <p class="px-6 py-6 text-gray-500">No licenses expire in the next 30 days.</p>
                    @endforelse
                </div>
            </x-card>
        </div>
    </div>
    </div>


    <script type="module">
        (function() {
            const colors = {
                resting: '#2563eb',
                tracking: '#FF8A1F',
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
                        data.trips.length + (data.trips.length === 1 ? ' trip' : ' trips') +
                        ' on the road \u00b7 live positions' :
                        'No driver is on the road right now';

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
