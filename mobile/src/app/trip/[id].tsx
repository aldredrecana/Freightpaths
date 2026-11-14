import { Stack, useFocusEffect, useLocalSearchParams } from "expo-router";
import { useCallback, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { ApiError } from "../../api";
import { StatusBadge } from "../../components/StatusBadge";
import { startTracking, stopTracking } from "../../tracking";
import { useTrackingInfo } from "../../useTrackingInfo";
import {
  ACTION_LABEL,
  formatDate,
  TIMELINE_LABEL,
  type Trip,
} from "../../types";
import { useApi } from "../../useApi";

export default function TripDetail() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const call = useApi();
  const tracking = useTrackingInfo();
  const [trip, setTrip] = useState<Trip | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function load() {
    try {
      const res = await call<{ data: Trip }>(`/trips/${id}`);
      setTrip(res.data);
    } catch (e) {
      setError(e instanceof ApiError ? e.message : "Something went wrong.");
    }
  }

  useFocusEffect(
    useCallback(() => {
      load();
    }, [id]),
  );

  async function changeStatus(status: string) {
    setBusy(true);
    setError(null);
    setMessage(null);
    try {
      const res = await call<{ message: string }>(`/trips/${id}/status`, {
        method: "POST",
        body: { status },
      });
      setMessage(res.message);

      // Share location only while the trip is on the way.
      if (status === "en_route") {
        const started = await startTracking(Number(id));
        if (!started.ok) setError(started.message ?? null);
      } else if (status === "delivered") {
        await stopTracking();
      }

      await load(); // reload so the timeline shows the new entry
    } catch (e) {
      setError(e instanceof ApiError ? e.message : "Something went wrong.");
    } finally {
      setBusy(false);
    }
  }

  function onAction(next: string) {
    if (next === "delivered") {
      Alert.alert(
        "Mark as delivered?",
        "Only do this after the goods are handed over.",
        [
          { text: "Not yet", style: "cancel" },
          { text: "Yes, delivered", onPress: () => changeStatus(next) },
        ],
      );
    } else {
      changeStatus(next);
    }
  }

  if (!trip) {
    return (
      <View style={styles.center}>
        <Stack.Screen options={{ title: "Trip" }} />
        {error ? (
          <Text style={styles.errorText}>{error}</Text>
        ) : (
          <ActivityIndicator size="large" />
        )}
      </View>
    );
  }

  const next = trip.allowed_next[0];

  return (
    <ScrollView contentContainerStyle={styles.content} style={styles.screen}>
      <Stack.Screen options={{ title: trip.reference }} />

      <StatusBadge status={trip.status} />

      {message && (
        <View style={styles.okBox}>
          <Text style={styles.okText}>{message}</Text>
        </View>
      )}
      {error && (
        <View style={styles.errorBox}>
          <Text style={styles.errorText}>{error}</Text>
        </View>
      )}

      <View style={styles.card}>
        <Text style={styles.label}>From</Text>
        <Text style={styles.value}>{trip.origin_address}</Text>
        <Text style={styles.label}>To</Text>
        <Text style={styles.value}>{trip.destination_address}</Text>
        <Text style={styles.label}>Departure</Text>
        <Text style={styles.value}>{formatDate(trip.departure_at)}</Text>
        <Text style={styles.label}>Expected arrival</Text>
        <Text style={styles.value}>{formatDate(trip.expected_arrival_at)}</Text>
        {trip.truck && (
          <>
            <Text style={styles.label}>Truck</Text>
            <Text style={styles.value}>
              {trip.truck.plate_no} · {trip.truck.model}
            </Text>
          </>
        )}
      </View>

      {trip.status === "en_route" && (
        <View style={styles.card}>
          {tracking.activeId === trip.id ? (
            <>
              <Text style={styles.heading}>Sharing your location</Text>
              <Text style={styles.logWhen}>
                {tracking.lastSent
                  ? `Last sent ${tracking.lastSent.toLocaleTimeString("en-PH", { hour: "numeric", minute: "2-digit" })}`
                  : "Waiting for the first GPS point..."}
                {tracking.queued > 0
                  ? ` · ${tracking.queued} waiting to send`
                  : ""}
              </Text>
            </>
          ) : (
            <>
              <Text style={styles.heading}>Location sharing is off</Text>
              <Pressable
                style={styles.secondary}
                onPress={async () => {
                  const started = await startTracking(trip.id);
                  if (!started.ok) setError(started.message ?? null);
                }}
              >
                <Text style={styles.secondaryText}>Turn on</Text>
              </Pressable>
            </>
          )}
        </View>
      )}

      {next && (
        <Pressable
          style={[styles.action, busy && styles.disabled]}
          disabled={busy}
          onPress={() => onAction(next)}
        >
          {busy ? (
            <ActivityIndicator color="#fff" />
          ) : (
            <Text style={styles.actionText}>{ACTION_LABEL[next] ?? next}</Text>
          )}
        </Pressable>
      )}

      <View style={styles.card}>
        <Text style={styles.heading}>Timeline</Text>
        {(trip.history ?? []).map((log, i) => (
          <View key={i} style={styles.logRow}>
            <Text style={styles.logTitle}>
              {TIMELINE_LABEL[log.status] ?? log.status}
            </Text>
            <Text style={styles.logWhen}>{formatDate(log.logged_at)}</Text>
            {log.remarks ? (
              <Text style={styles.logNote}>{log.remarks}</Text>
            ) : null}
          </View>
        ))}
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: "#f1f5f9" },
  content: { padding: 16, gap: 14 },
  center: {
    flex: 1,
    alignItems: "center",
    justifyContent: "center",
    padding: 24,
  },
  card: { backgroundColor: "#fff", borderRadius: 14, padding: 18 },
  label: { fontSize: 14, color: "#64748b", marginTop: 10 },
  value: { fontSize: 19, color: "#0f172a", fontWeight: "600" },
  heading: {
    fontSize: 18,
    fontWeight: "800",
    color: "#0f172a",
    marginBottom: 4,
  },
  action: {
    height: 66,
    borderRadius: 14,
    backgroundColor: "#1d4ed8",
    alignItems: "center",
    justifyContent: "center",
  },
  disabled: { opacity: 0.6 },
  actionText: { color: "#fff", fontSize: 22, fontWeight: "800" },
  secondary: {
    marginTop: 10,
    height: 52,
    borderRadius: 12,
    backgroundColor: "#e2e8f0",
    alignItems: "center",
    justifyContent: "center",
  },
  secondaryText: { fontSize: 18, fontWeight: "700", color: "#334155" },
  okBox: { padding: 14, borderRadius: 10, backgroundColor: "#dcfce7" },
  okText: { color: "#166534", fontSize: 17, fontWeight: "600" },
  errorBox: { padding: 14, borderRadius: 10, backgroundColor: "#fee2e2" },
  errorText: { color: "#991b1b", fontSize: 17 },
  logRow: { marginTop: 12 },
  logTitle: { fontSize: 17, fontWeight: "700", color: "#0f172a" },
  logWhen: { fontSize: 14, color: "#64748b" },
  logNote: { fontSize: 15, color: "#334155", marginTop: 2 },
});
