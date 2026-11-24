import { Ionicons } from "@expo/vector-icons";
import * as Location from "expo-location";
import { useCallback, useEffect, useRef, useState } from "react";
import {
  Alert,
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
  { value: "flat_tire", label: "Flat tire", icon: "ellipse-outline" },
  { value: "accident", label: "Accident", icon: "warning-outline" },
  {
    value: "road_blocked",
    label: "Road blocked",
    icon: "remove-circle-outline",
  },
  { value: "other", label: "Other", icon: "help-circle-outline" },
];

// "Problem on the road?" — the driver tells the dispatcher right away (with the GPS location),
// and sees when the dispatcher has handled it. For real emergencies: Call 911.
export function ProblemReportCard({ tripId }: { tripId: number }) {
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

  // The phone's location, if location is allowed. Quick: last known spot, else a fast fix.
  async function here() {
    try {
      const perm = await Location.getForegroundPermissionsAsync();
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

  function call911() {
    Alert.alert(
      "Call 911?",
      "Use this for emergencies: an accident with injuries, fire, or danger.",
      [
        { text: "Cancel", style: "cancel" },
        {
          text: "Call 911",
          style: "destructive",
          onPress: () => Linking.openURL("tel:911"),
        },
      ],
    );
  }

  return (
    <Card style={{ gap: 12 }}>
      <View style={styles.headRow}>
        <Ionicons name="warning" size={22} color={colors.red} />
        <View style={{ flex: 1 }}>
          <Text style={styles.heading}>Problem on the road?</Text>
          <Text style={styles.sub}>
            Your dispatcher is told right away, with your location.
          </Text>
        </View>
      </View>

      {sent && (
        <Banner kind="ok" text="Report sent. Your dispatcher was notified." />
      )}
      {error && <Banner kind="error" text={error} />}

      {open ? (
        <>
          <View style={styles.chips}>
            {TYPES.map((t) => {
              const on = type === t.value;
              return (
                <Pressable
                  key={t.value}
                  onPress={() => setType(t.value)}
                  style={[styles.chip, on && styles.chipOn]}
                >
                  <Ionicons
                    name={t.icon}
                    size={18}
                    color={on ? "#fff" : colors.red}
                  />
                  <Text style={[styles.chipText, on && { color: "#fff" }]}>
                    {t.label}
                  </Text>
                </Pressable>
              );
            })}
          </View>
          <TextInput
            style={styles.input}
            placeholder={
              type === "other"
                ? "What happened? (required)"
                : "What happened? (optional)"
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
          <Pressable style={styles.linkWrap} onPress={() => setOpen(false)}>
            <Text style={styles.link}>Cancel</Text>
          </Pressable>
        </>
      ) : (
        <View style={styles.actions}>
          <View style={{ flex: 1 }}>
            <Button
              label="Report a problem"
              tone="danger"
              icon="warning-outline"
              onPress={() => {
                setOpen(true);
                setSent(false);
                setError(null);
              }}
            />
          </View>
          <View style={{ flex: 1 }}>
            <Button
              label="Call 911"
              tone="light"
              icon="call-outline"
              onPress={call911}
            />
          </View>
        </View>
      )}

      {reports.map((r) => (
        <View key={r.id} style={styles.report}>
          <Ionicons
            name={r.resolved_at ? "checkmark-circle" : "time-outline"}
            size={20}
            color={r.resolved_at ? colors.green : colors.amber}
          />
          <View style={{ flex: 1 }}>
            <Text style={styles.reportTitle}>
              {r.label} · {formatDate(r.reported_at)}
            </Text>
            {r.note ? <Text style={styles.sub}>{r.note}</Text> : null}
            <Text
              style={[
                styles.status,
                { color: r.resolved_at ? colors.green : colors.amber },
              ]}
            >
              {r.resolved_at
                ? `Resolved by dispatcher${r.resolution_note ? `: ${r.resolution_note}` : ""}`
                : "Dispatcher notified. Waiting for a reply."}
            </Text>
          </View>
        </View>
      ))}
    </Card>
  );
}

const styles = StyleSheet.create({
  headRow: { flexDirection: "row", alignItems: "center", gap: 10 },
  heading: { fontSize: 18, fontWeight: "800", color: colors.text },
  sub: { fontSize: 14, color: colors.muted, marginTop: 2 },
  actions: { flexDirection: "row", gap: 10 },
  chips: { flexDirection: "row", flexWrap: "wrap", gap: 8 },
  chip: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    paddingVertical: 10,
    paddingHorizontal: 12,
    borderRadius: 12,
    borderWidth: 1.5,
    borderColor: colors.redSoft,
    backgroundColor: "#fff",
  },
  chipOn: { backgroundColor: colors.red, borderColor: colors.red },
  chipText: { fontSize: 15, fontWeight: "700", color: colors.text },
  input: {
    minHeight: 70,
    borderRadius: 14,
    backgroundColor: colors.bg,
    padding: 14,
    fontSize: 16,
    color: colors.text,
    textAlignVertical: "top",
  },
  linkWrap: { alignItems: "center", paddingVertical: 6 },
  link: { fontSize: 16, fontWeight: "700", color: colors.muted },
  report: {
    flexDirection: "row",
    gap: 10,
    paddingTop: 10,
    borderTopWidth: 1,
    borderTopColor: colors.line,
  },
  reportTitle: { fontSize: 15, fontWeight: "700", color: colors.text },
  status: { fontSize: 14, fontWeight: "600", marginTop: 2 },
});
