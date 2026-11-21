// Pickup (A) and drop-off (B) pins for trips, on the Leaflet map.
import L from "leaflet";

const COLORS = { origin: "#059669", destination: "#dc2626" };

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

// Draws the planned route (pins + dashed line) and returns the points, for fitting the map.
export function drawPlannedRoute(layer, plan) {
    const points = [];
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
    if (plan.origin && plan.destination) {
        // Straight line only: the planned direction, not the exact roads.
        L.polyline([plan.origin, plan.destination], {
            color: "#6b7280",
            weight: 3,
            dashArray: "8 8",
            opacity: 0.9,
        })
            .bindTooltip("Planned route (straight line)")
            .addTo(layer);
    }
    return points;
}

// The map in the trip form: "Find on map" buttons and draggable pins.
export function routePicker(config) {
    return {
        message: "",
        origin: config.origin, // [lat, lng] or null
        destination: config.destination,
        map: null,
        markers: {},

        init() {
            this.map = L.map(this.$refs.map).setView([14.17, 121.24], 9); // Laguna area
            L.tileLayer(config.tileUrl, {
                maxZoom: 19,
                attribution: config.attribution,
            }).addTo(this.map);

            // Clicking the map places the pin that is still missing (pickup first).
            this.map.on("click", (e) => {
                const kind = !this.origin
                    ? "origin"
                    : !this.destination
                      ? "destination"
                      : null;
                if (kind) this.place(kind, [e.latlng.lat, e.latlng.lng]);
            });

            ["origin", "destination"].forEach((kind) => {
                if (this[kind]) this.place(kind, this[kind], false);
            });
            this.fit();
        },

        place(kind, point, fit = true) {
            this[kind] = point;
            if (!this.markers[kind]) {
                this.markers[kind] = L.marker(point, {
                    icon: pinIcon(kind),
                    draggable: true,
                })
                    .bindTooltip(
                        kind === "origin"
                            ? "Pickup (drag to adjust)"
                            : "Drop-off (drag to adjust)",
                    )
                    .addTo(this.map);
                this.markers[kind].on("dragend", (e) => {
                    const p = e.target.getLatLng();
                    this[kind] = [p.lat, p.lng];
                });
            } else {
                this.markers[kind].setLatLng(point);
            }
            if (fit) this.fit();
        },

        fit() {
            const pts = [this.origin, this.destination].filter(Boolean);
            if (pts.length === 1) this.map.setView(pts[0], 15);
            if (pts.length === 2)
                this.map.fitBounds(pts, { padding: [40, 40] });
        },

        async find(kind) {
            const field = document.querySelector(
                '[name="' + kind + '_address"]',
            );
            const q = field ? field.value.trim() : "";
            if (q.length < 3) {
                this.message =
                    "Type the " +
                    (kind === "origin" ? "origin" : "destination") +
                    " address first.";
                return;
            }
            this.message = "Searching...";
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
                    this.message =
                        "Found: " +
                        data.label +
                        ". Drag the pin if it is not the exact spot.";
                } else {
                    this.message = data.approximate
                        ? "Near: " +
                          data.label +
                          ". Only part of the address matched, so drag the pin to the exact spot."
                        : "Found: " +
                          data.label +
                          ". Drag the pin if it is not the exact spot.";
                }
            } catch (e) {
                this.message =
                    data.message ||
                    "Address not found. Click on the map to place the pin.";
            }
        },

        clear(kind) {
            if (this.markers[kind]) {
                this.map.removeLayer(this.markers[kind]);
                delete this.markers[kind];
            }
            this[kind] = null;
        },

        // For the hidden inputs: "" when there is no pin, so the server saves nothing.
        value(kind, i) {
            return this[kind] ? this[kind][i].toFixed(7) : "";
        },
    };
}
