import AsyncStorage from "@react-native-async-storage/async-storage";
import * as Location from "expo-location";
import * as SecureStore from "expo-secure-store";
import * as TaskManager from "expo-task-manager";
import { Alert, PermissionsAndroid, Platform } from "react-native";
import { api, ApiError } from "./api";
import { TOKEN_KEY } from "./config";

type Ping = {
  lat: number;
  lng: number;
  accuracy: number | null;
  speed: number | null;
  recorded_at: string;
};
type Stored = Ping & { schedule_id: number };

const TASK_NAME = "freightpath-location";
const QUEUE_KEY = "freightpath_ping_queue";
const ACTIVE_KEY = "freightpath_active_trip";
const SENT_KEY = "freightpath_last_sent";
const CONSENT_KEY = "freightpath_location_consent_v1"; // saved once the driver agrees
const MAX_QUEUE = 2000; // oldest points are dropped beyond this
const BATCH = 100; // points per upload
const GPS_EVERY_MS = 15_000; // ask the phone for a point this often

let flushing = false;

// ---- The background task: Android runs this even when the screen is off ----

TaskManager.defineTask<{ locations: Location.LocationObject[] }>(
  TASK_NAME,
  async ({ data, error }) => {
    if (error || !data) return;

    const tripId = await readActiveTrip();
    if (tripId === null) return;

    for (const loc of data.locations) {
      await enqueue({
        schedule_id: tripId,
        lat: loc.coords.latitude,
        lng: loc.coords.longitude,
        accuracy: loc.coords.accuracy ?? null,
        speed: loc.coords.speed ?? null,
        recorded_at: new Date(loc.timestamp).toISOString(),
      });
    }

    await flushQueue();
  },
);

async function readActiveTrip(): Promise<number | null> {
  const raw = await AsyncStorage.getItem(ACTIVE_KEY);
  return raw ? Number(raw) : null;
}

// ---- The waiting line of points (kept on the phone, so nothing is lost offline) ----

async function readQueue(): Promise<Stored[]> {
  const raw = await AsyncStorage.getItem(QUEUE_KEY);
  return raw ? JSON.parse(raw) : [];
}

async function writeQueue(list: Stored[]) {
  await AsyncStorage.setItem(QUEUE_KEY, JSON.stringify(list));
}

async function enqueue(point: Stored) {
  const list = await readQueue();
  list.push(point);
  await writeQueue(list.slice(-MAX_QUEUE));
}

// ---- Uploading ----

// Sends waiting points to the server. If the network is down they stay in the line.
export async function flushQueue(): Promise<void> {
  if (flushing) return;
  flushing = true;

  try {
    const token = await SecureStore.getItemAsync(TOKEN_KEY);
    if (!token) return;

    while (true) {
      const list = await readQueue();
      if (list.length === 0) return;

      // One upload = leading points that belong to the same trip.
      const tripId = list[0].schedule_id;
      let n = 0;
      while (n < list.length && n < BATCH && list[n].schedule_id === tripId)
        n++;
      const batch = list.slice(0, n);

      try {
        await api("/locations", {
          method: "POST",
          token,
          body: {
            schedule_id: tripId,
            pings: batch.map(({ schedule_id, ...ping }) => ping),
          },
        });
      } catch (e) {
        const status = e instanceof ApiError ? e.status : 0;
        const rejected =
          status >= 400 && status < 500 && status !== 401 && status !== 429;

        if (!rejected) return; // no network, logged out, or too many requests: try later

        // The server refuses these points (for example the trip is no longer on the road).
        const fresh = await readQueue();
        await writeQueue(fresh.slice(n));
        if (tripId === (await readActiveTrip())) await stopTracking(false);
        continue;
      }

      await AsyncStorage.setItem(SENT_KEY, new Date().toISOString());
      const fresh = await readQueue();
      await writeQueue(fresh.slice(n));
    }
  } finally {
    flushing = false;
  }
}

// ---- Start / stop ----
// Privacy notice (Data Privacy Act of 2012). Asked once; the answer is saved on the phone.
async function askConsent(): Promise<boolean> {
  if ((await AsyncStorage.getItem(CONSENT_KEY)) === "yes") return true;

  const agreed = await new Promise<boolean>((resolve) => {
    Alert.alert(
      "Location sharing",
      "While a trip is on the way (from Start trip until Delivered), FreightPath records your phone's location, speed and time, " +
        "even when the screen is off.\n\n" +
        "Only your dispatcher and admin can see it. It is used to follow the delivery and record rest stops, " +
        "and it is kept with the trip record.\n\n" +
        "Tracking stops when you mark the trip Delivered.",
      [
        { text: "Not now", style: "cancel", onPress: () => resolve(false) },
        { text: "I agree", onPress: () => resolve(true) },
      ],
      { cancelable: false },
    );
  });

  if (agreed) await AsyncStorage.setItem(CONSENT_KEY, "yes");
  return agreed;
}
function explainBackgroundPermission(): Promise<void> {
  return new Promise((resolve) => {
    Alert.alert(
      "Allow location all the time",
      'To follow your trip while the screen is off, choose "Allow all the time" on the next screen.',
      [{ text: "Continue", onPress: () => resolve() }],
      { cancelable: false },
    );
  });
}

