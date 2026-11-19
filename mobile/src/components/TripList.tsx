import { Ionicons } from "@expo/vector-icons";
import { router, useFocusEffect } from "expo-router";
import { useCallback } from "react";
import {
  ActivityIndicator,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import { colors } from "../theme";
import { useTrips } from "../trips";
import { formatDate, type Trip } from "../types";
import { StatusBadge } from "./StatusBadge";
import { Banner, Card } from "./ui";

function TripRow({ trip }: { trip: Trip }) {
  return (
    <Pressable onPress={() => router.push(`/trip/${trip.id}`)}>
      <Card style={styles.row}>
        <View style={{ flex: 1, gap: 6 }}>
          <View style={styles.rowTop}>
            <Text style={styles.rowRef}>{trip.reference}</Text>
            <StatusBadge status={trip.status} />
          </View>
          <Text style={styles.rowRoute} numberOfLines={1}>
            {trip.origin_address} → {trip.destination_address}
          </Text>
          <Text style={styles.rowWhen}>{formatDate(trip.departure_at)}</Text>
        </View>
        <Ionicons name="chevron-forward" size={22} color={colors.muted} />
      </Card>
    </Pressable>
  );
}

// A titled list of trips, used by the Schedule and History tabs.
export function TripList({
  title,
  list,
  emptyTitle,
  emptyText,
  emptyIcon,
}: {
  title: string;
  list: Trip[];
  emptyTitle: string;
  emptyText: string;
  emptyIcon: keyof typeof Ionicons.glyphMap;
}) {
  const insets = useSafeAreaInsets();
  const { trips, error, warning, refreshing, refresh, pull } = useTrips();

  useFocusEffect(
    useCallback(() => {
      refresh();
    }, []),
  );

  return (
    <View style={styles.root}>
      <Text style={[styles.title, { paddingTop: insets.top + 18 }]}>
        {title}
      </Text>

      <ScrollView
        contentContainerStyle={styles.body}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={pull} />
        }
      >
        {error && (
          <Pressable onPress={refresh}>
            <Banner kind="error" text={`${error} Tap to try again.`} />
          </Pressable>
        )}
        {warning && <Banner kind="warn" text={warning} />}

        {trips === null && !error && (
          <ActivityIndicator size="large" style={{ marginTop: 40 }} />
        )}

        {trips !== null && list.length === 0 && (
          <View style={styles.empty}>
            <Ionicons name={emptyIcon} size={56} color={colors.muted} />
            <Text style={styles.emptyTitle}>{emptyTitle}</Text>
            <Text style={styles.emptyText}>{emptyText}</Text>
          </View>
        )}

        {list.map((t) => (
          <TripRow key={t.id} trip={t} />
        ))}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.bg },
  title: {
    paddingHorizontal: 20,
    paddingBottom: 6,
    fontSize: 30,
    fontWeight: "800",
    color: colors.ink,
  },
  body: { padding: 18, gap: 14, paddingBottom: 40 },
  row: { flexDirection: "row", alignItems: "center", gap: 10 },
  rowTop: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
  },
  rowRef: { fontSize: 18, fontWeight: "800", color: colors.ink },
  rowRoute: { fontSize: 16, color: colors.ink },
  rowWhen: { fontSize: 15, color: colors.muted },
  empty: { alignItems: "center", gap: 6, paddingVertical: 60 },
  emptyTitle: { fontSize: 20, fontWeight: "800", color: colors.ink },
  emptyText: {
    fontSize: 16,
    color: colors.muted,
    textAlign: "center",
    paddingHorizontal: 30,
  },
});
