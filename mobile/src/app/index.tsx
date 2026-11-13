import { router, useFocusEffect, type Href } from "expo-router";
import { useCallback, useState } from "react";
import {
  ActivityIndicator,
  FlatList,
  Pressable,
  RefreshControl,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { SafeAreaView } from "react-native-safe-area-context";
import { ApiError } from "../api";
import { useAuth } from "../auth";
import { StatusBadge } from "../components/StatusBadge";
import { formatDate, type Trip } from "../types";
import { useApi } from "../useApi";

export default function TripsScreen() {
  const { user, signOut } = useAuth();
  const call = useApi();
  const [trips, setTrips] = useState<Trip[] | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [refreshing, setRefreshing] = useState(false);

  async function load() {
    setError(null);
    try {
      const res = await call<{ data: Trip[] }>("/trips");
      setTrips(res.data);
    } catch (e) {
      setError(e instanceof ApiError ? e.message : "Something went wrong.");
    }
  }

  // Load when the screen opens, and again every time we come back to it.
  useFocusEffect(
    useCallback(() => {
      load();
    }, []),
  );

  async function onRefresh() {
    setRefreshing(true);
    await load();
    setRefreshing(false);
  }

  return (
    <SafeAreaView style={styles.screen} edges={["top"]}>
      <View style={styles.header}>
        <View style={{ flex: 1 }}>
          <Text style={styles.hello}>Hello, {user?.name}</Text>
          <Text style={styles.sub}>Your trips</Text>
        </View>
        <Pressable style={styles.logout} onPress={signOut}>
          <Text style={styles.logoutText}>Log out</Text>
        </Pressable>
      </View>

      {error && (
        <Pressable style={styles.errorBox} onPress={load}>
          <Text style={styles.errorText}>{error} Tap to try again.</Text>
        </Pressable>
      )}

      <FlatList
        data={trips ?? []}
        keyExtractor={(trip) => String(trip.id)}
        contentContainerStyle={styles.list}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }
        ListEmptyComponent={
          trips === null ? (
            error ? undefined : (
              <ActivityIndicator size="large" style={{ marginTop: 40 }} />
            )
          ) : (
            <Text style={styles.empty}>
              No trips yet. Pull down to refresh.
            </Text>
          )
        }
        renderItem={({ item }) => (
          <Pressable
            style={styles.card}
            onPress={() => router.push(`/trip/${item.id}` as Href)}
          >
            <View style={styles.cardTop}>
              <Text style={styles.reference}>{item.reference}</Text>
              <StatusBadge status={item.status} />
            </View>
            <Text style={styles.route}>
              {item.origin_address} → {item.destination_address}
            </Text>
            <Text style={styles.when}>
              Departs {formatDate(item.departure_at)}
            </Text>
          </Pressable>
        )}
      />
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: "#f1f5f9" },
  header: {
    flexDirection: "row",
    alignItems: "center",
    padding: 20,
    backgroundColor: "#fff",
  },
  hello: { fontSize: 22, fontWeight: "800", color: "#0f172a" },
  sub: { fontSize: 15, color: "#64748b", marginTop: 2 },
  logout: {
    paddingHorizontal: 16,
    paddingVertical: 10,
    borderRadius: 10,
    backgroundColor: "#e2e8f0",
  },
  logoutText: { fontSize: 16, fontWeight: "700", color: "#334155" },
  list: { padding: 16, gap: 12 },
  card: {
    backgroundColor: "#fff",
    borderRadius: 14,
    padding: 18,
    gap: 8,
  },
  cardTop: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
  },
  reference: { fontSize: 20, fontWeight: "800", color: "#0f172a" },
  route: { fontSize: 17, color: "#1e293b" },
  when: { fontSize: 15, color: "#64748b" },
  empty: { textAlign: "center", fontSize: 17, color: "#64748b", marginTop: 40 },
  errorBox: {
    margin: 16,
    marginBottom: 0,
    padding: 14,
    borderRadius: 10,
    backgroundColor: "#fee2e2",
  },
  errorText: { color: "#991b1b", fontSize: 16 },
});
