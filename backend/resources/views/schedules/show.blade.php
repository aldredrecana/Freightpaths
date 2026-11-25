<x-app-layout>
    <div class="py-10">
        <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">

            <x-page-header crumb="Trip details" :title="'Delivery ' . $schedule->reference" :subtitle="$schedule->origin_address . ' → ' . $schedule->destination_address">
                <x-slot name="actions">
                    <x-btn variant="secondary" :href="auth()->user()->hasRole('driver')
                        ? route('schedules.mine')
                        : (in_array($schedule->status, ['delivered', 'cancelled'], true)
                            ? route('transactions.index', ['trip' => $schedule->id])
                            : route('schedules.index'))">Back</x-btn>

                    @if (!auth()->user()->hasRole('driver'))
                        @if ($schedule->canOfferBackload())
                            <x-btn variant="secondary" :href="route('schedules.create', ['backload_of' => $schedule->id])">Offer
                                backload</x-btn>
                        @endif
                        @unless (in_array($schedule->status, ['delivered', 'cancelled', 'en_route'], true))
                            <x-btn :href="route('schedules.edit', $schedule)">Edit trip</x-btn>
                        @endunless

                        @if ($schedule->status === 'pending')
                            <x-confirm-delete :action="route('schedules.destroy', $schedule)" title="Delete this trip?"
                                message="Only pending trips can be deleted." />
                        @endif
                    @endif
                </x-slot>
            </x-page-header>

            {{-- Backload: a return load linked to another trip --}}
            @if ($schedule->backloadOf || $schedule->backloads->isNotEmpty())
                <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-2.5 text-xs text-amber-900">
                    @if ($schedule->backloadOf)
                        <span
                            class="mr-1 rounded bg-amber-200 px-1.5 py-0.5 text-[10px] font-bold uppercase">Backload</span>
                        Return load after
                        <a href="{{ route('schedules.show', $schedule->backloadOf) }}"
                            class="font-semibold underline">{{ $schedule->backloadOf->reference }}</a>.
                        The driver can accept or decline it.
                    @endif
                    @foreach ($schedule->backloads as $back)
                        <div class="{{ $schedule->backloadOf ? 'mt-1' : '' }}">
                            Backload offered:
                            <a href="{{ route('schedules.show', $back) }}"
                                class="font-semibold underline">{{ $back->reference }}</a>
                            ({{ \Illuminate\Support\Str::of($back->status)->replace('_', ' ')->title() }})
                            &middot;
                            {{ $back->origin_address }} &rarr; {{ $back->destination_address }}
                        </div>
                    @endforeach
                </div>
            @endif

            @if ($errors->has('delete'))
                <div class="rounded-lg bg-red-100 p-3 text-sm text-red-800">{{ $errors->first('delete') }}</div>
            @endif

            @if ($schedule->urgency)
                <div
                    class="rounded-lg p-3 text-sm {{ $schedule->urgency === 'overdue' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800' }}">
                    <strong class="uppercase">{{ $schedule->urgency }}</strong>: departure
                    {{ $schedule->departure_at->diffForHumans() }}
                    @if ($schedule->awaiting_since)
                        &middot; awaiting driver response for
                        {{ $schedule->awaiting_since->diffForHumans(null, true) }}
                    @endif
                </div>
            @endif

            <div
                class="grid grid-cols-1 gap-5 rounded-2xl border border-gray-200 bg-white p-6 text-sm shadow-sm md:grid-cols-2">
                @foreach ([
        'Driver' => $schedule->driver->user->name,
        'Truck' => $schedule->truck->plate_no . ' · ' . $schedule->truck->model,
        'Origin' => $schedule->origin_address,
        'Destination' => $schedule->destination_address,
        'Departure' => $schedule->departure_at->format('M d, Y g:i A'),
        'Expected arrival' => $schedule->expected_arrival_at->format('M d, Y g:i A'),
    ] as $label => $value)
                    <div>
                        <div class="text-xs uppercase tracking-wider text-gray-400">{{ $label }}</div>
                        <div class="mt-1 text-gray-800">{{ $value }}</div>
                    </div>
                @endforeach
                <div class="md:col-span-2">
                    <div class="text-xs uppercase tracking-wider text-gray-400">Current status</div>
                    <div class="mt-1"><x-status-pill :status="$schedule->status" /></div>
                </div>
            </div>

            @if (count($nextStatuses))
                <form method="POST" action="{{ route('schedules.status', $schedule) }}"
                    class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                    @csrf
                    <h3 class="font-semibold text-gray-800 mb-4">Update delivery status</h3>
                    <x-input-label for="remarks" value="Remarks (optional)" />
                    <x-text-input id="remarks" name="remarks" class="block mt-1 w-full" :value="old('remarks')" />
                    <x-input-error :messages="$errors->get('remarks')" class="mt-2" />
                    <x-input-error :messages="$errors->get('status')" class="mt-2" />

                    <div class="mt-4 flex gap-3">
                        @foreach ($nextStatuses as $s)
                            <x-btn name="status" value="{{ $s }}" :variant="$s === 'cancelled' ? 'danger' : 'primary'">
                                {{ ['accepted' => 'Accept trip', 'en_route' => 'Start trip', 'delivered' => 'Mark delivered', 'cancelled' => 'Cancel trip'][$s] }}
                            </x-btn>
                        @endforeach
                    </div>
                </form>
            @endif

            @if (auth()->user()->hasRole('driver') && in_array($schedule->status, ['pending', 'accepted'], true))
                <form method="POST" action="{{ route('schedules.decline', $schedule) }}"
                    class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm"
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
                    class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
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
                        <div class="mt-4"><x-btn>Reassign</x-btn></div>
                    @endif
                </form>
            @endif

            {{-- Problems the driver reported on this trip --}}
            @if (!auth()->user()->hasRole('driver') && $schedule->incidents->isNotEmpty())
                <div class="rounded-md border border-red-200 bg-white">
                    <div class="flex items-center justify-between border-b border-red-100 bg-red-50 px-4 py-2.5">
                        <h3 class="text-sm font-semibold text-red-900">Problem reports</h3>
                        <a href="{{ route('incidents.index', ['status' => 'all']) }}"
                            class="text-xs font-medium text-red-800 hover:underline">All reports &rarr;</a>
                    </div>
                    <ul class="divide-y divide-gray-100 text-xs">
                        @foreach ($schedule->incidents as $incident)
                            <li class="flex flex-wrap items-start justify-between gap-3 px-4 py-2.5">
                                <div>
                                    <span class="font-semibold text-gray-800">{{ $incident->label }}</span>
                                    <span class="text-gray-400">&middot;
                                        {{ $incident->reported_at->format('M d, g:i A') }}</span>
                                    @if ($incident->note)
                                        <p class="mt-0.5 text-gray-600">{{ $incident->note }}</p>
                                    @endif
                                    @if ($incident->map_url)
                                        <a href="{{ $incident->map_url }}" target="_blank" rel="noopener"
                                            class="mt-0.5 inline-block text-brand-700 hover:underline">Open location in
                                            Maps &rarr;</a>
                                    @endif
                                </div>
                                @if ($incident->resolved_at)
                                    <span
                                        class="rounded bg-emerald-50 px-1.5 py-0.5 text-[11px] font-semibold text-emerald-700">Resolved</span>
                                @else
                                    <form method="POST" action="{{ route('incidents.resolve', $incident) }}">
                                        @csrf
                                        <button type="submit"
                                            class="rounded-md bg-brand-600 px-2.5 py-1.5 text-xs font-medium text-white hover:bg-brand-700">Mark
                                            resolved</button>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (!in_array($schedule->status, ['en_route', 'delivered'], true) && $schedule->hasPins())
                <div id="route-card" class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="font-semibold text-gray-800">Planned route</h3>
                    <p class="mb-3 mt-1 text-sm text-gray-600">A = pickup, B = drop-off. The blue line is the suggested
                        road
                        route. GPS tracking starts when the driver starts the trip.</p>
                    <div id="route-map" style="height: 18rem; z-index: 0;"
                        class="w-full rounded-lg border border-gray-200"></div>
                </div>

                <script type="module">
                    (function() {
                        const map = L.map('route-map');
                        L.tileLayer(@json(config('freightpath.map_tile_url')), {
                            maxZoom: 19,
                            attribution: @json(config('freightpath.map_attribution')),
                        }).addTo(map);

                        const points = window.drawPlannedRoute(L.layerGroup().addTo(map), @json($schedule->routePlan()));
                        if (points.length === 1) map.setView(points[0], 15);
                        else map.fitBounds(points, {
                            padding: [40, 40]
                        });
                    })();
                </script>
            @endif

            @if (in_array($schedule->status, ['en_route', 'delivered'], true))

                {{-- Proof of delivery: the photo and the receiver's signature from the driver's app --}}
                @if (!auth()->user()->hasRole('driver') && $schedule->proof)
                    @php $proof = $schedule->proof; @endphp
                    <div class="rounded-md border border-gray-200 bg-white">
                        <div
                            class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 px-4 py-2.5">
                            <h3 class="text-sm font-semibold text-[#0E2F30]">Proof of delivery</h3>
                            <span class="text-xs text-gray-500">Received by <span
                                    class="font-semibold text-gray-800">{{ $proof->received_by }}</span>
                                &middot; {{ $proof->captured_at->format('M d, Y g:i A') }}
                                @if ($proof->map_url)
                                    &middot; <a href="{{ $proof->map_url }}" target="_blank" rel="noopener"
                                        class="text-brand-700 hover:underline">Location</a>
                                @endif
                            </span>
                        </div>
                        <div class="grid grid-cols-1 gap-4 p-4 sm:grid-cols-2">
                            <a href="{{ route('schedules.proof', [$schedule, 'photo']) }}" target="_blank"
                                rel="noopener" class="block">
                                <p class="mb-1 text-[11px] font-medium uppercase tracking-wide text-gray-400">Delivery
                                    photo</p>
                                <img src="{{ route('schedules.proof', [$schedule, 'photo']) }}"
                                    alt="Photo of the delivered goods"
                                    class="h-56 w-full rounded-md border border-gray-200 object-cover">
                            </a>
                            <div>
                                <p class="mb-1 text-[11px] font-medium uppercase tracking-wide text-gray-400">
                                    Receiver's signature</p>
                                <img src="{{ route('schedules.proof', [$schedule, 'signature']) }}"
                                    alt="Signature of {{ $proof->received_by }}"
                                    class="h-56 w-full rounded-md border border-gray-200 bg-white object-contain">
                                @if ($proof->note)
                                    <p class="mt-2 text-xs italic text-gray-600">"{{ $proof->note }}"</p>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
                <div id="tracking-card" class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm"
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
                    <h4 class="mt-5 mb-2 text-sm font-semibold text-gray-800">Breaks (tapped by the driver)</h4>
                    <ul id="tracking-breaks" class="text-sm text-gray-700"></ul>
                </div>

                <script type="module">
                    (function() {
                        const card = document.getElementById('tracking-card');
                        const url = card.dataset.url;
                        const live = card.dataset.live === '1';

                        const pill = {
                            resting: 'bg-amber-100 text-amber-800',
                            tracking: 'bg-brand-50 text-brand-700',
                            offline: 'bg-red-100 text-red-800',
                            finished: 'bg-emerald-50 text-emerald-700',
                            waiting: 'bg-gray-100 text-gray-600',
                        };

                        const map = L.map('tracking-map').setView([12.88, 121.77], 6);
                        L.tileLayer(@json(config('freightpath.map_tile_url')), {
                            maxZoom: 19,
                            attribution: @json(config('freightpath.map_attribution')),
                        }).addTo(map);

                        const layer = L.layerGroup().addTo(map);
                        const plan = @json($schedule->routePlan()); // pickup (A) and drop-off (B) pins
                        let fitted = false;

                        function render(d) {
                            layer.clearLayers();

                            const stateEl = document.getElementById('tracking-state');
                            stateEl.textContent = d.label;
                            stateEl.className = 'text-sm rounded-md px-2.5 py-1 ' + (pill[d.state] || pill.waiting);
                            document.getElementById('tracking-message').textContent = d.message;

                            const bounds = [];
                            bounds.push(...window.drawPlannedRoute(layer, plan));

                            const segments = d.segments || [{
                                type: 'driven',
                                points: d.path
                            }];
                            segments.forEach(function(seg) {
                                if (seg.points.length < 2) return;
                                L.polyline(seg.points, seg.type === 'gap' ? {
                                        color: '#6B7280',
                                        weight: 4,
                                        opacity: 0.9,
                                        dashArray: '6 10'
                                    } : {
                                        color: '#0B6E6E',
                                        weight: 5,
                                        opacity: 0.9
                                    }).bindTooltip(seg.type === 'gap' ? 'No signal here (likely route)' : 'Driven route')
                                    .addTo(layer);
                            });
                            const start = segments.length && segments[0].points.length ? segments[0].points[0] : null;
                            if (start) {
                                bounds.push(start);
                                L.circleMarker(start, {
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

                            // Breaks the driver started with "Take a break" in the app.
                            const breaks = document.getElementById('tracking-breaks');
                            breaks.replaceChildren();
                            const all = d.breaks || [];
                            all.forEach(function(b, i) {
                                const li = document.createElement('li');
                                li.textContent = 'Break ' + (i + 1) + ': ' + b.from + ' to ' + (b.to || 'now (on break)') +
                                    ' (' + b.minutes + ' min)';
                                breaks.appendChild(li);
                            });
                            const li = document.createElement('li');
                            li.className = all.length ? 'mt-1 font-medium text-gray-800' : '';
                            li.textContent = all.length ?
                                'Total: ' + all.reduce(function(sum, b) {
                                    return sum + b.minutes;
                                }, 0) + ' min in ' + all.length + ' break(s)' :
                                'No breaks yet.';
                            breaks.appendChild(li);
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

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                <h3 class="font-semibold text-gray-800 mb-4">Delivery timeline</h3>
                <ol class="relative border-l border-gray-300 ml-3">
                    @forelse ($schedule->statusLogs as $log)
                        <li class="mb-6 ml-6">
                            <span
                                class="absolute -left-1.5 mt-1.5 h-3 w-3 rounded-full {{ $loop->first ? 'bg-brand-600' : 'bg-gray-400' }}"></span>
                            <p class="font-semibold text-sm {{ $loop->first ? 'text-brand-700' : 'text-gray-800' }}">
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
