<x-app-layout>
    <div class="py-10">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

            <x-page-header crumb="GPS Tracking" eyebrow="Fleet visibility" title="Live fleet tracking"
                subtitle="See every driver on the road, or follow one trip at a time.">
                <x-slot name="actions">
                    <span
                        class="inline-flex items-center gap-2 rounded-full bg-brand-50 px-3 py-1.5 text-xs font-semibold text-brand-700">
                        <span class="h-2 w-2 rounded-full bg-brand-500"></span> Live locations
                    </span>
                    <button id="map-refresh" type="button"
                        class="rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
                        Refresh location
                    </button>
                </x-slot>
            </x-page-header>

            <div class="mt-8 grid grid-cols-1 gap-6 lg:grid-cols-12">

                <x-card class="overflow-hidden lg:col-span-8">
                    <div style="position: relative; z-index: 0;">
                        <div style="position: absolute; top: 12px; left: 12px; z-index: 1000;"
                            class="rounded-lg bg-white px-3 py-1.5 text-xs text-gray-600 shadow">
                            <span style="color: #10b981;">&#9679;</span> Live GPS &middot; updates every 15 seconds
                        </div>
                        <div id="map-canvas" style="height: 34rem; z-index: 0;"></div>
                    </div>
                    <div id="map-info" class="border-t border-gray-100 p-5 text-sm text-gray-600"></div>
                </x-card>

                <x-card class="overflow-hidden lg:col-span-4">
                    <div class="flex items-center justify-between gap-3 p-6 pb-4">
                        <div>
                            <h2 class="text-lg font-semibold text-[#12263F]">Trucks on the road</h2>
                            <p id="map-count" class="mt-1 text-xs text-gray-500">Loading...</p>
                        </div>
                        <button id="map-all" type="button"
                            class="rounded-lg bg-brand-500 px-3 py-2 text-xs font-semibold text-white">View all</button>
                    </div>
                    <div id="map-list" class="divide-y divide-gray-100 border-t border-gray-100"
                        style="max-height: 34rem; overflow-y: auto;"></div>
                </x-card>
            </div>
        </div>
    </div>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
        (function() {
            const dataUrl = "{{ route('map.data') }}";

            const stateColor = {
                resting: '#2563eb',
                tracking: '#FF8A1F',
                offline: '#dc2626',
                waiting: '#6b7280'
            };
            const pill = {
                resting: 'bg-blue-50 text-blue-700',
                tracking: 'bg-brand-50 text-brand-700',
                offline: 'bg-red-100 text-red-800',
                waiting: 'bg-gray-100 text-gray-600',
            };

            const truckSvg =
                '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="white" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h11v10H3z"/><path d="M14 10h4l3 3v3h-7z"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/></svg>';

            const map = L.map('map-canvas', {
                zoomControl: false
            }).setView([14.17, 121.24], 9);
            L.control.zoom({
                position: 'topright'
            }).addTo(map);
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors',
            }).addTo(map);
            const layer = L.layerGroup().addTo(map);

            let trips = [];
            let selectedId = null; // null = "View all"
            let routeData = null;
            let fitKey = null; // what the map was last zoomed to

            // Small helpers: text is always set with textContent, never as HTML.
            function el(tag, className, text) {
                const node = document.createElement(tag);
                if (className) node.className = className;
                if (text !== undefined) node.textContent = text;
                return node;
            }

            function initials(name) {
                return name.split(/\s+/).filter(Boolean).slice(0, 2).map(function(p) {
                    return p[0].toUpperCase();
                }).join('');
            }

            function truckIcon(color, selected) {
                return L.divIcon({
                    className: '',
                    html: '<div style="width:40px;height:40px;border-radius:12px;background:' + color +
                        ';display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(0,0,0,.35);' +
                        (selected ? 'outline:3px solid #fff;' : '') + '">' + truckSvg + '</div>',
                    iconSize: [40, 40],
                    iconAnchor: [20, 20],
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
                    document.getElementById('map-count').textContent = 'Could not load. Retrying...';
                    return;
                }
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

            function renderList() {
                const list = document.getElementById('map-list');
                list.replaceChildren();

                document.getElementById('map-count').textContent = trips.length ?
                    trips.length + (trips.length === 1 ? ' trip' : ' trips') + ' on the road' :
                    'Nothing on the road right now';

                if (!trips.length) {
                    list.appendChild(el('p', 'p-6 text-sm text-gray-500',
                        'Trips appear here when a driver starts one.'));
                    return;
                }

                trips.forEach(function(t) {
                    const row = el('button', 'block w-full px-6 py-4 text-left hover:bg-gray-50' + (t.id ===
                        selectedId ? ' bg-brand-50' : ''));
                    row.type = 'button';

                    const top = el('div', 'flex items-center gap-3');
                    top.appendChild(el('span',
                        'flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-700',
                        initials(t.driver)));
                    const who = el('div', 'min-w-0');
                    who.appendChild(el('div', 'truncate font-semibold text-[#12263F]', t.truck + ' \u00b7 ' + t
                        .driver));
                    who.appendChild(el('div', 'text-xs text-gray-500', t.reference));
                    top.appendChild(who);
                    row.appendChild(top);

                    row.appendChild(el('div', 'mt-2 truncate text-sm text-gray-600', t.origin + ' \u2192 ' + t
                        .destination));
                    row.appendChild(el('span', 'mt-2 inline-block rounded-md px-2.5 py-1 text-xs ' + (pill[t
                        .state] || pill.waiting), t.label));

                    row.addEventListener('click', function() {
                        select(t.id);
                    });
                    list.appendChild(row);
                });
            }

            function renderMap() {
                layer.clearLayers();
                const bounds = [];

                if (selectedId === null) {
                    trips.forEach(function(t) {
                        if (!t.last) return;
                        const ll = [t.last.lat, t.last.lng];
                        const marker = L.marker(ll, {
                            icon: truckIcon(stateColor[t.state] || stateColor.waiting, false)
                        }).addTo(layer);
                        marker.bindTooltip(el('span', '', t.driver), {
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

                    if (d.path.length > 1) {
                        L.polyline(d.path, {
                            color: '#FF8A1F',
                            weight: 4,
                            opacity: 0.9
                        }).addTo(layer);
                    }
                    d.path.forEach(function(p) {
                        bounds.push(p);
                    });

                    if (d.path.length) {
                        L.circleMarker(d.path[0], {
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
                            icon: truckIcon(stateColor[d.state] || stateColor.waiting, true)
                        }).addTo(layer);
                    }
                }

                const key = selectedId === null ? 'all' : String(selectedId);
                if (key !== fitKey && bounds.length) {
                    map.fitBounds(bounds, {
                        padding: [50, 50],
                        maxZoom: 15
                    });
                    fitKey = key;
                }
            }

            function renderInfo() {
                const box = document.getElementById('map-info');
                box.replaceChildren();

                const allBtn = document.getElementById('map-all');
                allBtn.className = 'rounded-lg px-3 py-2 text-xs font-semibold ' +
                    (selectedId === null ? 'bg-brand-500 text-white' : 'border border-gray-200 bg-white text-gray-700');

                if (selectedId === null) {
                    box.appendChild(el('p', '', trips.length ?
                        'Showing the last known position of every driver on the road. Select a trip to follow its route.' :
                        'No driver is on the road right now.'));
                    return;
                }

                const t = trips.find(function(x) {
                    return x.id === selectedId;
                });
                const d = routeData || t;

                const wrap = el('div', 'flex flex-wrap items-center justify-between gap-4');

                const left = el('div', 'flex items-center gap-3');
                left.appendChild(el('span',
                    'flex h-10 w-10 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-700',
                    initials(t.driver)));
                const who = el('div');
                who.appendChild(el('div', 'font-semibold text-[#12263F]', t.driver));
                who.appendChild(el('div', 'text-xs text-gray-500', d.message));
                left.appendChild(who);
                wrap.appendChild(left);

                wrap.appendChild(el('div', 'text-gray-700', t.origin + ' \u2192 ' + t.destination));
                wrap.appendChild(el('span', 'rounded-md px-2.5 py-1 text-xs ' + (pill[d.state] || pill.waiting), d
                    .label));

                const link = el('a',
                    'rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50',
                    'Trip details');
                link.href = t.url;
                wrap.appendChild(link);

                box.appendChild(wrap);
            }

            function render() {
                renderList();
                renderMap();
                renderInfo();
            }

            document.getElementById('map-all').addEventListener('click', showAll);
            document.getElementById('map-refresh').addEventListener('click', refresh);

            refresh();
            setInterval(refresh, 15000);
        })();
    </script>
</x-app-layout>
