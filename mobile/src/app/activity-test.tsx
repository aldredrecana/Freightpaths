import { Ionicons } from "@expo/vector-icons";
import ActivityRecognition from "expo-activity-recognition";
import * as Location from "expo-location";
import { useEffect, useRef, useState } from "react";
import { ScrollView, StyleSheet, Text, View } from "react-native";
import { Banner, Button, Card } from "../components/ui";
import { colors } from "../theme";

// TEST SCREEN: checks if Android Activity Recognition works on this phone.
// It shows what Android thinks you are doing (still / walking / driving) next to the GPS speed.

type IconName = keyof typeof Ionicons.glyphMap;
type LogRow = {
  time: string;
  activity: string;
  confidence: number;
  kmh: number | null;
};

const LOOK: Record<string, { icon: IconName; color: string; label: string }> = {
  still: { icon: "pause-circle", color: colors.muted, label: "Still" },
  walking: { icon: "walk", color: colors.amber, label: "Walking" },
  running: { icon: "walk", color: colors.amber, label: "Running" },
  driving: { icon: "car", color: colors.brand, label: "In a vehicle" },
  cycling: { icon: "bicycle", color: colors.brand, label: "Cycling" },
  unknown: { icon: "help-circle", color: colors.muted, label: "Unknown" },
};

export default function ActivityTest() {
  const [running, setRunning] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [current, setCurrent] = useState<{
    activity: string;
    confidence: number;
  } | null>(null);
  const [kmh, setKmh] = useState<number | null>(null);
  const [log, setLog] = useState<LogRow[]>([]);
  const kmhRef = useRef<number | null>(null);
  const stops = useRef<(() => void)[]>([]);

  // Stop everything when leaving the screen.
  useEffect(() => () => stopAll(), []);

  function stopAll() {
    stops.current.forEach((stop) => stop());
    stops.current = [];
    ActivityRecognition.removeObserver().catch(() => {});
  }

  async function start() {
    setError(null);
    try {
      const perm = await ActivityRecognition.requestPermissions();
      if (!perm.granted) {
        setError(
          'Allow "Physical activity" for FreightPath in the phone settings, then try again.',
        );
        return;
      }

      // Activity changes from Android.
      const sub = ActivityRecognition.addListener(
        "onActivityChange",
        ({ activity, confidence }) => {
          setCurrent({ activity, confidence });
          const time = new Date().toLocaleTimeString("en-PH", {
            hour: "numeric",
            minute: "2-digit",
            second: "2-digit",
          });
          setLog((rows) =>
            [
              { time, activity, confidence, kmh: kmhRef.current },
              ...rows,
            ].slice(0, 40),
          );
        },
      );
      stops.current.push(() => sub.remove());

      // Ask for an update about every 10 seconds (Android may answer slower).
      await ActivityRecognition.setupObserver(10_000);

      // GPS speed, to compare.
      const loc = await Location.requestForegroundPermissionsAsync();
      if (loc.granted) {
        const watch = await Location.watchPositionAsync(
          {
            accuracy: Location.Accuracy.High,
            timeInterval: 3000,
            distanceInterval: 0,
          },
          (pos) => {
            const s = pos.coords.speed;
            const v = s !== null && s >= 0 ? Math.round(s * 3.6) : null;
            kmhRef.current = v;
            setKmh(v);
          },
        );
        stops.current.push(() => watch.remove());
      }

      setRunning(true);
    } catch (e) {
      setError(`Activity Recognition failed: ${(e as Error).message}`);
      stopAll();
    }
  }

  function stop() {
    stopAll();
    setRunning(false);
  }

  const look = LOOK[current?.activity ?? ""] ?? null;

  return (
    <ScrollView
      style={{ flex: 1, backgroundColor: colors.bg }}
      contentContainerStyle={styles.body}
    >
      <Banner
        kind="warn"
        text="Test only. Walk around, then ride or drive. Check if the activity matches what you are doing."
      />
      {error && <Banner kind="error" text={error} />}

      <Card style={styles.now}>
        {look ? (
          <>
            <Ionicons name={look.icon} size={64} color={look.color} />
            <Text style={[styles.nowText, { color: look.color }]}>
              {look.label}
            </Text>
            <Text style={styles.sub}>Confidence {current!.confidence}%</Text>
          </>
        ) : (
          <>
            <Ionicons name="hourglass-outline" size={56} color={colors.muted} />
            <Text style={styles.nowText}>
              {running ? "Waiting for Android..." : "Not started"}
            </Text>
            <Text style={styles.sub}>
              The first result can take up to a minute.
            </Text>
          </>
        )}
        <Text style={styles.speed}>
          GPS speed: {kmh === null ? "–" : `${kmh} km/h`}
        </Text>
      </Card>

      {running ? (
        <Button
          label="Stop test"
          tone="light"
          icon="stop-circle-outline"
          onPress={stop}
        />
      ) : (
        <Button label="Start test" icon="play" onPress={start} />
      )}

      {log.length > 0 && (
        <Card style={{ gap: 6 }}>
          <Text style={styles.heading}>Changes</Text>
          {log.map((r, i) => (
            <Text key={i} style={styles.row}>
              {r.time} · {LOOK[r.activity]?.label ?? r.activity} ({r.confidence}
              %) · GPS {r.kmh === null ? "–" : `${r.kmh} km/h`}
            </Text>
          ))}
        </Card>
      )}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  body: { padding: 18, gap: 14 },
  now: { alignItems: "center", gap: 4, paddingVertical: 28 },
  nowText: { fontSize: 26, fontWeight: "900", color: colors.ink },
  sub: { fontSize: 14, color: colors.muted },
  speed: { marginTop: 10, fontSize: 16, fontWeight: "700", color: colors.ink },
  heading: { fontSize: 17, fontWeight: "800", color: colors.ink },
  row: { fontSize: 14, color: colors.ink },
});
