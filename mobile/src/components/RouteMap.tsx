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

  window.update = function (s) {
    layer.clearLayers();
    var bounds = [];

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
      map.panBy([0, 110]);
    } else if (!fitted && bounds.length) {
      fitted = true;
      if (bounds.length > 1) map.fitBounds(bounds, PAD);
      else { map.setView(bounds[0], 16); map.panBy([0, 110]); }
    }
  };
</script>
</body>
</html>`;

type Props = {
  me: [number, number] | null;
  path: [number, number][];
  label: string;
  recenter: number;
  style?: StyleProp<ViewStyle>;
};

export function RouteMap({ me, path, label, recenter, style }: Props) {
  // (The library's own typing needs the extra <object> to accept props.)
  const ref = useRef<WebView<object>>(null);
  const [ready, setReady] = useState(false);

  // Send the newest position and route into the map page.
  useEffect(() => {
    if (!ready) return;
    const state = JSON.stringify({ me, path, label, recenter });
    ref.current?.injectJavaScript(
      `if (window.update) { window.update(${state}); } true;`,
    );
  }, [ready, me, path, label, recenter]);

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
