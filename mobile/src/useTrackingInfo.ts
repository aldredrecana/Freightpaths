import { useEffect, useState } from "react";
import { getTrackingInfo } from "./tracking";

// Re-reads the tracking status every 2 seconds so the screen stays current.
export function useTrackingInfo() {
  const [info, setInfo] = useState(getTrackingInfo());

  useEffect(() => {
    const t = setInterval(() => setInfo(getTrackingInfo()), 2000);
    return () => clearInterval(t);
  }, []);

  return info;
}
