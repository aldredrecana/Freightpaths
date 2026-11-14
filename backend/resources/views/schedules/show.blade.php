<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Delivery {{ $schedule->reference }}</h2>
            <div class="flex items-center gap-4 text-sm">
                <a href="{{ auth()->user()->hasRole('driver') ? route('schedules.mine') : (in_array($schedule->status, ['delivered', 'cancelled'], true) ? route('transactions.index', ['trip' => $schedule->id]) : route('schedules.index')) }}"
                    class="text-gray-600 underline">Back</a>

                @if (!auth()->user()->hasRole('driver'))
                    @unless (in_array($schedule->status, ['delivered', 'cancelled', 'en_route'], true))
                        <a href="{{ route('schedules.edit', $schedule) }}" class="text-blue-600 underline">Edit</a>
                    @endunless

                    <x-confirm-delete :action="route('schedules.destroy', $schedule)" title="Delete this trip?"
                        message="Only pending trips can be deleted." />
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">



            @if ($errors->has('delete'))
                <div class="p-3 bg-red-100 text-red-800 rounded">{{ $errors->first('delete') }}</div>
            @endif

            @if ($schedule->urgency)
                <div
                    class="p-3 rounded {{ $schedule->urgency === 'overdue' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800' }}">
                    <strong class="uppercase">{{ $schedule->urgency }}</strong>: departure
                    {{ $schedule->departure_at->diffForHumans() }}
                    @if ($schedule->awaiting_since)
                        &middot; awaiting driver response for {{ $schedule->awaiting_since->diffForHumans(null, true) }}
                    @endif
                </div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg p-6 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div><span class="text-gray-500">Driver:</span> {{ $schedule->driver->user->name }}</div>
                <div><span class="text-gray-500">Truck:</span> {{ $schedule->truck->plate_no }} -
                    {{ $schedule->truck->model }}</div>
                <div><span class="text-gray-500">Origin:</span> {{ $schedule->origin_address }}</div>
                <div><span class="text-gray-500">Destination:</span> {{ $schedule->destination_address }}</div>
                <div><span class="text-gray-500">Departure:</span>
                    {{ $schedule->departure_at->format('M d, Y g:i A') }}</div>
                <div><span class="text-gray-500">Expected arrival:</span>
                    {{ $schedule->expected_arrival_at->format('M d, Y g:i A') }}</div>
                <div class="md:col-span-2"><span class="text-gray-500">Current status:</span>
                    <span class="font-semibold">{{ ucfirst(str_replace('_', ' ', $schedule->status)) }}</span>
                </div>
            </div>

            @if (count($nextStatuses))
                <form method="POST" action="{{ route('schedules.status', $schedule) }}"
                    class="bg-white shadow-sm sm:rounded-lg p-6">
                    @csrf
                    <h3 class="font-semibold text-gray-800 mb-4">Update delivery status</h3>
                    <x-input-label for="remarks" value="Remarks (optional)" />
                    <x-text-input id="remarks" name="remarks" class="block mt-1 w-full" :value="old('remarks')" />
                    <x-input-error :messages="$errors->get('remarks')" class="mt-2" />
                    <x-input-error :messages="$errors->get('status')" class="mt-2" />

                    <div class="mt-4 flex gap-3">
                        @foreach ($nextStatuses as $s)
                            <button type="submit" name="status" value="{{ $s }}"
                                class="px-4 py-2 rounded-md text-xs uppercase text-white {{ $s === 'cancelled' ? 'bg-red-600' : 'bg-gray-800' }}">
                                {{ ['accepted' => 'Accept Trip', 'en_route' => 'Depart / En Route', 'delivered' => 'Mark Delivered', 'cancelled' => 'Cancel Trip'][$s] }}
                            </button>
                        @endforeach
                    </div>
                </form>
            @endif

            @if (auth()->user()->hasRole('driver') && in_array($schedule->status, ['pending', 'accepted'], true))
                <form method="POST" action="{{ route('schedules.decline', $schedule) }}"
                    class="bg-white shadow-sm sm:rounded-lg p-6"
                    onsubmit="return confirm('Decline this trip? The dispatcher will be asked to assign another driver.')">
                    @csrf
                    <h3 class="font-semibold text-gray-800">Can't take this trip?</h3>
                    <p class="mb-4 mt-1 text-sm text-gray-500">This takes effect immediately so a replacement can be
                        found quickly.</p>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <x-input-label for="reason" value="Reason" />
                            <select id="reason" name="reason"
                                class="block mt-1 w-full border-gray-300 rounded-md shadow-sm" required>
                                <option value="">Select a reason</option>
                                @foreach (\App\Models\Schedule::DECLINE_REASONS as $value => $label)
                                    <option value="{{ $value }}" @selected(old('reason') === $value)>
                                        {{ $label }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('reason')" class="mt-2" />
                        </div>
                        <div class="md:col-span-2">
                            <x-input-label for="details" value="Details (required for Other)" />
                            <x-text-input id="details" name="details" class="block mt-1 w-full" :value="old('details')" />
                            <x-input-error :messages="$errors->get('details')" class="mt-2" />
                        </div>
                    </div>
                    <div class="mt-4">
                        <button class="px-4 py-2 rounded-md text-xs uppercase text-white bg-red-600">Can't take this
                            trip</button>
                    </div>
                </form>
            @endif

            @if (
                !auth()->user()->hasRole('driver') &&
                    in_array($schedule->status, ['pending', 'accepted', 'needs_reassignment'], true))
                <form method="POST" action="{{ route('schedules.reassign', $schedule) }}"
                    class="bg-white shadow-sm sm:rounded-lg p-6">
                    @csrf
                    <h3 class="font-semibold text-gray-800">
                        {{ $schedule->status === 'needs_reassignment' ? 'Find a replacement driver' : 'Reassign driver' }}
                    </h3>
                    <p class="mb-4 mt-1 text-sm text-gray-500">Off-duty drivers and drivers with overlapping trips are
                        not
                        listed. Call the driver first. They must still accept the trip.</p>

                    @if ($candidates->isEmpty())
                        <p class="text-sm text-red-700">No driver is free for this trip's time. You can postpone the
                            trip
                            (Edit) or cancel it.</p>
                    @else
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="md:col-span-2">
                                <x-input-label for="driver_id" value="New driver" />
                                <select id="driver_id" name="driver_id"
                                    class="block mt-1 w-full border-gray-300 rounded-md shadow-sm" required>
                                    <option value="">Select a driver</option>
                                    @foreach ($candidates as $c)
                                        <option value="{{ $c->id }}">
                                            {{ $c->user->name }} &middot; {{ $c->contact_no }} &middot;
                                            {{ isset($lastDone[$c->id]) ? 'finished a trip ' . \Illuminate\Support\Carbon::parse($lastDone[$c->id])->diffForHumans() : 'no finished trips yet' }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('driver_id')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="note" value="Note (optional)" />
                                <x-text-input id="note" name="note" class="block mt-1 w-full" />
                            </div>
                        </div>
                        <div class="mt-4"><x-primary-button>Reassign</x-primary-button></div>
                    @endif
                </form>
            @endif

            @if (in_array($schedule->status, ['en_route', 'delivered'], true))
                <div id="tracking-card" class="bg-white shadow-sm sm:rounded-lg p-6"
                    data-url="{{ route('schedules.tracking', $schedule) }}"
                    data-live="{{ $schedule->status === 'en_route' ? '1' : '0' }}">
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                        <h3 class="font-semibold text-gray-800">Location &amp; rests</h3>
                        <span id="tracking-state"
                            class="text-sm rounded-md px-2.5 py-1 bg-gray-100 text-gray-600">Loading...</span>
                    </div>
                    <p id="tracking-message" class="text-sm text-gray-600 mb-3"></p>
                    <div id="tracking-map" style="height: 20rem; z-index: 0;"
                        class="w-full rounded-lg border border-gray-200"></div>
                    <h4 class="mt-5 mb-2 text-sm font-semibold text-gray-800">Rest periods</h4>
                    <ul id="tracking-rests" class="text-sm text-gray-700"></ul>
                </div>

                <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css"
                    integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
                <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"
                    integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
                <script>
                    (function() {
                        const card = document.getElementById('tracking-card');
                        const url = card.dataset.url;
                        const live = card.dataset.live === '1';

                        const pill = {
                            resting: 'bg-amber-100 text-amber-800',
                            tracking: 'bg-blue-50 text-blue-700',
                            offline: 'bg-red-100 text-red-800',
                            finished: 'bg-emerald-50 text-emerald-700',
                            waiting: 'bg-gray-100 text-gray-600',
                        };

                        const map = L.map('tracking-map').setView([12.88, 121.77], 6);
                        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            maxZoom: 19,
                            attribution: '&copy; OpenStreetMap contributors',
                        }).addTo(map);

                        const layer = L.layerGroup().addTo(map);
                        let fitted = false;

                        function render(d) {
                            layer.clearLayers();

                            const stateEl = document.getElementById('tracking-state');
                            stateEl.textContent = d.label;
                            stateEl.className = 'text-sm rounded-md px-2.5 py-1 ' + (pill[d.state] || pill.waiting);
                            document.getElementById('tracking-message').textContent = d.message;

                            const bounds = [];

                            if (d.path.length > 1) {
                                L.polyline(d.path, {
                                    color: '#2563eb',
                                    weight: 4,
                                    opacity: 0.8
                                }).addTo(layer);
                            }
                            if (d.path.length) {
                                bounds.push(d.path[0]);
                                L.circleMarker(d.path[0], {
                                        radius: 7,
                                        color: '#047857',
                                        fillColor: '#10b981',
                                        fillOpacity: 1
                                    })
                                    .bindTooltip('Start of tracking').addTo(layer);
                            }

                            d.rests.forEach(function(r, i) {
                                bounds.push([r.lat, r.lng]);
                                L.circleMarker([r.lat, r.lng], {
                                        radius: 11,
                                        color: '#b45309',
                                        fillColor: '#f59e0b',
                                        fillOpacity: 0.85
                                    })
                                    .bindTooltip('Rest ' + (i + 1) + ': ' + r.minutes + ' min').addTo(layer);
                            });

                            if (d.last) {
                                bounds.push([d.last.lat, d.last.lng]);
                                L.circleMarker([d.last.lat, d.last.lng], {
                                        radius: 9,
                                        weight: 3,
                                        color: '#1d4ed8',
                                        fillColor: '#3b82f6',
                                        fillOpacity: 1
                                    })
                                    .bindTooltip('Last known position').addTo(layer);
                            }

                            if (bounds.length && !fitted) {
                                map.fitBounds(bounds, {
                                    padding: [30, 30],
                                    maxZoom: 16
                                });
                                fitted = true;
                            }

                            const list = document.getElementById('tracking-rests');
                            list.replaceChildren();
                            if (!d.rests.length) {
                                const li = document.createElement('li');
                                li.textContent = 'No rest periods yet.';
                                list.appendChild(li);
                            }
                            d.rests.forEach(function(r, i) {
                                const li = document.createElement('li');
                                li.textContent = 'Rest ' + (i + 1) + ': ' + r.from + ' to ' + r.to + ' (' + r.minutes +
                                    ' min)';
                                list.appendChild(li);
                            });
                        }

                        async function load() {
                            try {
                                const res = await fetch(url, {
                                    headers: {
                                        Accept: 'application/json'
                                    },
                                    credentials: 'same-origin'
                                });
                                if (!res.ok) throw new Error('HTTP ' + res.status);
                                render(await res.json());
                            } catch (e) {
                                document.getElementById('tracking-message').textContent =
                                    'Could not load tracking data. Retrying...';
                            }
                        }

                        load();
                        if (live) setInterval(load, 15000);
                    })();
                </script>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="font-semibold text-gray-800 mb-4">Delivery timeline</h3>
                <ol class="relative border-l border-gray-300 ml-3">
                    @forelse ($schedule->statusLogs as $log)
                        <li class="mb-6 ml-6">
                            <span
                                class="absolute -left-1.5 mt-1.5 h-3 w-3 rounded-full {{ $loop->first ? 'bg-indigo-600' : 'bg-gray-400' }}"></span>
                            <p class="font-semibold text-sm {{ $loop->first ? 'text-indigo-700' : 'text-gray-800' }}">
                                {{ ucfirst(str_replace('_', ' ', $log->status)) }}
                                @if ($log->is_late)
                                    <span
                                        class="ml-2 rounded bg-amber-500 px-1.5 py-0.5 text-[10px] font-bold uppercase text-white">Late</span>
                                @endif
                            </p>
                            <p class="text-xs text-gray-500">{{ $log->logged_at->format('M d, Y g:i A') }} &middot;
                                {{ $log->loggedBy->name }}</p>
                            @if ($log->remarks)
                                <p class="text-sm text-gray-600 mt-1">{{ $log->remarks }}</p>
                            @endif
                        </li>
                    @empty
                        <li class="ml-6 text-sm text-gray-500">No history yet.</li>
                    @endforelse
                </ol>
            </div>

        </div>
    </div>
</x-app-layout>
