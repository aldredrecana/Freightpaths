import { Ionicons } from "@expo/vector-icons";
import * as Location from "expo-location";
import { useCallback, useEffect, useRef, useState } from "react";
import {
  Linking,
  Pressable,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";
import { colors } from "../theme";
import { formatDate } from "../types";
import { useApi } from "../useApi";
import { confirmDialog } from "./Dialog";
import { Banner, Button, Card } from "./ui";

type IconName = keyof typeof Ionicons.glyphMap;
type Report = {
  id: number;
  type: string;
  label: string;
  note: string | null;
  reported_at: string;
  resolved_at: string | null;
  resolution_note: string | null;
};

const TYPES: { value: string; label: string; icon: IconName }[] = [
  { value: "breakdown", label: "Breakdown", icon: "construct-outline" },
  { value: "flat_tire", label: "Flat tire", icon: "disc-outline" },
  { value: "accident", label: "Accident", icon: "car-sport-outline" },
  { value: "road_blocked", label: "Road blocked", icon: "trail-sign-outline" },
  {
    value: "other",
    label: "Other",
    icon: "ellipsis-horizontal-circle-outline",
  },
];

// The driver tells the dispatcher about a problem right away (with the GPS location),
// before leaving (status "accepted") or on the road ("en_route"). For real emergencies: Call 911.
export function ProblemReportCard({
  tripId,
  status,
}: {
  tripId: number;
  status: string;
}) {
  const beforeStart = status === "accepted"; // the truck has not left yet
  const call = useApi();
  const callRef = useRef(call); // useApi gives a new function every render; keep the latest here
  callRef.current = call;
  const [reports, setReports] = useState<Report[]>([]);
  const [open, setOpen] = useState(false);
  const [type, setType] = useState<string | null>(null);
  const [note, setNote] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [sent, setSent] = useState(false);

  const load = useCallback(async () => {
    try {
      const res = await callRef.current<{ incidents: Report[] }>(
        `/trips/${tripId}/incidents`,
      );
      setReports(res.incidents);
    } catch {
      // No internet for a moment: try again on the next check.
    }
  }, [tripId]);

  // Check every 30 seconds, so the driver sees when the dispatcher resolves it.
  useEffect(() => {
    load();
    const timer = setInterval(load, 30_000);
    return () => clearInterval(timer);
  }, [load]);

  // The phone's location. Before Start trip the app may not have asked yet, so ask now.
  async function here() {
    try {
      let perm = await Location.getForegroundPermissionsAsync();
      if (!perm.granted && perm.canAskAgain)
        perm = await Location.requestForegroundPermissionsAsync();
      if (!perm.granted) return null;
      const pos =
        (await Location.getLastKnownPositionAsync({ maxAge: 120_000 })) ??
        (await Location.getCurrentPositionAsync({
          accuracy: Location.Accuracy.Balanced,
        }));
      return pos
        ? {
            lat: pos.coords.latitude,
            lng: pos.coords.longitude,
            accuracy: pos.coords.accuracy ?? null,
          }
        : null;
    } catch {
      return null; // the server uses the trip's last GPS point instead
    }
  }

  async function send() {
    if (!type) {
      setError("Choose what happened.");
      return;
    }
    if (type === "other" && note.trim() === "") {
      setError("Please describe the problem.");
      return;
    }
    setError(null);
    setBusy(true);
    try {
      const where = await here();
      await call(`/trips/${tripId}/incidents`, {
        method: "POST",
        body: { type, note: note.trim() || null, ...(where ?? {}) },
      });
      setOpen(false);
      setType(null);
      setNote("");
      setSent(true);
      await load();
    } catch (e) {
      setError((e as Error).message);
    } finally {
      setBusy(false);
    }
  }

  async function call911() {
    const yes = await confirmDialog({
      icon: "call",
      tone: "danger",
      title: "Call 911?",
      message:
        "Use this for emergencies: an accident with injuries, fire, or danger.",
      confirmText: "Call 911",
    });
    if (yes) Linking.openURL("tel:911");
  }

  function close() {
    setOpen(false);
    setType(null);
    setNote("");
    setError(null);
  }

  return (
    <Card style={styles.card}>
      <View style={styles.headRow}>
        <View style={styles.headIcon}>
          <Ionicons name="warning" size={24} color={colors.red} />
        </View>
        <View style={{ flex: 1 }}>
          <Text style={styles.heading}>
            {open
              ? "What happened?"
              : beforeStart
                ? "Problem with the truck?"
                : "Problem on the road?"}
          </Text>
          <Text style={styles.sub}>
            {open
              ? "Choose one. Your location is sent with the report."
              : beforeStart
                ? "Engine trouble or a flat tire before you leave? Your dispatcher can change the truck or the schedule."
                : "Your dispatcher is told right away, with your location."}
          </Text>
        </View>
      </View>

      {sent && (
        <Banner kind="ok" text="Report sent. Your dispatcher was notified." />
      )}
      {error && <Banner kind="error" text={error} />}

      {open ? (
        <>
          <View style={styles.grid}>
            {TYPES.map((t) => {
              const on = type === t.value;
              return (
                <Pressable
                  key={t.value}
                  onPress={() => setType(t.value)}
                  style={({ pressed }) => [
                    styles.type,
                    on && styles.typeOn,
                    pressed && { opacity: 0.8 },
                  ]}
                >
                  <View
                    style={[
                      styles.typeIcon,
                      on && { backgroundColor: colors.red },
                    ]}
                  >
                    <Ionicons
                      name={t.icon}
                      size={22}
                      color={on ? "#fff" : colors.red}
                    />
                  </View>
                  <Text style={[styles.typeText, on && { color: colors.red }]}>
                    {t.label}
                  </Text>
                  {on && (
                    <Ionicons
                      name="checkmark-circle"
                      size={20}
                      color={colors.red}
                      style={styles.typeCheck}
                    />
                  )}
                </Pressable>
              );
            })}
          </View>
          <TextInput
            style={styles.input}
            placeholder={
              type === "other"
                ? "What happened? (required)"
                : "Add details (optional)"
            }
            placeholderTextColor={colors.muted}
            multiline
            maxLength={500}
            value={note}
            onChangeText={setNote}
          />
          <Button
            label="Send report"
            tone="danger"
            icon="send"
            busy={busy}
            onPress={send}
          />
          <Pressable style={styles.linkWrap} onPress={close} hitSlop={8}>
            <Text style={styles.link}>Cancel</Text>
          </Pressable>
        </>
      ) : (
        <View style={{ gap: 10 }}>
          <Button
            label="Report a problem"
            tone="danger"
            icon="megaphone-outline"
            onPress={() => {
              setOpen(true);
              setSent(false);
              setError(null);
            }}
          />
          <Pressable
            onPress={call911}
            style={({ pressed }) => [styles.sos, pressed && { opacity: 0.8 }]}
          >
            <Ionicons name="call" size={20} color={colors.red} />
            <Text style={styles.sosText}>Emergency? Call 911</Text>
          </Pressable>
        </View>
      )}

      {reports.length > 0 && (
        <View style={{ gap: 8 }}>
          <Text style={styles.listTitle}>YOUR REPORTS</Text>
          {reports.map((r) => {
            const done = Boolean(r.resolved_at);
            return (
              <View key={r.id} style={styles.report}>
                <View style={styles.reportTop}>
                  <Text style={styles.reportTitle}>{r.label}</Text>
                  <View
                    style={[
                      styles.pill,
                      {
                        backgroundColor: done
                          ? colors.greenSoft
                          : colors.amberSoft,
                      },
                    ]}
                  >
                    <Ionicons
                      name={done ? "checkmark-circle" : "time"}
                      size={14}
                      color={done ? colors.green : colors.amber}
                    />
                    <Text
                      style={[
                        styles.pillText,
                        { color: done ? colors.green : colors.amber },
                      ]}
                    >
                      {done ? "Resolved" : "Waiting"}
                    </Text>
                  </View>
                </View>
                <Text style={styles.reportTime}>
                  {formatDate(r.reported_at)}
                </Text>
                {r.note ? (
                  <Text style={styles.reportNote}>"{r.note}"</Text>
                ) : null}
                <Text style={styles.reportStatus}>
                  {done
                    ? `Dispatcher: ${r.resolution_note || "Handled."}`
                    : "Your dispatcher was notified. Waiting for a reply."}
                </Text>
              </View>
            );
          })}
        </View>
      )}
    </Card>
  );
}

const styles = StyleSheet.create({
  card: { gap: 14, borderWidth: 1, borderColor: colors.redSoft },
  headRow: { flexDirection: "row", alignItems: "flex-start", gap: 12 },
  headIcon: {
    width: 46,
    height: 46,
    borderRadius: 23,
    backgroundColor: colors.redSoft,
    alignItems: "center",
    justifyContent: "center",
  },
  heading: { fontSize: 18, fontWeight: "800", color: colors.text },
  sub: { fontSize: 14, lineHeight: 20, color: colors.muted, marginTop: 2 },
  sos: {
    height: 52,
    borderRadius: 16,
    borderWidth: 1.5,
    borderColor: colors.redSoft,
    backgroundColor: "#fff",
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    gap: 8,
  },
  sosText: { fontSize: 16, fontWeight: "800", color: colors.red },
  grid: { flexDirection: "row", flexWrap: "wrap", gap: 10 },
  type: {
    width: "48%",
    flexGrow: 1,
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    padding: 10,
    borderRadius: 16,
    borderWidth: 1.5,
    borderColor: colors.line,
    backgroundColor: "#fff",
  },
  typeOn: { borderColor: colors.red, backgroundColor: "#FFF5F5" },
  typeIcon: {
    width: 38,
    height: 38,
    borderRadius: 12,
    backgroundColor: colors.redSoft,
    alignItems: "center",
    justifyContent: "center",
  },
  typeText: { flex: 1, fontSize: 15, fontWeight: "700", color: colors.text },
  typeCheck: { marginLeft: -4 },
  input: {
    minHeight: 80,
    borderRadius: 14,
    backgroundColor: colors.bg,
    padding: 14,
    fontSize: 16,
    color: colors.text,
    textAlignVertical: "top",
  },
  linkWrap: { alignItems: "center", paddingVertical: 4 },
  link: { fontSize: 16, fontWeight: "700", color: colors.muted },
  listTitle: {
    fontSize: 12,
    fontWeight: "800",
    color: colors.muted,
    letterSpacing: 0.8,
    marginTop: 2,
  },
  report: { backgroundColor: colors.bg, borderRadius: 14, padding: 12, gap: 2 },
  reportTop: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    gap: 8,
  },
  reportTitle: { fontSize: 16, fontWeight: "800", color: colors.text },
  reportTime: { fontSize: 13, color: colors.muted },
  reportNote: {
    fontSize: 14,
    color: colors.text,
    fontStyle: "italic",
    marginTop: 4,
  },
  reportStatus: { fontSize: 14, color: colors.muted, marginTop: 4 },
  pill: {
    flexDirection: "row",
    alignItems: "center",
    gap: 4,
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 999,
  },
  pillText: { fontSize: 12, fontWeight: "800" },
});
