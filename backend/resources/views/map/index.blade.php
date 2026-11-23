<x-app-layout>
    {{-- GPS Tracking: a full-size map with the list of trucks on the road beside it
         (the layout fleet tools use). The page fills the screen under the top bar. --}}
    <div class="flex flex-col overflow-hidden lg:h-[calc(100vh-5rem-1px)] lg:flex-row"></div>

    {{-- Map (edge to edge) --}}
    <section class="relative h-[60vh] min-w-0 flex-1 lg:h-auto" style="z-index: 0;"></section>
    <div id="map-canvas" class="absolute inset-0"></div>

    {{-- Title and legend, floating on the map --}}
    <div class="pointer-events-none absolute left-3 top-3 z-[1000] flex flex-col gap-2">
        <div class="pointer-events-auto rounded-md border border-gray-200 bg-white/95 px-3 py-2 shadow-sm">
            <h1 class="text-sm font-semibold text-[#0E2F30]">GPS Tracking</h1>
            <p class="mt-0.5 text-[11px] text-gray-500">Location updates every 15 seconds</p>
        </div>
        <div
            class="pointer-events-auto flex flex-wrap gap-x-3 gap-y-1 rounded-md border border-gray-200 bg-white/95 px-3 py-1.5 text-[11px] text-gray-600 shadow-sm">
            <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full"
                    style="background:#0B6E6E"></span>On the road</span>
            <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full"
                    style="background:#2563eb"></span>Resting</span>
            <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full"
                    style="background:#dc2626"></span>No signal</span>
            <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full"
                    style="background:#6b7280"></span>Waiting</span>
        </div>
    </div>

    {{-- Empty map message --}}
    <div id="map-empty" style="display: none;"
        class="pointer-events-none absolute inset-0 z-[999] flex items-center justify-center">
        <div class="rounded-md border border-gray-200 bg-white/95 px-5 py-4 text-center shadow-sm">
            <p class="text-sm font-semibold text-[#0E2F30]">No trucks on the road</p>
            <p class="mt-1 text-xs text-gray-500">A truck appears here when its driver taps Start trip.</p>
        </div>
    </div>

    {{-- The selected trip, floating at the bottom of the map --}}
    <div id="map-info" style="display: none;"
        class="absolute bottom-3 left-3 right-3 z-[1000] rounded-md border border-gray-200 bg-white shadow-md lg:right-auto lg:w-[32rem]">
    </div>
    </section>

    {{-- Side panel: trucks on the road --}}
    <aside class="flex w-full flex-col border-l border-gray-200 bg-white lg:w-[22rem] lg:shrink-0"></aside>
    <div class="flex items-center justify-between gap-2 border-b border-gray-200 px-4 py-3">
        <div class="flex items-center gap-2">
            <h2 class="text-sm font-semibold text-[#0E2F30]">On the road</h2>
            <span id="map-count"
                class="rounded bg-gray-100 px-1.5 py-0.5 text-[11px] font-semibold text-gray-600">0</span>
        </div>
        <div class="flex items-center gap-1">
            <button id="map-all" type="button"
                class="rounded border border-gray-200 px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50">Show
                all</button>
            <button id="map-refresh" type="button" title="Refresh now" aria-label="Refresh now"
                class="rounded border border-gray-200 p-1.5 text-gray-600 hover:bg-gray-50">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                    aria-hidden="true">
                    <path d="M21 12a9 9 0 1 1-3-6.7L21 8" />
                    <path d="M21 3v5h-5" />
                </svg>
            </button>
        </div>
    </div>

    <div class="border-b border-gray-200 px-4 py-2">
        <input id="map-search" type="search" placeholder="Search driver, plate or trip no."
            class="w-full rounded border border-gray-200 px-2.5 py-1.5 text-xs placeholder-gray-400 focus:border-brand-500 focus:ring-brand-500">
    </div>

    <div id="map-list" class="flex-1 divide-y divide-gray-100 overflow-y-auto" style="max-height: 70vh;"></div>

    <p id="map-updated" class="border-t border-gray-200 px-4 py-2 text-[11px] text-gray-400">Loading...</p>
    </aside>
    </div>

    <script type="module">
        (function() {
            const dataUrl = "{{ route('map.data') }}";

            const stateColor = {
                resting: '#2563eb',
                tracking: '#0B6E6E',
                offline: '#dc2626',
                waiting: '#6b7280'
            };
            const stateText = {
                resting: 'text-blue-700',
                tracking: 'text-brand-700',
                offline: 'text-red-700',
                waiting: 'text-gray-600'
            };

            // A truck seen from above, nose pointing up (north). It is turned to face the direction of travel.
            function truckSvg(color) {
                return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 28 46" width="28" height="46">' +
                    '<rect x="3" y="15" width="22" height="29" rx="3" fill="' + color +
                    '" stroke="#fff" stroke-width="2"/>' +
                    '<rect x="11" y="19" width="6" height="21" rx="2" fill="#fff" opacity=".25"/>' +
                    '<rect x="5" y="2" width="18" height="12" rx="5" fill="' + color +
                    '" stroke="#fff" stroke-width="2"/>' +
                    '<rect x="8" y="4.5" width="12" height="4" rx="1.5" fill="#fff" opacity=".9"/>' +
                    '</svg>';
            }

            const map = L.map('map-canvas', {
                zoomControl: false
            }).setView([14.17, 121.24], 9);
            L.control.zoom({
                position: 'topright'
            }).addTo(map);
            L.tileLayer(@json(config('freightpath.map_tile_url')), {
                maxZoom: 19,
                attribution: @json(config('freightpath.map_attribution')),
            }).addTo(map);
            const layer = L.layerGroup().addTo(map);

            let trips = [];
            let selectedId = null; // null = "Show all"
            let routeData = null;
            let fitKey = null; // what the map was last zoomed to
            let search = '';

            // Small helpers: text is always set with textContent, never as HTML.
            function el(tag, className, text) {
                const node = document.createElement(tag);
                if (className) node.className = className;
                if (text !== undefined) node.textContent = text;
                return node;
            }

            function dot(color) {
                const d = el('span', 'inline-block h-2 w-2 shrink-0 rounded-full');
                d.style.background = color;
                return d;
            }

            // "just now", "40 s ago", "3 min ago", "1 h 5 min ago"
            function ago(seconds) {
                if (seconds === null || seconds === undefined) return 'No GPS yet';
                if (seconds < 10) return 'just now';
                if (seconds < 60) return seconds + ' s ago';
                const m = Math.floor(seconds / 60);
                if (m < 60) return m + ' min ago';
                return Math.floor(m / 60) + ' h ' + (m % 60) + ' min ago';
            }

            function speed(t) {
                return t.last && t.last.speed_kmh !== null && t.last.speed_kmh !== undefined ?
                    t.last.speed_kmh + ' km/h' : '';
            }

            // heading = direction of travel in degrees (0 = north, 90 = east); null = not moving.
            function truckIcon(color, selected, heading) {
                const turn = heading === null || heading === undefined ? 0 : heading;
                const size = selected ? 1.25 : 1;
                return L.divIcon({
                    className: '',
                    html: '<div style="width:48px;height:48px;display:flex;align-items:center;justify-content:center;' +
                        'transform:rotate(' + turn + 'deg) scale(' + size + ');transition:transform .6s;' +
                        'filter:drop-shadow(0 2px 3px rgba(0,0,0,.45));">' + truckSvg(color) + '</div>',
                    iconSize: [48, 48],
                    iconAnchor: [24, 24], // the middle of the truck sits on the GPS point
                });
            }

            async function getJson(url) {
                const res = await fetch(url, {
                    headers: {
                        Accept: 'application/json'
                    },
                    credentials: 'same-origin'
                });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            }

            async function refresh() {
                try {
                    trips = (await getJson(dataUrl)).trips;

                    if (selectedId !== null && !trips.some(function(t) {
                            return t.id === selectedId;
                        })) {
                        selectedId = null; // that trip is no longer on the road
                        routeData = null;
                    }
                    if (selectedId !== null) {
                        const t = trips.find(function(x) {
                            return x.id === selectedId;
                        });
                        routeData = await getJson(t.tracking_url);
                    }
                } catch (e) {
                    document.getElementById('map-updated').textContent = 'Could not load. Retrying...';
                    return;
                }
                document.getElementById('map-updated').textContent = 'Updated ' + new Date().toLocaleTimeString(
                    'en-PH', {
                        hour: 'numeric',
                        minute: '2-digit',
                        second: '2-digit'
                    }) + ' · refreshes every 15 seconds';
                render();
            }

            function select(id) {
                selectedId = id;
                routeData = null;
                refresh();
            }

            function showAll() {
                selectedId = null;
                routeData = null;
                fitKey = null;
                render();
            }

            function matches(t) {
                if (!search) return true;
                return [t.driver, t.truck, t.reference].join(' ').toLowerCase().includes(search);
            }

            function renderList() {
                const list = document.getElementById('map-list');
                list.replaceChildren();
                document.getElementById('map-count').textContent = trips.length;

                if (!trips.length) {
                    list.appendChild(el('p', 'px-4 py-6 text-xs text-gray-500',
                        'No trips on the road. Trips appear here when a driver starts one.'));
                    return;
                }

                const shown = trips.filter(matches);
                if (!shown.length) {
                    list.appendChild(el('p', 'px-4 py-6 text-xs text-gray-500', 'No match for "' + search + '".'));
                    return;
                }

                shown.forEach(function(t) {
                    const selected = t.id === selectedId;
                    const row = el('button', 'block w-full border-l-2 px-4 py-2.5 text-left hover:bg-gray-50 ' +
                        (selected ? 'border-brand-600 bg-brand-50/60' : 'border-transparent'));
                    row.type = 'button';

                    // Line 1: status dot, plate, time since the last GPS point
                    const top = el('div', 'flex items-center gap-2');
                    top.appendChild(dot(stateColor[t.state] || stateColor.waiting));
                    top.appendChild(el('span', 'font-mono text-xs font-semibold text-[#0E2F30]', t.truck));
                    top.appendChild(el('span', 'ml-auto text-[11px] text-gray-500', ago(t.last ? t.last
                        .seconds_ago : null)));
                    row.appendChild(top);

                    // Line 2: driver and trip number
                    const mid = el('div', 'mt-0.5 flex items-center gap-2 pl-4 text-xs');
                    mid.appendChild(el('span', 'truncate text-gray-800', t.driver));
                    mid.appendChild(el('span', 'text-gray-400', t.reference));
                    row.appendChild(mid);

                    // Line 3: status, speed and destination
                    const bottom = el('div', 'mt-0.5 flex items-center gap-2 pl-4 text-[11px]');
                    bottom.appendChild(el('span', 'font-medium ' + (stateText[t.state] || stateText.waiting), t
                        .label));
                    if (speed(t)) bottom.appendChild(el('span', 'text-gray-500', speed(t)));
                    bottom.appendChild(el('span', 'truncate text-gray-400', '\u2192 ' + t.destination));
                    row.appendChild(bottom);

                    row.addEventListener('click', function() {
                        select(t.id);
                    });
                    list.appendChild(row);
                });
            }

            function renderMap() {
                layer.clearLayers();
                const bounds = [];

                document.getElementById('map-empty').style.display = trips.length ? 'none' : 'flex';

                if (selectedId === null) {
                    trips.forEach(function(t) {
                        if (!t.last) return;
                        const ll = [t.last.lat, t.last.lng];
                        const marker = L.marker(ll, {
                            icon: truckIcon(stateColor[t.state] || stateColor.waiting, false, t.last
                                .heading)
                        }).addTo(layer);
                        marker.bindTooltip(el('span', '', t.truck + ' \u00b7 ' + t.driver), {
                            direction: 'top',
                            offset: [0, -22]
                        });
                        marker.on('click', function() {
                            select(t.id);
                        });
                        bounds.push(ll);
                    });
                } else if (routeData) {
                    const d = routeData;

                    // Driven parts: drawn like a highlighted road (white edge, teal line on top).
                    // Lost-signal parts: a dashed grey line along the most likely road.
                    const segments = d.segments || [{
                        type: 'driven',
                        points: d.path
                    }];
                    segments.forEach(function(seg) {
                        if (seg.points.length < 2) return;
                        if (seg.type === 'gap') {
                            L.polyline(seg.points, {
                                color: '#6B7280',
                                weight: 4,
                                opacity: 0.9,
                                dashArray: '6 10',
                                lineCap: 'round'
                            }).bindTooltip('No signal here (likely route)').addTo(layer);
                            return;
                        }
                        L.polyline(seg.points, {
                            color: '#ffffff',
                            weight: 10,
                            opacity: 0.95,
                            lineCap: 'round',
                            lineJoin: 'round'
                        }).addTo(layer);
                        L.polyline(seg.points, {
                            color: '#0B6E6E',
                            weight: 6,
                            opacity: 1,
                            lineCap: 'round',
                            lineJoin: 'round'
                        }).addTo(layer);
                    });
                    segments.forEach(function(seg) {
                        seg.points.forEach(function(p) {
                            bounds.push(p);
                        });
                    });

                    const start = segments.length && segments[0].points.length ? segments[0].points[0] : null;
                    if (start) {
                        L.circleMarker(start, {
                                radius: 7,
                                color: '#047857',
                                fillColor: '#10b981',
                                fillOpacity: 1
                            })
                            .bindTooltip('Start of tracking').addTo(layer);
                    }
                    d.rests.forEach(function(r, i) {
                        L.circleMarker([r.lat, r.lng], {
                                radius: 11,
                                color: '#1d4ed8',
                                fillColor: '#3b82f6',
                                fillOpacity: 0.85
                            })
                            .bindTooltip('Rest ' + (i + 1) + ': ' + r.minutes + ' min').addTo(layer);
                    });
                    if (d.last) {
                        L.marker([d.last.lat, d.last.lng], {
                            icon: truckIcon(stateColor[d.state] || stateColor.waiting, true, d.last.heading)
                        }).addTo(layer);
                    }
                }

                const key = selectedId === null ? 'all' : String(selectedId);
                if (key !== fitKey && bounds.length) {
                    // Keep the trucks clear of the title (top left) and the trip card (bottom).
                    map.fitBounds(bounds, {
                        paddingTopLeft: [60, 130],
                        paddingBottomRight: [60, selectedId === null ? 60 : 240],
                        maxZoom: 15
                    });
                    fitKey = key;
                }
            }

            // The selected trip's details, floating at the bottom of the map.
            function renderInfo() {
                const box = document.getElementById('map-info');
                box.replaceChildren();

                const allBtn = document.getElementById('map-all');
                allBtn.className = 'rounded border px-2 py-1 text-xs font-medium ' +
                    (selectedId === null ? 'border-brand-600 bg-brand-600 text-white' :
                        'border-gray-200 text-gray-700 hover:bg-gray-50');

                if (selectedId === null) {
                    box.style.display = 'none';
                    return;
                }

                const t = trips.find(function(x) {
                    return x.id === selectedId;
                });
                const d = routeData || t;
                box.style.display = 'block';

                // Header: plate, driver, status, close
                const head = el('div', 'flex items-center gap-2 border-b border-gray-100 px-4 py-2.5');
                head.appendChild(dot(stateColor[d.state] || stateColor.waiting));
                head.appendChild(el('span', 'font-mono text-sm font-semibold text-[#0E2F30]', t.truck));
                head.appendChild(el('span', 'text-sm text-gray-700', t.driver));
                head.appendChild(el('span', 'text-xs text-gray-400', t.reference));
                const close = el('button', 'ml-auto rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700',
                    '\u00d7');
                close.type = 'button';
                close.setAttribute('aria-label', 'Close');
                close.addEventListener('click', showAll);
                head.appendChild(close);
                box.appendChild(head);

                // Facts in a row: status, last GPS point, speed
                const facts = el('div', 'grid grid-cols-3 gap-3 px-4 py-2.5 text-xs');
                [
                    ['Status', d.label],
                    ['Last GPS point', ago(t.last ? t.last.seconds_ago : null)],
                    ['Speed', speed(t) || '\u2014'],
                ].forEach(function(f) {
                    const cell = el('div');
                    cell.appendChild(el('div', 'text-[11px] uppercase tracking-wide text-gray-400', f[0]));
                    cell.appendChild(el('div', 'mt-0.5 font-medium text-gray-800', f[1]));
                    facts.appendChild(cell);
                });
                box.appendChild(facts);

                // Route and message
                const route = el('div', 'border-t border-gray-100 px-4 py-2.5 text-xs');
                route.appendChild(el('div', 'truncate text-gray-700', t.origin + ' \u2192 ' + t.destination));
                route.appendChild(el('div', 'mt-1 text-gray-500', d.message));
                const link = el('a', 'mt-2 inline-block font-medium text-brand-700 hover:underline',
                    'Open trip details \u2192');
                link.href = t.url;
                route.appendChild(link);
                box.appendChild(route);
            }

            function render() {
                renderList();
                renderMap();
                renderInfo();
            }

            document.getElementById('map-all').addEventListener('click', showAll);
            document.getElementById('map-refresh').addEventListener('click', refresh);
            document.getElementById('map-search').addEventListener('input', function(e) {
                search = e.target.value.trim().toLowerCase();
                renderList();
            });

            refresh();
            setInterval(refresh, 15000);
        })();
    </script>
</x-app-layout>
