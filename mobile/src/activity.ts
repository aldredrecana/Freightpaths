import ActivityRecognition from "expo-activity-recognition";
import AsyncStorage from "@react-native-async-storage/async-storage";
import { showDialog } from "./components/Dialog";

// Android Activity Recognition: the phone's own motion sensors (accelerometer) and Google's
// trained model say if the person is still, walking or in a vehicle. We send it with each
// GPS point, so the website can tell the driver walking apart from the truck moving.

type Reading = { activity: string; confidence: number; at: number };

const ASKED_KEY = "freightpath_activity_asked_v1";
const FRESH_MS = 3 * 60_000; // older readings are not sent

let latest: Reading | null = null;
let subscription: { remove: () => void } | null = null;

// The newest reading, if it is recent enough to trust.
export function currentActivity(): {
  activity: string;
  activity_confidence: number;
} | null {
  if (!latest || Date.now() - latest.at > FRESH_MS) return null;
  return { activity: latest.activity, activity_confidence: latest.confidence };
}

// Starts listening (asks for the "Physical activity" permission once). Safe to call again.
export async function startActivity(): Promise<void> {
  if (subscription) return;
  try {
    const status = await ActivityRecognition.checkPermissions();
    if (status.status !== "authorized") {
      // Explain once before Android asks; if the driver says no, tracking still works (GPS only).
      if (await AsyncStorage.getItem(ASKED_KEY)) return;
      await AsyncStorage.setItem(ASKED_KEY, "yes");
      await showDialog({
        icon: "walk",
        title: "Allow physical activity",
        message:
          "FreightPath uses your phone's motion sensor to tell when you are walking (for example during a break), " +
          "so the dispatcher does not think the truck is moving.",
        buttons: [{ text: "Continue", value: "ok" }],
      });
      const asked = await ActivityRecognition.requestPermissions();
      if (!asked.granted) return;
    }

    subscription = ActivityRecognition.addListener(
      "onActivityChange",
      ({ activity, confidence }) => {
        latest = { activity, confidence, at: Date.now() };
      },
    );
    await ActivityRecognition.setupObserver(15_000);
  } catch {
    // No Google Play services or no sensor: tracking works with GPS only.
    subscription?.remove();
    subscription = null;
  }
}

export async function stopActivity(): Promise<void> {
  subscription?.remove();
  subscription = null;
  latest = null;
  await ActivityRecognition.removeObserver().catch(() => {});
}
