// Pickup (A) and drop-off (B) pins for trips, on the Leaflet map,
// and the planned route between them along the roads.
import L from "leaflet";

const COLORS = { origin: "#059669", destination: "#dc2626" };
const ROUTE_COLOR = "#1D4ED8"; // planned route (blue), so it is not mixed up with the driven route (teal)

function meta(name) {
    const tag = document.querySelector('meta[name="' + name + '"]');
    return tag ? tag.content : null;
}

async function getJson(url) {
    const res = await fetch(url, {
        headers: { Accept: "application/json" },
        credentials: "same-origin",
    });
    if (!res.ok) throw new Error("HTTP " + res.status);
    return res.json();
}

// ---- Road route A -> B (asked from our server, which asks OSRM; remembered on this page) ----

const roadCache = new Map();

function routeKey(a, b) {
    return a.join(",") + ";" + b.join(",");
}

async function fetchRoad(a, b) {
    const key = routeKey(a, b);
    if (roadCache.has(key)) return roadCache.get(key);
    const url = meta("route-preview-url");
    if (!url) return null;
    try {
        const q = new URLSearchParams({
            from_lat: a[0],
            from_lng: a[1],
            to_lat: b[0],
            to_lng: b[1],
        });
        const data = await getJson(url + "?" + q);
        roadCache.set(key, data);
        return data;
    } catch (e) {
        return null;
    }
}

// "About 42 km · 1 h 5 min by road"
export function routeSummary(r) {
    if (!r || !r.on_roads || r.km === null) return "";
    const h = Math.floor(r.minutes / 60);
    const m = r.minutes % 60;
    const time = h ? h + " h " + m + " min" : m + " min";
    return "About " + r.km + " km · " + time + " by road";
}

// The road line: a white edge with the blue route on top.
// A pin is often off the road (inside a yard or warehouse). The route starts at the nearest
// road, so a small dotted line joins each pin to where the road route starts or ends.
function roadLine(points, tooltip, from, to) {
    const group = L.layerGroup();
    const first = points[0];
    const last = points[points.length - 1];
    [
        [from, first],
        [last, to],
    ].forEach(([a, b]) => {
        if (a && b && L.latLng(a).distanceTo(L.latLng(b)) > 15) {
            L.polyline([a, b], {
                color: ROUTE_COLOR,
                weight: 3,
                opacity: 0.8,
                dashArray: "2 7",
                lineCap: "round",
            })
                .bindTooltip("From the pin to the nearest road")
                .addTo(group);
        }
    });
    L.polyline(points, {
        color: "#fff",
        weight: 8,
        opacity: 0.9,
        lineJoin: "round",
    }).addTo(group);
    L.polyline(points, {
        color: ROUTE_COLOR,
        weight: 5,
        opacity: 0.85,
        lineJoin: "round",
    })
        .bindTooltip(tooltip)
        .addTo(group);
    return group;
}

// A dashed straight line, shown until (or if) the road route is known.
function straightLine(a, b) {
    return L.polyline([a, b], {
        color: "#6b7280",
        weight: 3,
        dashArray: "8 8",
        opacity: 0.9,
    }).bindTooltip("Planned direction (straight line)");
}

// A round pin with a letter, A = pickup, B = drop-off.
export function pinIcon(kind) {
    const letter = kind === "origin" ? "A" : "B";
    return L.divIcon({
        className: "",
        html:
            '<div style="width:30px;height:30px;border-radius:9999px;background:' +
            COLORS[kind] +
            ";color:#fff;font:700 14px/30px sans-serif;text-align:center;border:3px solid #fff;" +
            'box-shadow:0 1px 4px rgba(0,0,0,.4)">' +
            letter +
            "</div>",
        iconSize: [30, 30],
        iconAnchor: [15, 15],
    });
}

