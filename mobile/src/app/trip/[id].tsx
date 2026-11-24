import { Ionicons } from "@expo/vector-icons";
import {
  router,
  Stack,
  useFocusEffect,
  useLocalSearchParams,
} from "expo-router";
import { useCallback, useState } from "react";
import {
  ActivityIndicator,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import { ApiError } from "../../api";
import { confirmDialog } from "../../components/Dialog";
import { StatusBadge } from "../../components/StatusBadge";
import { ProblemReportCard } from "../../components/ProblemReportCard";
import { TripRouteCard } from "../../components/TripRouteCard";
import {
  Banner,
  Button,
  Card,
  InfoTile,
  RouteTimeline,
} from "../../components/ui";
import { colors, shadow } from "../../theme";
import { startTracking, stopTracking } from "../../tracking";
import {
  ACTION_LABEL,
  formatDate,
  formatTime,
  TIMELINE_LABEL,
  type Trip,
} from "../../types";
import { useApi } from "../../useApi";
import { useTrackingInfo } from "../../useTrackingInfo";

// Same reasons as the website.
const DECLINE_REASONS = [
  { value: "sick", label: "Sick" },
  { value: "emergency", label: "Emergency" },
  { value: "vehicle_problem", label: "Vehicle problem" },
  { value: "other", label: "Other" },
];

const ACTION_ICON: Record<string, keyof typeof Ionicons.glyphMap> = {
  accepted: "checkmark-circle",
  en_route: "navigate",
  delivered: "flag",
};

export default function TripDetail() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const call = useApi();
  const tracking = useTrackingInfo();
  const insets = useSafeAreaInsets();
  const [trip, setTrip] = useState<Trip | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [declining, setDeclining] = useState(false);
  const [reason, setReason] = useState<string | null>(null);
  const [details, setDetails] = useState("");

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

  async function onAction(next: string) {
    if (next === "delivered") {
      // Delivered needs the proof first: photo, receiver's name and signature.
      router.push(`/deliver/${id}`);
      return;
    }
    if (next === "en_route") {
      const yes = await confirmDialog({
        icon: "navigate",
        title: "Start this trip?",
        message:
          "Your location will be shared with your dispatcher until you mark the trip Delivered.",
        confirmText: "Start trip",
        cancelText: "Not yet",
      });
      if (!yes) return;
    }
    changeStatus(next);
  }

  async function confirmDecline() {
    if (!reason) {
      setError("Please choose a reason.");
      return;
    }
    if (reason === "other" && !details.trim()) {
      setError('Please write the details for "Other".');
      return;
    }

    const yes = await confirmDialog({
      icon: "close-circle",
      tone: "danger",
      title: "Decline this trip?",
      message: "The dispatcher will assign another driver.",
      confirmText: "Yes, decline",
      cancelText: "Keep trip",
    });
    if (yes) sendDecline();
  }

  async function sendDecline() {
    setBusy(true);
    setError(null);
    setMessage(null);
    try {
      const res = await call<{ message: string }>(`/trips/${id}/decline`, {
        method: "POST",
        body: { reason, details: details.trim() || null },
      });
      setMessage(res.message);
      setDeclining(false);
      setReason(null);
      setDetails("");
      await load(); // the trip now shows as declined, with a new timeline entry
    } catch (e) {
      setError(e instanceof ApiError ? e.message : "Something went wrong.");
    } finally {
      setBusy(false);
    }
  }

  const header = {
    headerStyle: { backgroundColor: colors.bg },
    headerShadowVisible: false,
    headerTintColor: colors.text,
  };

  if (!trip) {
    return (
      <View style={styles.center}>
        <Stack.Screen options={{ title: "Trip", ...header }} />
        {error ? (
          <Banner kind="error" text={error} />
        ) : (
          <ActivityIndicator size="large" />
        )}
      </View>
    );
  }

  const next = trip.allowed_next[0];
  const hasBar = Boolean(next) || trip.can_decline;
  const sharing = tracking.activeId === trip.id;

  return (
    <View style={styles.screen}>
      <Stack.Screen options={{ title: trip.reference, ...header }} />

      <ScrollView
        contentContainerStyle={[
          styles.content,
          { paddingBottom: hasBar ? 200 + insets.bottom : insets.bottom + 40 },
        ]}
        keyboardShouldPersistTaps="handled"
      >
        <StatusBadge status={trip.status} />

        {trip.is_backload && (
          <Banner
            kind="warn"
            text={`Backload: a return load after ${trip.backload_of ?? "your last trip"}, so the truck does not drive back empty. You can accept or decline it.`}
          />
        )}

        {message && <Banner kind="ok" text={message} />}
        {error && <Banner kind="error" text={error} />}

        <Card>
          <RouteTimeline
            origin={trip.origin_address}
            destination={trip.destination_address}
          />

          <TripRouteCard trip={trip} />
        </Card>

        <View style={styles.tiles}>
          <InfoTile
            icon="time-outline"
            label="Departure"
            value={formatDate(trip.departure_at)}
          />
          <InfoTile
            icon="flag-outline"
            label="Arrival"
            value={formatDate(trip.expected_arrival_at)}
          />
          {trip.truck && (
            <InfoTile
              icon="car-sport-outline"
              label="Truck"
              value={trip.truck.plate_no}
            />
          )}
        </View>

        {trip.status === "en_route" && (
          <Card style={{ gap: 12 }}>
            <View style={styles.trackRow}>
              <View
                style={[
                  styles.liveDot,
                  { backgroundColor: sharing ? colors.green : colors.muted },
                ]}
              />
              <View style={{ flex: 1 }}>
                <Text style={styles.heading}>
                  {sharing
                    ? "Sharing your location"
                    : "Location sharing is off"}
                </Text>
                {sharing && (
                  <Text style={styles.sub}>
                    {tracking.lastSent
                      ? `Last sent ${tracking.lastSent.toLocaleTimeString("en-PH", { hour: "numeric", minute: "2-digit" })}`
                      : "Waiting for the first GPS point..."}
                    {tracking.queued > 0
                      ? ` · ${tracking.queued} waiting to send`
                      : ""}
                  </Text>
                )}
              </View>
            </View>
            {!sharing && (
              <Button
                label="Turn on"
                tone="light"
                onPress={async () => {
                  const started = await startTracking(trip.id);
                  if (!started.ok) setError(started.message ?? null);
                }}
              />
            )}
          </Card>
        )}

        {(trip.status === "accepted" || trip.status === "en_route") && (
          <ProblemReportCard tripId={trip.id} status={trip.status} />
        )}

        {declining && (
          <Card style={{ gap: 10 }}>
            <Text style={styles.heading}>Why are you declining?</Text>
            {DECLINE_REASONS.map((r) => (
              <Pressable
                key={r.value}
                style={[styles.reason, reason === r.value && styles.reasonOn]}
                onPress={() => setReason(r.value)}
              >
                <Ionicons
                  name={
                    reason === r.value ? "radio-button-on" : "radio-button-off"
                  }
                  size={22}
                  color={reason === r.value ? colors.red : colors.muted}
                />
                <Text style={styles.reasonText}>{r.label}</Text>
              </Pressable>
            ))}
            <TextInput
              style={styles.input}
              placeholder={
                reason === "other" ? "Details (required)" : "Details (optional)"
              }
              placeholderTextColor={colors.muted}
              multiline
              maxLength={300}
              value={details}
              onChangeText={setDetails}
            />
          </Card>
        )}

        {trip.rests && trip.rests.length > 0 && (
          <Card style={{ gap: 8 }}>
            <Text style={styles.heading}>Rest breaks counted</Text>
            {trip.rests.map((r, i) => (
              <View key={i} style={styles.restRow}>
                <Ionicons name="cafe-outline" size={20} color={colors.amber} />
                <Text style={styles.restText}>
                  {formatTime(r.started_at)} – {formatTime(r.ended_at)} ·{" "}
                  {r.minutes} min
                </Text>
              </View>
            ))}
          </Card>
        )}

        <Card>
          <Text style={styles.heading}>Timeline</Text>
          {(trip.history ?? []).map((log, i, all) => (
            <View key={i} style={styles.logRow}>
              <View style={styles.logRail}>
                <View style={[styles.logDot, i === 0 && styles.logDotLatest]} />
                {i < all.length - 1 && <View style={styles.logLine} />}
              </View>
              <View style={{ flex: 1, paddingBottom: 16 }}>
                <Text style={styles.logTitle}>
                  {TIMELINE_LABEL[log.status] ?? log.status}
                </Text>
                <Text style={styles.sub}>{formatDate(log.logged_at)}</Text>
                {log.remarks ? (
                  <Text style={styles.logNote}>{log.remarks}</Text>
                ) : null}
              </View>
            </View>
          ))}
        </Card>
      </ScrollView>

      {hasBar && (
        <View style={[styles.bar, { paddingBottom: insets.bottom + 14 }]}>
          {declining ? (
            <>
              <Button
                label="Send decline"
                tone="danger"
                busy={busy}
                onPress={confirmDecline}
              />
              <Pressable
                style={styles.linkWrap}
                onPress={() => setDeclining(false)}
              >
                <Text style={styles.link}>Cancel</Text>
              </Pressable>
            </>
          ) : (
            <>
              {next && (
                <Button
                  label={ACTION_LABEL[next] ?? next}
                  icon={ACTION_ICON[next]}
                  busy={busy}
                  onPress={() => onAction(next)}
                />
              )}
              {trip.can_decline && (
                <Pressable
                  style={styles.linkWrap}
                  onPress={() => {
                    setDeclining(true);
                    setError(null);
                    setMessage(null);
                  }}
                >
                  <Text style={[styles.link, { color: colors.red }]}>
                    Decline this trip
                  </Text>
                </Pressable>
              )}
            </>
          )}
        </View>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  center: {
    flex: 1,
    justifyContent: "center",
    padding: 24,
    backgroundColor: colors.bg,
  },
  content: { padding: 18, gap: 14 },
  tiles: { flexDirection: "row", gap: 10 },
  heading: { fontSize: 18, fontWeight: "800", color: colors.text },
  sub: { fontSize: 14, color: colors.muted, marginTop: 2 },
  trackRow: { flexDirection: "row", alignItems: "center", gap: 12 },
  liveDot: { width: 14, height: 14, borderRadius: 7 },
  reason: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    height: 54,
    paddingHorizontal: 14,
    borderRadius: 14,
    borderWidth: 1.5,
    borderColor: colors.line,
  },
  reasonOn: { borderColor: colors.red, backgroundColor: colors.redSoft },
  reasonText: { fontSize: 17, fontWeight: "600", color: colors.text },
  input: {
    minHeight: 90,
    padding: 14,
    borderRadius: 14,
    backgroundColor: colors.bg,
    fontSize: 17,
    color: colors.text,
    textAlignVertical: "top",
  },
  restRow: { flexDirection: "row", alignItems: "center", gap: 10 },
  restText: { fontSize: 16, color: colors.text, fontWeight: "600" },
  logRow: { flexDirection: "row", gap: 12, marginTop: 14 },
  logRail: { alignItems: "center", width: 16 },
  logDot: {
    width: 12,
    height: 12,
    borderRadius: 6,
    backgroundColor: colors.line,
    marginTop: 4,
  },
  logDotLatest: { backgroundColor: colors.primary },
  logLine: { flex: 1, width: 2, backgroundColor: colors.line, marginTop: 4 },
  logTitle: { fontSize: 17, fontWeight: "700", color: colors.text },
  logNote: { fontSize: 15, color: colors.text, marginTop: 4 },
  bar: {
    position: "absolute",
    left: 0,
    right: 0,
    bottom: 0,
    gap: 4,
    padding: 18,
    backgroundColor: "#fff",
    borderTopLeftRadius: 28,
    borderTopRightRadius: 28,
    ...shadow,
  },
  linkWrap: { alignItems: "center", paddingVertical: 12 },
  link: { fontSize: 17, fontWeight: "700", color: colors.muted },
});
