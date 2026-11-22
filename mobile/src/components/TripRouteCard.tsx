import * as Location from "expo-location";
import { useEffect, useMemo, useState } from "react";
import { StyleSheet, Text, View } from "react-native";
import { colors } from "../theme";
import type { Trip } from "../types";
import { Card } from "./ui";
import { RouteMap } from "./RouteMap";

type Point = [number, number];

// Route visualization for one trip: pickup (A), drop-off (B),
// the planned line between them, and the driver's position ("Me").
export function TripRouteCard({ trip }: { trip: Trip }) {
  const [me, setMe] = useState<Point | null>(null);

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

  const plan = useMemo(
    () => ({
      origin: trip.origin_point ?? null,
      destination: trip.destination_point ?? null,
    }),
    [trip.origin_point, trip.destination_point],
  );

  const hasPins = Boolean(plan.origin || plan.destination);

  // No pins from the dispatcher: nothing to draw, so the card is not shown.
  if (!hasPins) return null;

  return (
    <Card>
      <Text style={styles.heading}>Route</Text>
      <Text style={styles.sub}>
        A = pickup, B = drop-off. The dashed line shows the direction, not the
        roads.
      </Text>

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
  mapBox: { marginTop: 12, height: 260, borderRadius: 14, overflow: "hidden" },
  map: { flex: 1 },
});