// Draws the planned route (pins + road line) and returns the pin points, for fitting the map.
export function drawPlannedRoute(layer, plan) {
    const points = [];
    if (plan.origin && plan.destination) {
        const cached = roadCache.get(routeKey(plan.origin, plan.destination));
        if (cached && cached.on_roads) {
            roadLine(
                cached.points,
                "Planned route. " + routeSummary(cached),
                plan.origin,
                plan.destination,
            ).addTo(layer);
        } else {
            const straight = straightLine(plan.origin, plan.destination).addTo(
                layer,
            );
            fetchRoad(plan.origin, plan.destination).then((r) => {
                if (r && r.on_roads && layer.hasLayer(straight)) {
                    layer.removeLayer(straight);
                    roadLine(
                        r.points,
                        "Planned route. " + routeSummary(r),
                        plan.origin,
                        plan.destination,
                    ).addTo(layer);
                }
            });
        }
    }
    if (plan.origin) {
        L.marker(plan.origin, { icon: pinIcon("origin") })
            .bindTooltip("Pickup")
            .addTo(layer);
        points.push(plan.origin);
    }
    if (plan.destination) {
        L.marker(plan.destination, { icon: pinIcon("destination") })
            .bindTooltip("Drop-off")
            .addTo(layer);
        points.push(plan.destination);
    }
    return points;
}

