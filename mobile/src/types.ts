export type TripStatus =
  | "pending"
  | "accepted"
  | "en_route"
  | "delivered"
  | "cancelled"
  | "needs_reassignment";

export type Trip = {
  id: number;
  reference: string;
  status: TripStatus;
  origin_address: string;
  destination_address: string;
  departure_at: string;
  expected_arrival_at: string;
  urgency: string | null;
  truck?: { plate_no: string; model: string };
  allowed_next: string[];
  can_decline: boolean;
  history?: {
    status: string;
    remarks: string | null;
    logged_at: string;
    by: string | null;
  }[];
  rests?: { started_at: string; ended_at: string; minutes: number }[];
};

// Words the driver sees for each status.
export const STATUS_LABEL: Record<string, string> = {
  pending: "Waiting for you",
  accepted: "Accepted",
  en_route: "On the way",
  delivered: "Delivered",
  cancelled: "Cancelled",
  needs_reassignment: "Declined",
};

export const STATUS_COLOR: Record<string, { bg: string; fg: string }> = {
  pending: { bg: "#FEF3C7", fg: "#92400E" },
  accepted: { bg: "#E7F5F5", fg: "#0B6E6E" },
  en_route: { bg: "#D1FAE5", fg: "#065F46" },
  delivered: { bg: "#E5E7EB", fg: "#374151" },
  cancelled: { bg: "#E5E7EB", fg: "#374151" },
  needs_reassignment: { bg: "#FEE2E2", fg: "#991B1B" },
};

// The big button for each next step.
export const ACTION_LABEL: Record<string, string> = {
  accepted: "Accept trip",
  en_route: "Start trip",
  delivered: "Mark as delivered",
};

// Timeline wording (same as the website).
export const TIMELINE_LABEL: Record<string, string> = {
  pending: "Trip assigned",
  accepted: "Trip accepted",
  en_route: "Trip started",
  delivered: "Delivered",
  needs_reassignment: "Driver declined",
  cancelled: "Trip cancelled",
};

export function formatDate(iso: string): string {
  return new Date(iso).toLocaleString("en-PH", {
    month: "short",
    day: "numeric",
    hour: "numeric",
    minute: "2-digit",
  });
}

export function formatTime(iso: string): string {
  return new Date(iso).toLocaleTimeString("en-PH", {
    hour: "numeric",
    minute: "2-digit",
  });
}

// "Tue, 18 Nov"
export function formatDay(iso: string): string {
  return new Date(iso).toLocaleDateString("en-PH", {
    weekday: "short",
    day: "numeric",
    month: "short",
  });
}

// "Today's", "Tomorrow's" or "Upcoming" (for "Today's delivery").
export function dayWord(iso: string): string {
  const day = new Date(iso);
  const today = new Date();
  const tomorrow = new Date();
  tomorrow.setDate(today.getDate() + 1);

  if (day.toDateString() === today.toDateString()) return "Today's";
  if (day.toDateString() === tomorrow.toDateString()) return "Tomorrow's";
  return "Upcoming";
}
