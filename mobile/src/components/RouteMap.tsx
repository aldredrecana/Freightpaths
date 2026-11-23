import { useEffect, useRef, useState } from "react";
import type { StyleProp, ViewStyle } from "react-native";
import { WebView } from "react-native-webview";

// A map page (Leaflet + OpenStreetMap) shown inside the app.
const HTML = `<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css"
  integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
<style>
  html, body, #map { height: 100%; margin: 0; padding: 0; background: #e5e9e7; }
  .leaflet-control-attribution { font-size: 10px; margin-bottom: 4px; }
</style>
</head>
<body>
<div id="map"></div>
<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"
  integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
  var map = L.map('map', { zoomControl: false, attributionControl: true }).setView([14.17, 121.24], 12);
  map.attributionControl.setPrefix(false);
  L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap contributors'
  }).addTo(map);

  var layer = L.layerGroup().addTo(map);
  var fitted = false;
  var lastRecenter = 0;
  var PAD = { paddingTopLeft: [40, 110], paddingBottomRight: [40, 330], maxZoom: 16 };
  var PAD_SMALL = { padding: [36, 36], maxZoom: 16 }; // small map inside a card

  // A round pin with a letter: A = pickup (green), B = drop-off (red).
  function pin(point, letter, color) {
    var el = document.createElement('div');
    el.textContent = letter;
    el.style.cssText = 'width:30px;height:30px;border-radius:15px;background:' + color + ';color:#fff;' +
      'border:3px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.4);font:700 14px sans-serif;' +
      'display:flex;align-items:center;justify-content:center;';
    return L.marker(point, { icon: L.divIcon({ className: '', html: el, iconSize: [30, 30], iconAnchor: [15, 15] }) });
  }

  window.update = function (s) {
    layer.clearLayers();
    var bounds = [];
    var pad = s.compact ? PAD_SMALL : PAD;

        // Planned route: the road route from pickup to drop-off when we have it,
    // otherwise a dashed straight line that only shows the direction.
    if (s.plan) {
      if (s.plan.line && s.plan.line.length > 1) {
        L.polyline(s.plan.line, { color: '#ffffff', weight: 8, opacity: 0.9, lineJoin: 'round' }).addTo(layer);
        L.polyline(s.plan.line, { color: '#1D4ED8', weight: 5, opacity: 0.85, lineJoin: 'round' }).addTo(layer);
                // Dotted joins from each pin to where the road route starts / ends (pins are often off the road).
        var ends = [[s.plan.origin, s.plan.line[0]], [s.plan.line[s.plan.line.length - 1], s.plan.destination]];
        ends.forEach(function (e) {
          if (e[0] && e[1] && L.latLng(e[0]).distanceTo(L.latLng(e[1])) > 15) {
            L.polyline(e, { color: '#1D4ED8', weight: 3, opacity: 0.8, dashArray: '2 7', lineCap: 'round' }).addTo(layer);
          }
        });
      } else if (s.plan.origin && s.plan.destination) {
        L.polyline([s.plan.origin, s.plan.destination], { color: '#6b7280', weight: 3, dashArray: '8 8' }).addTo(layer);
      }
      if (s.plan.origin) { pin(s.plan.origin, 'A', '#059669').addTo(layer); bounds.push(s.plan.origin); }
      if (s.plan.destination) { pin(s.plan.destination, 'B', '#dc2626').addTo(layer); bounds.push(s.plan.destination); }
    }
    if (s.path && s.path.length > 1) {
      L.polyline(s.path, { color: '#0B6E6E', weight: 6, opacity: 0.9 }).addTo(layer);
    }
    if (s.path && s.path.length) {
      L.circleMarker(s.path[0], { radius: 9, color: '#fff', weight: 3, fillColor: '#0B6E6E', fillOpacity: 1 }).addTo(layer);
      s.path.forEach(function (p) { bounds.push(p); });
    }
    if (s.me) {
      var dot = document.createElement('div');
      dot.textContent = s.label || 'D';
      dot.style.cssText = 'width:34px;height:34px;border-radius:17px;background:#0B6E6E;color:#fff;' +
        'border:3px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,.35);font:700 15px sans-serif;' +
        'display:flex;align-items:center;justify-content:center;';
      L.marker(s.me, { icon: L.divIcon({ className: '', html: dot, iconSize: [34, 34], iconAnchor: [17, 17] }) }).addTo(layer);
      bounds.push(s.me);
    }

        if (s.recenter !== lastRecenter && s.me) {
      lastRecenter = s.recenter;
      map.setView(s.me, 16);
      if (!s.compact) map.panBy([0, 110]);
    } else if (!fitted && bounds.length) {
      fitted = true;
      if (bounds.length > 1) map.fitBounds(bounds, pad);
      else { map.setView(bounds[0], 15); if (!s.compact) map.panBy([0, 110]); }
    }
  };
</script>
</body>
</html>`;

type Point = [number, number];

type Props = {
  me: Point | null;
  path: Point[];
  label: string;
  recenter: number;
  style?: StyleProp<ViewStyle>;
  // Optional: pickup (A) and drop-off (B) pins set by the dispatcher.
  // line = the road route from A to B (optional).
  plan?: { origin: Point | null; destination: Point | null; line?: Point[] };
  // Optional: a small map inside a card (less padding, no room for a bottom sheet).
  compact?: boolean;
};

export function RouteMap({
  me,
  path,
  label,
  recenter,
  style,
  plan,
  compact,
}: Props) {
  // (The library's own typing needs the extra <object> to accept props.)
  const ref = useRef<WebView<object>>(null);
  const [ready, setReady] = useState(false);

  // Send the newest position and route into the map page.
  useEffect(() => {
    if (!ready) return;
    const state = JSON.stringify({ me, path, label, recenter, plan, compact });
    ref.current?.injectJavaScript(
      `if (window.update) { window.update(${state}); } true;`,
    );
  }, [ready, me, path, label, recenter, plan, compact]);

  return (
    <WebView<object>
      ref={ref}
      style={style}
      originWhitelist={["*"]}
      source={{ html: HTML }}
      userAgent="FreightPathDriver/1.0 (student capstone project, PUP Calauan)"
      javaScriptEnabled
      domStorageEnabled
      overScrollMode="never"
      onLoadEnd={() => setReady(true)}
    />
  );
}
