import * as Location from "expo-location";
import { useEffect, useMemo, useState } from "react";
import { StyleSheet, Text, View } from "react-native";
import { colors } from "../theme";
import type { Trip } from "../types";
import { useApi } from "../useApi";
import { Card } from "./ui";
import { RouteMap } from "./RouteMap";

type Point = [number, number];
type RoadRoute = {
  points: Point[];
  km: number | null;
  minutes: number | null;
  on_roads: boolean;
};

// "About 42 km · 1 h 5 min by road"
function summary(r: RoadRoute | null): string | null {
  if (!r || !r.on_roads || r.km === null || r.minutes === null) return null;
  const h = Math.floor(r.minutes / 60);
  const m = r.minutes % 60;
  return `About ${r.km} km · ${h ? `${h} h ${m} min` : `${m} min`} by road`;
}

// Route visualization for one trip: pickup (A), drop-off (B),
// the road route between them, and the driver's position ("Me").
export function TripRouteCard({ trip }: { trip: Trip }) {
  const call = useApi();
  const [me, setMe] = useState<Point | null>(null);
  const [road, setRoad] = useState<RoadRoute | null>(null);

  // The driver's position, only if location is already allowed (we never ask here).
  useEffect(() => {
    let alive = true;
    (async () => {
      const perm = await Location.getForegroundPermissionsAsync();
      if (!perm.granted) return;
      const pos =
        (await Location.getLastKnownPositionAsync()) ??
        (await Location.getCurrentPositionAsync({
          accuracy: Location.Accuracy.Balanced,
        }));
      if (alive && pos) setMe([pos.coords.latitude, pos.coords.longitude]);
    })().catch(() => {});
    return () => {
      alive = false;
    };
  }, []);

  // The road route from A to B (the server asks OSRM, the free OpenStreetMap routing service).
  const hasBoth = Boolean(trip.origin_point && trip.destination_point);
  useEffect(() => {
    if (!hasBoth) return;
    let alive = true;
    call<RoadRoute>(`/trips/${trip.id}/route`)
      .then((r) => alive && setRoad(r))
      .catch(() => {}); // no route: the dashed straight line is shown instead
    return () => {
      alive = false;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [trip.id, hasBoth, trip.origin_point?.[0], trip.destination_point?.[0]]);

  const plan = useMemo(
    () => ({
      origin: trip.origin_point ?? null,
      destination: trip.destination_point ?? null,
      line: road?.on_roads ? road.points : undefined,
    }),
    [trip.origin_point, trip.destination_point, road],
  );
  const about = summary(road);

  const hasPins = Boolean(plan.origin || plan.destination);

  // No pins from the dispatcher: nothing to draw, so the card is not shown.
  if (!hasPins) return null;

  return (
    <Card>
      <Text style={styles.heading}>Route</Text>
      <Text style={styles.sub}>
        {road?.on_roads
          ? "A = pickup, B = drop-off. The blue line is the suggested road route."
          : "A = pickup, B = drop-off. The dashed line shows the direction."}
      </Text>
      {about && <Text style={styles.about}>{about}</Text>}

      <View style={styles.mapBox}>
        <RouteMap
          style={styles.map}
          me={me}
          path={[]}
          label="Me"
          recenter={0}
          plan={plan}
          compact
        />
      </View>
    </Card>
  );
}

const styles = StyleSheet.create({
  heading: { fontSize: 18, fontWeight: "800", color: colors.text },
  sub: { fontSize: 14, color: colors.muted, marginTop: 2 },
  about: { fontSize: 15, fontWeight: "700", color: "#1D4ED8", marginTop: 6 },
  mapBox: { marginTop: 12, height: 260, borderRadius: 14, overflow: "hidden" },
  map: { flex: 1 },
});