// The map in the trip form.
// - Typing an address (then leaving the box) places its pin automatically.
// - Clicking the map or dragging a pin fills an empty address box automatically.
// - Each pin can be removed; the planned road route is shown when both pins are set.
export function routePicker(config) {
    // The map, pins and line live here, NOT in Alpine's data: Alpine would wrap them in
    // reactive proxies, which breaks Leaflet's zoom animation (pins vanish or lag).
    let map = null;
    const markers = {};
    let line = null;
    let routeFor = ""; // which pin positions the line on the map belongs to

    const plain = (p) => (p ? [Number(p[0]), Number(p[1])] : null);
    const keyOf = (a, b) => (a && b ? a.join(",") + ";" + b.join(",") : "");

    return {
        message: "",
        summary: "",
        origin: plain(config.origin), // [lat, lng] or null
        destination: plain(config.destination),
        // Remember what we filled in ourselves, so we never overwrite what the user typed.
        autoAddress: { origin: null, destination: null },
        // True when the pin came from the address text (so a new address can move it).
        pinFromAddress: { origin: false, destination: false },

        init() {
            map = L.map(this.$refs.map).setView([14.17, 121.24], 9); // Laguna area
            L.tileLayer(config.tileUrl, {
                maxZoom: 19,
                attribution: config.attribution,
            }).addTo(map);

            // Clicking the map places the pin that is still missing (pickup first).
            map.on("click", (e) => {
                const kind = !this.origin
                    ? "origin"
                    : !this.destination
                      ? "destination"
                      : null;
                if (!kind) {
                    this.message =
                        "Both pins are placed. Drag a pin to move it, or remove one first.";
                    return;
                }
                this.place(kind, [e.latlng.lat, e.latlng.lng]);
                this.pinFromAddress[kind] = false;
                this.fillAddress(kind);
            });

            // Leaving an address box places (or moves) its pin.
            ["origin", "destination"].forEach((kind) => {
                const field = this.field(kind);
                if (!field) return;
                field.addEventListener("change", () => {
                    const text = field.value.trim();
                    if (text.length < 3 || text === this.autoAddress[kind])
                        return;
                    if (!this[kind] || this.pinFromAddress[kind])
                        this.find(kind);
                });
            });

            ["origin", "destination"].forEach((kind) => {
                if (this[kind]) this.place(kind, this[kind], false);
            });
            this.pinFromAddress = {
                origin: !!this.origin,
                destination: !!this.destination,
            };
            this.fit();
            this.updateRoute();
        },

        field(kind) {
            return document.querySelector('[name="' + kind + '_address"]');
        },

        label(kind) {
            return kind === "origin" ? "pickup" : "drop-off";
        },

        place(kind, point, fit = true) {
            const p = plain(point);
            this[kind] = p;
            if (!markers[kind]) {
                markers[kind] = L.marker(p, {
                    icon: pinIcon(kind),
                    draggable: true,
                })
                    .bindTooltip(
                        kind === "origin"
                            ? "Pickup (drag to adjust)"
                            : "Drop-off (drag to adjust)",
                    )
                    .addTo(map);
                markers[kind].on("dragend", (e) => {
                    const ll = e.target.getLatLng();
                    this[kind] = [ll.lat, ll.lng];
                    this.pinFromAddress[kind] = false;
                    this.fillAddress(kind);
                    this.updateRoute();
                });
            } else {
                markers[kind].setLatLng(p);
            }
            if (fit) this.fit();
            this.updateRoute();
        },

        fit() {
            const pts = [plain(this.origin), plain(this.destination)].filter(
                Boolean,
            );
            if (pts.length === 1) map.setView(pts[0], 15);
            if (pts.length === 2) map.fitBounds(pts, { padding: [40, 40] });
        },

        // The road route between the two pins.
        async updateRoute() {
            const a = plain(this.origin);
            const b = plain(this.destination);
            const key = keyOf(a, b);
            if (key === routeFor && line) return; // already drawn for these pins
            routeFor = key;

            if (line) {
                map.removeLayer(line);
                line = null;
            }
            this.summary = "";
            if (!a || !b) return;

            line = straightLine(a, b).addTo(map);

            const r = await fetchRoad(a, b);
            if (routeFor !== key) return; // pins moved meanwhile
            if (r && r.on_roads) {
                map.removeLayer(line);
                line = roadLine(r.points, "Planned route", a, b).addTo(map);
                this.summary = routeSummary(r);
            }
        },

        // Pin -> address, only when the box is empty or still holds an address we filled in.
        async fillAddress(kind) {
            const field = this.field(kind);
            const url = meta("reverse-geocode-url");
            if (!field || !url) return;
            const current = field.value.trim();
            if (current !== "" && current !== this.autoAddress[kind]) return; // keep what the user typed

            const point = plain(this[kind]);
            const asked = point.join(",");
            this.message = "Looking up the " + this.label(kind) + " address...";
            try {
                const data = await getJson(
                    url +
                        "?" +
                        new URLSearchParams({ lat: point[0], lng: point[1] }),
                );
                if (!this[kind] || plain(this[kind]).join(",") !== asked)
                    return; // pin moved meanwhile
                if (data.found) {
                    field.value = data.label;
                    this.autoAddress[kind] = data.label;
                    this.message =
                        "The " +
                        this.label(kind) +
                        " address was filled in from the map. Please check it.";
                } else {
                    this.message =
                        "No address found here. Please type the " +
                        this.label(kind) +
                        " address.";
                }
            } catch (e) {
                this.message =
                    "Could not look up the address. Please type the " +
                    this.label(kind) +
                    " address.";
            }
        },

        // Address -> pin ("Find" buttons, and automatically when an address box is left).
        async find(kind) {
            const field = this.field(kind);
            const q = field ? field.value.trim() : "";
            if (q.length < 3) {
                this.message =
                    "Type the " +
                    (kind === "origin" ? "origin" : "destination") +
                    " address first.";
                return;
            }
            this.message =
                "Searching for the " + this.label(kind) + " address...";
            try {
                const res = await fetch(
                    config.geocodeUrl + "?q=" + encodeURIComponent(q),
                    {
                        headers: { Accept: "application/json" },
                        credentials: "same-origin",
                    },
                );
                const data = await res.json();
                if (data.found) {
                    this.place(kind, [data.lat, data.lng]);
                    this.pinFromAddress[kind] = true;
                    const pin = kind === "origin" ? "A" : "B";
                    this.message = data.approximate
                        ? "Pin " +
                          pin +
                          " placed near: " +
                          data.label +
                          ". Only part of the address matched, so drag the pin to the exact spot."
                        : "Pin " +
                          pin +
                          " placed at: " +
                          data.label +
                          ". Drag the pin if it is not the exact spot.";
                } else {
                    this.message =
                        data.message ||
                        "Address not found. Try a shorter address (for example 'Rizal Park, Manila'), or click on the map to place the pin.";
                }
            } catch (e) {
                this.message =
                    "Search failed. Click on the map to place the pin.";
            }
        },

        // Removes a wrong pin. An address we filled in from that pin is removed too.
        clear(kind) {
            if (markers[kind]) {
                map.removeLayer(markers[kind]);
                delete markers[kind];
            }
            this[kind] = null;
            this.pinFromAddress[kind] = false;

            const field = this.field(kind);
            if (
                field &&
                this.autoAddress[kind] &&
                field.value.trim() === this.autoAddress[kind]
            ) {
                field.value = "";
            }
            this.autoAddress[kind] = null;
            this.message =
                "The " +
                this.label(kind) +
                " pin was removed. Click on the map to place it again.";
            this.updateRoute();
        },

        // For the hidden inputs: "" when there is no pin, so the server saves nothing.
        value(kind, i) {
            return this[kind] ? Number(this[kind][i]).toFixed(7) : "";
        },
    };
}