export async function startTracking(
  tripId: number,
): Promise<{ ok: boolean; message?: string }> {
  const running = await Location.hasStartedLocationUpdatesAsync(TASK_NAME);
  if (running && (await readActiveTrip()) === tripId) {
    // Already tracking. If nothing has been sent yet (e.g. app reopened), try a quick point.
    if (!(await AsyncStorage.getItem(SENT_KEY))) sendFirstPointNow(tripId);
    return { ok: true };
  }
  if (running) await stopTracking();
  if (!(await askConsent())) {
    return {
      ok: false,
      message:
        'Location sharing is needed while a trip is on the way. Tap "Turn on" on the trip to read the notice again.',
    };
  }

  const foreground = await Location.requestForegroundPermissionsAsync();
  if (foreground.status !== "granted") {
    return {
      ok: false,
      message:
        "Location permission is needed so the dispatcher can follow your trip. Allow it in your phone settings.",
    };
  }

  const background = await Location.getBackgroundPermissionsAsync();
  if (background.status !== "granted") {
    await explainBackgroundPermission();
    const asked = await Location.requestBackgroundPermissionsAsync();
    if (asked.status !== "granted") {
      return {
        ok: false,
        message:
          'To follow your trip while the screen is off, set Location to "Allow all the time" for this app in your phone settings.',
      };
    }
  }

  // Android 13+ needs this to show the "trip in progress" notification.
  if (Platform.OS === "android" && Platform.Version >= 33) {
    await PermissionsAndroid.request(
      PermissionsAndroid.PERMISSIONS.POST_NOTIFICATIONS,
    );
  }

  await AsyncStorage.setItem(ACTIVE_KEY, String(tripId));
  await AsyncStorage.removeItem(SENT_KEY);

  await Location.startLocationUpdatesAsync(TASK_NAME, {
    accuracy: Location.Accuracy.High, // GPS-level points, so rests are detected reliably
    timeInterval: GPS_EVERY_MS,
    distanceInterval: 0, // report even when standing still, so rests can be seen
    showsBackgroundLocationIndicator: true,
    foregroundService: {
      notificationTitle: "FreightPath trip in progress",
      notificationBody:
        "Your location is shared with the dispatcher until you mark the trip delivered.",
      notificationColor: "#0B6E6E",
    },
  });

  sendFirstPointNow(tripId); // don't wait for the first background GPS fix
  return { ok: true };
}

// The background task can take a minute or more for its first point (a GPS "cold start",
// especially indoors). So right after Start trip we send one quick point ourselves:
// the phone's last known spot if it is fresh, otherwise a fast Wi-Fi/cell-tower fix.
async function sendFirstPointNow(tripId: number): Promise<void> {
  try {
    const loc =
      (await Location.getLastKnownPositionAsync({
        maxAge: 60_000, // at most 1 minute old
        requiredAccuracy: 200, // within 200 m
      })) ??
      (await Location.getCurrentPositionAsync({
        accuracy: Location.Accuracy.Balanced, // fast: uses Wi-Fi and cell towers too
      }));

    if ((await readActiveTrip()) !== tripId) return; // the trip ended meanwhile

    await enqueue({
      schedule_id: tripId,
      lat: loc.coords.latitude,
      lng: loc.coords.longitude,
      accuracy: loc.coords.accuracy ?? null,
      speed: loc.coords.speed ?? null,
      recorded_at: new Date(loc.timestamp).toISOString(),
    });
    await flushQueue();
  } catch {
    // No quick point available: the background task will send the first one.
  }
}
export async function stopTracking(flushFirst = true): Promise<void> {
  if (await Location.hasStartedLocationUpdatesAsync(TASK_NAME)) {
    await Location.stopLocationUpdatesAsync(TASK_NAME);
  }

  if (flushFirst) await flushQueue();
  await AsyncStorage.removeItem(ACTIVE_KEY);
}

// Call this with the driver's trips: tracking runs only while a trip is on the way.
export async function syncTracking(
  trips: { id: number; status: string }[],
): Promise<{ ok: boolean; message?: string }> {
  flushQueue(); // also uploads anything left over from earlier

  const onTheRoad = trips.find((t) => t.status === "en_route");
  if (onTheRoad) return startTracking(onTheRoad.id);

  await stopTracking();
  return { ok: true };
}

export async function getTrackingInfo() {
  const [active, running, sent, queue] = await Promise.all([
    AsyncStorage.getItem(ACTIVE_KEY),
    Location.hasStartedLocationUpdatesAsync(TASK_NAME),
    AsyncStorage.getItem(SENT_KEY),
    AsyncStorage.getItem(QUEUE_KEY),
  ]);

  return {
    activeId: running && active ? Number(active) : null,
    lastSent: sent ? new Date(sent) : null,
    queued: queue ? (JSON.parse(queue) as unknown[]).length : 0,
  };
}
