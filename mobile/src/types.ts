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
  pending: { bg: "#fef3c7", fg: "#92400e" },
  accepted: { bg: "#dbeafe", fg: "#1e40af" },
  en_route: { bg: "#e0e7ff", fg: "#3730a3" },
  delivered: { bg: "#dcfce7", fg: "#166534" },
  cancelled: { bg: "#e2e8f0", fg: "#475569" },
  needs_reassignment: { bg: "#fee2e2", fg: "#991b1b" },
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
