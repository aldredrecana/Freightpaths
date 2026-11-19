import { createContext, useContext, useState, type ReactNode } from "react";
import { ApiError } from "./api";
import { syncTracking } from "./tracking";
import type { Trip } from "./types";
import { useApi } from "./useApi";

const ACTIVE = ["en_route", "accepted", "pending"];
const PRIORITY: Record<string, number> = {
  en_route: 0,
  accepted: 1,
  pending: 2,
};

type TripsState = {
  trips: Trip[] | null; // null while the first load is running
  active: Trip[];
  history: Trip[];
  current: Trip | undefined; // the trip to look at first
  online: boolean;
  error: string | null;
  warning: string | null;
  refreshing: boolean;
  refresh: () => Promise<void>;
  pull: () => Promise<void>; // pull-to-refresh
};

const TripsContext = createContext<TripsState | null>(null);

// Loads the driver's trips once and shares them with the three tabs.
export function TripsProvider({ children }: { children: ReactNode }) {
  const call = useApi();
  const [trips, setTrips] = useState<Trip[] | null>(null);
  const [online, setOnline] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [warning, setWarning] = useState<string | null>(null);
  const [refreshing, setRefreshing] = useState(false);

  async function refresh() {
    try {
      const res = await call<{ data: Trip[] }>("/trips");
      setTrips(res.data);
      setOnline(true);
      setError(null);

      // Location is shared only while a trip is on the way.
      const tracking = await syncTracking(res.data);
      setWarning(tracking.ok ? null : (tracking.message ?? null));
    } catch (e) {
      setOnline(false);
      setError(e instanceof ApiError ? e.message : "Something went wrong.");
    }
  }

  async function pull() {
    setRefreshing(true);
    await refresh();
    setRefreshing(false);
  }

  const all = trips ?? [];
  const active = all
    .filter((t) => ACTIVE.includes(t.status))
    .sort(
      (a, b) =>
        PRIORITY[a.status] - PRIORITY[b.status] ||
        a.departure_at.localeCompare(b.departure_at),
    );
  const history = all.filter((t) => !ACTIVE.includes(t.status));

  return (
    <TripsContext.Provider
      value={{
        trips,
        active,
        history,
        current: active[0],
        online,
        error,
        warning,
        refreshing,
        refresh,
        pull,
      }}
    >
      {children}
    </TripsContext.Provider>
  );
}

export function useTrips() {
  const ctx = useContext(TripsContext);
  if (!ctx) throw new Error("useTrips must be used inside TripsProvider");
  return ctx;
}
