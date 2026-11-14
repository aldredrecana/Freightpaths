import { useEffect, useState } from "react";
import { getTrackingInfo } from "./tracking";

type TrackingInfo = {
  activeId: number | null;
  lastSent: Date | null;
  queued: number;
};

// Re-reads the tracking status every 2 seconds so the screen stays current.
export function useTrackingInfo() {
  const [info, setInfo] = useState<TrackingInfo>({
    activeId: null,
    lastSent: null,
    queued: 0,
  });

  useEffect(() => {
    let alive = true;

    async function refresh() {
      const next = await getTrackingInfo();
      if (alive) setInfo(next);
    }

    refresh();
    const timer = setInterval(refresh, 2000);

    return () => {
      alive = false;
      clearInterval(timer);
    };
  }, []);

  return info;
}
