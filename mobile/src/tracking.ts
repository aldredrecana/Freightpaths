import AsyncStorage from "@react-native-async-storage/async-storage";
import * as Location from "expo-location";
import * as SecureStore from "expo-secure-store";
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

const QUEUE_KEY = "freightpath_ping_queue";
const MAX_QUEUE = 2000; // oldest points are dropped beyond this
const BATCH = 100; // points per upload
const GPS_EVERY_MS = 15_000; // ask the phone for a point this often
const SEND_EVERY_MS = 30_000; // try to upload this often

let watcher: Location.LocationSubscription | null = null;
let timer: ReturnType<typeof setInterval> | null = null;
let activeId: number | null = null;
let flushing = false;
let lastSent: Date | null = null;
let queued = 0;

// ---- The waiting line of points (kept on the phone, so nothing is lost offline) ----

async function readQueue(): Promise<Stored[]> {
  const raw = await AsyncStorage.getItem(QUEUE_KEY);
  const list: Stored[] = raw ? JSON.parse(raw) : [];
  queued = list.length;
  return list;
}

async function writeQueue(list: Stored[]) {
  queued = list.length;
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
        if (tripId === activeId) await stopTracking(false);
        continue;
      }

      lastSent = new Date();
      const fresh = await readQueue();
      await writeQueue(fresh.slice(n));
    }
  } finally {
    flushing = false;
  }
}

// ---- Start / stop ----

export async function startTracking(
  tripId: number,
): Promise<{ ok: boolean; message?: string }> {
  if (activeId === tripId && watcher) return { ok: true };
  await stopTracking();

  const permission = await Location.requestForegroundPermissionsAsync();
  if (permission.status !== "granted") {
    return {
      ok: false,
      message:
        "Location permission is needed so the dispatcher can follow your trip. Allow it in your phone settings.",
    };
  }

  activeId = tripId;
  watcher = await Location.watchPositionAsync(
    {
      accuracy: Location.Accuracy.Balanced,
      timeInterval: GPS_EVERY_MS,
      distanceInterval: 0, // report even when standing still, so rests can be seen
    },
    (loc) => {
      enqueue({
        schedule_id: tripId,
        lat: loc.coords.latitude,
        lng: loc.coords.longitude,
        accuracy: loc.coords.accuracy ?? null,
        speed: loc.coords.speed ?? null,
        recorded_at: new Date(loc.timestamp).toISOString(),
      });
    },
  );
  timer = setInterval(flushQueue, SEND_EVERY_MS);

  return { ok: true };
}

export async function stopTracking(flushFirst = true): Promise<void> {
  watcher?.remove();
  watcher = null;

  if (timer) {
    clearInterval(timer);
    timer = null;
  }

  activeId = null;
  if (flushFirst) await flushQueue();
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

export function getTrackingInfo() {
  return { activeId, lastSent, queued };
}
