import { Ionicons } from "@expo/vector-icons";
import { useEffect, useState } from "react";
import { StyleSheet, Text, View } from "react-native";
import { colors } from "../theme";
import { useApi } from "../useApi";
import { confirmDialog, showDialog } from "./Dialog";
import { Banner, Button, Card } from "./ui";

// "Take a break" / "End break": the driver tells the dispatcher he is away from the truck
// (eating, buying something, restroom), so walking around is not mistaken for the truck moving.
export function BreakCard({
  tripId,
  since,
  onChange,
}: {
  tripId: number;
  since: string | null;
  onChange: () => void | Promise<void>;
}) {
  const call = useApi();
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [now, setNow] = useState(Date.now());

  // Update the "for 12 min" text every 30 seconds while on a break.
  useEffect(() => {
    if (!since) return;
    const timer = setInterval(() => setNow(Date.now()), 30_000);
    return () => clearInterval(timer);
  }, [since]);

  async function start() {
    const yes = await confirmDialog({
      icon: "cafe",
      title: "Take a break?",
      message:
        "Your dispatcher will see that you are on a break. Location sharing stays on.",
      confirmText: "Start break",
      cancelText: "Not now",
    });
    if (!yes) return;
    await send("start");
  }

  async function end() {
    const res = await send("end");
    if (res) {
      await showDialog({
        icon: "checkmark-circle",
        tone: "success",
        title: "Break ended",
        message: `Your break lasted ${res.minutes ?? 0} min. Drive safe!`,
        buttons: [{ text: "OK", value: "ok" }],
      });
    }
  }

  async function send(action: "start" | "end") {
    setError(null);
    setBusy(true);
    try {
      const res = await call<{ minutes?: number | null }>(
        `/trips/${tripId}/break/${action}`,
        {
          method: "POST",
        },
      );
      await onChange();
      return res;
    } catch (e) {
      setError((e as Error).message);
      return null;
    } finally {
      setBusy(false);
    }
  }

  const minutes = since
    ? Math.max(0, Math.floor((now - new Date(since).getTime()) / 60_000))
    : 0;
  const startedAt = since
    ? new Date(since).toLocaleTimeString("en-PH", {
        hour: "numeric",
        minute: "2-digit",
      })
    : "";

  return (
    <Card style={[styles.card, since && styles.onBreak]}>
      <View style={styles.row}>
        <View style={[styles.icon, since && { backgroundColor: colors.amber }]}>
          <Ionicons
            name="cafe"
            size={22}
            color={since ? "#fff" : colors.amber}
          />
        </View>
        <View style={{ flex: 1 }}>
          <Text style={styles.title}>
            {since ? "You are on a break" : "Need a break?"}
          </Text>
          <Text style={styles.sub}>
            {since
              ? `Since ${startedAt} (${minutes} min). Tap End break before you drive.`
              : "Eating, buying something or the restroom? Let your dispatcher know."}
          </Text>
        </View>
      </View>

      {error && <Banner kind="error" text={error} />}

      {since ? (
        <Button label="End break" icon="play" busy={busy} onPress={end} />
      ) : (
        <Button
          label="Take a break"
          tone="light"
          icon="cafe-outline"
          busy={busy}
          onPress={start}
        />
      )}
    </Card>
  );
}

const styles = StyleSheet.create({
  card: { gap: 12 },
  onBreak: { borderWidth: 1.5, borderColor: colors.amber },
  row: { flexDirection: "row", alignItems: "flex-start", gap: 12 },
  icon: {
    width: 44,
    height: 44,
    borderRadius: 22,
    backgroundColor: colors.amberSoft,
    alignItems: "center",
    justifyContent: "center",
  },
  title: { fontSize: 18, fontWeight: "800", color: colors.ink },
  sub: { fontSize: 14, lineHeight: 20, color: colors.muted, marginTop: 2 },
});
