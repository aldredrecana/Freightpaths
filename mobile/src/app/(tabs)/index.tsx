import { Ionicons } from "@expo/vector-icons";
import * as Location from "expo-location";
import { router, useFocusEffect } from "expo-router";
import { useCallback, useEffect, useState } from "react";
import { Pressable, StyleSheet, Text, View } from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import { useAuth } from "../../auth";
import { RouteMap } from "../../components/RouteMap";
import { StatusBadge } from "../../components/StatusBadge";
import { Banner, RouteTimeline } from "../../components/ui";
import { colors, shadow } from "../../theme";
import { useTrips } from "../../trips";
import { dayWord, formatDay, formatTime } from "../../types";
import { useApi } from "../../useApi";

export default function Home() {
  const { user } = useAuth();
  const call = useApi();
  const insets = useSafeAreaInsets();
  const { current, online, warning, refresh } = useTrips();
  const [me, setMe] = useState<[number, number] | null>(null);
  const [path, setPath] = useState<[number, number][]>([]);
  const [recenter, setRecenter] = useState(0);
  const [note, setNote] = useState<string | null>(null);

  // Keep the trips fresh while this tab is showing.
  useFocusEffect(
    useCallback(() => {
      refresh();
      const timer = setInterval(refresh, 15000);
      return () => clearInterval(timer);
    }, []),
  );

  // Show the phone's own position on the map while this tab is showing.
  useFocusEffect(
    useCallback(() => {
      let sub: Location.LocationSubscription | null = null;
      let cancelled = false;

      (async () => {
        const permission = await Location.getForegroundPermissionsAsync();
        if (permission.status !== "granted") return;

        const watcher = await Location.watchPositionAsync(
          {
            accuracy: Location.Accuracy.Balanced,
            timeInterval: 5000,
            distanceInterval: 5,
          },
          (loc) => setMe([loc.coords.latitude, loc.coords.longitude]),
        );
        if (cancelled) watcher.remove();
        else sub = watcher;
      })();

      return () => {
        cancelled = true;
        sub?.remove();
      };
    }, []),
  );

  // The route driven so far on the trip that is on the way.
  const onTheRoad = current?.status === "en_route" ? current.id : null;
  useEffect(() => {
    if (onTheRoad === null) {
      setPath([]);
      return;
    }

    let alive = true;
    async function loadTrack() {
      try {
        const res = await call<{ path: [number, number][] }>(
          `/trips/${onTheRoad}/track`,
        );
        if (alive) setPath(res.path);
      } catch {
        // The map keeps what it had; the next refresh tries again.
      }
    }

    loadTrack();
    const timer = setInterval(loadTrack, 15000);
    return () => {
      alive = false;
      clearInterval(timer);
    };
  }, [onTheRoad]);

  // The round button: ask for location if needed, then center the map on the phone.
  async function locate() {
    setNote(null);
    const permission = await Location.requestForegroundPermissionsAsync();
    if (permission.status !== "granted") {
      setNote("Turn on location to see yourself on the map.");
      return;
    }

    const loc =
      (await Location.getLastKnownPositionAsync({ maxAge: 300_000 })) ??
      (await Location.getCurrentPositionAsync({
        accuracy: Location.Accuracy.Balanced,
      }));
    setMe([loc.coords.latitude, loc.coords.longitude]);
    setRecenter((n) => n + 1);
  }

  const initial = (user?.name ?? "D").trim().charAt(0).toUpperCase() || "D";

  return (
    <View style={styles.root}>
      <RouteMap
        me={me}
        path={path}
        label={initial}
        recenter={recenter}
        style={StyleSheet.absoluteFill}
      />

      <View
        style={[styles.top, { paddingTop: insets.top + 10 }]}
        pointerEvents="box-none"
      >
        <View style={styles.topRow} pointerEvents="box-none">
          <View style={styles.brandChip}>
            <View style={styles.brandLogo}>
              <Text style={styles.brandLogoText}>F</Text>
            </View>
            <Text style={styles.brandName}>FreightPath</Text>
          </View>

          <View style={styles.onlinePill}>
            <View
              style={[
                styles.onlineDot,
                { backgroundColor: online ? colors.brand : colors.muted },
              ]}
            />
            <Text style={styles.onlineText}>
              {online ? "Online" : "Offline"}
            </Text>
          </View>
        </View>

        {(warning || note) && (
          <Banner kind="warn" text={(warning ?? note) as string} />
        )}
      </View>

      <View style={styles.bottom} pointerEvents="box-none">
        <Pressable style={styles.locate} onPress={locate}>
          <Ionicons name="locate" size={24} color={colors.brand} />
        </Pressable>

        {current ? (
          <Pressable
            style={styles.sheet}
            onPress={() => router.push(`/trip/${current.id}`)}
          >
            <View style={styles.handle} />

            <View style={styles.sheetTop}>
              <View style={{ flex: 1 }}>
                <Text style={styles.kicker}>
                  {current.status === "en_route" ? "CURRENT TRIP" : "NEXT TRIP"}{" "}
                  · {formatTime(current.departure_at)}
                </Text>
                <Text style={styles.sheetTitle}>
                  {dayWord(current.departure_at)} delivery
                </Text>
              </View>
              <StatusBadge status={current.status} />
            </View>

            <RouteTimeline
              origin={current.origin_address}
              destination={current.destination_address}
            />

            <View style={styles.divider} />

            <View style={styles.dateRow}>
              <Ionicons
                name="calendar-outline"
                size={18}
                color={colors.muted}
              />
              <Text style={styles.dateText}>
                {formatDay(current.departure_at)} · Arrives{" "}
                {formatTime(current.expected_arrival_at)}
              </Text>
            </View>
          </Pressable>
        ) : (
          <View style={styles.sheet}>
            <View style={styles.handle} />
            <Text style={styles.sheetTitle}>No trips right now</Text>
            <Text style={styles.dateText}>
              New trips from your dispatcher appear here. Check the Schedule tab
              for the full list.
            </Text>
          </View>
        )}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.bg },
  top: {
    position: "absolute",
    top: 0,
    left: 0,
    right: 0,
    paddingHorizontal: 16,
    gap: 10,
  },
  topRow: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
  },
  brandChip: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    paddingVertical: 8,
    paddingLeft: 8,
    paddingRight: 16,
    borderRadius: 16,
    backgroundColor: "#fff",
    ...shadow,
  },
  brandLogo: {
    width: 30,
    height: 30,
    borderRadius: 9,
    backgroundColor: colors.brand,
    alignItems: "center",
    justifyContent: "center",
  },
  brandLogoText: { color: "#fff", fontSize: 17, fontWeight: "800" },
  brandName: { fontSize: 17, fontWeight: "800", color: colors.ink },
  onlinePill: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    paddingVertical: 12,
    paddingHorizontal: 16,
    borderRadius: 16,
    backgroundColor: "#fff",
    ...shadow,
  },
  onlineDot: { width: 10, height: 10, borderRadius: 5 },
  onlineText: { fontSize: 16, fontWeight: "700", color: colors.ink },
  bottom: {
    position: "absolute",
    left: 0,
    right: 0,
    bottom: 0,
    padding: 14,
    gap: 12,
  },
  locate: {
    alignSelf: "flex-end",
    width: 52,
    height: 52,
    borderRadius: 16,
    backgroundColor: "#fff",
    alignItems: "center",
    justifyContent: "center",
    ...shadow,
  },
  sheet: {
    backgroundColor: "#fff",
    borderRadius: 26,
    padding: 20,
    paddingTop: 10,
    gap: 14,
    ...shadow,
  },
  handle: {
    alignSelf: "center",
    width: 44,
    height: 5,
    borderRadius: 3,
    backgroundColor: colors.line,
  },
  sheetTop: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    gap: 10,
  },
  kicker: {
    fontSize: 13,
    fontWeight: "700",
    color: colors.muted,
    letterSpacing: 0.8,
  },
  sheetTitle: {
    marginTop: 2,
    fontSize: 26,
    fontWeight: "800",
    color: colors.ink,
  },
  divider: { height: 1, backgroundColor: colors.line },
  dateRow: { flexDirection: "row", alignItems: "center", gap: 8 },
  dateText: { fontSize: 15, color: colors.muted },
});
