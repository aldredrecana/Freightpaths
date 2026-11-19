import { Ionicons } from "@expo/vector-icons";
import type { ReactNode } from "react";
import {
  ActivityIndicator,
  Pressable,
  StyleSheet,
  Text,
  View,
  type StyleProp,
  type ViewStyle,
} from "react-native";
import { colors, shadow } from "../theme";

type IconName = keyof typeof Ionicons.glyphMap;

export function Card({
  children,
  style,
}: {
  children: ReactNode;
  style?: StyleProp<ViewStyle>;
}) {
  return <View style={[styles.card, style]}>{children}</View>;
}

// The big rounded button used for every main action.
export function Button({
  label,
  onPress,
  busy = false,
  disabled = false,
  tone = "primary",
  icon,
}: {
  label: string;
  onPress: () => void;
  busy?: boolean;
  disabled?: boolean;
  tone?: "primary" | "danger" | "light";
  icon?: IconName;
}) {
  const tones = {
    primary: { bg: colors.brand, fg: "#fff" },
    danger: { bg: colors.red, fg: "#fff" },
    light: { bg: colors.line, fg: colors.ink },
  };
  const palette = tones[tone];

  return (
    <Pressable
      onPress={onPress}
      disabled={busy || disabled}
      style={({ pressed }) => [
        styles.button,
        { backgroundColor: palette.bg },
        (busy || disabled) && { opacity: 0.6 },
        pressed && { opacity: 0.85 },
      ]}
    >
      {busy ? (
        <ActivityIndicator color={palette.fg} />
      ) : (
        <View style={styles.buttonRow}>
          {icon && <Ionicons name={icon} size={22} color={palette.fg} />}
          <Text style={[styles.buttonText, { color: palette.fg }]}>
            {label}
          </Text>
        </View>
      )}
    </Pressable>
  );
}

// A colored message box: green for success, red for errors, amber for warnings.
export function Banner({
  kind,
  text,
}: {
  kind: "ok" | "error" | "warn";
  text: string;
}) {
  const palette = {
    ok: {
      bg: colors.greenSoft,
      fg: "#065F46",
      icon: "checkmark-circle" as IconName,
    },
    error: {
      bg: colors.redSoft,
      fg: "#991B1B",
      icon: "alert-circle" as IconName,
    },
    warn: {
      bg: colors.amberSoft,
      fg: colors.amber,
      icon: "information-circle" as IconName,
    },
  }[kind];

  return (
    <View style={[styles.banner, { backgroundColor: palette.bg }]}>
      <Ionicons name={palette.icon} size={22} color={palette.fg} />
      <Text style={[styles.bannerText, { color: palette.fg }]}>{text}</Text>
    </View>
  );
}

// Pickup and drop-off joined by a line, like a ride-hailing trip card.
export function RouteTimeline({
  origin,
  destination,
}: {
  origin: string;
  destination: string;
}) {
  return (
    <View style={styles.route}>
      <View style={styles.rail}>
        <View style={styles.pickup}>
          <View style={styles.pickupInner} />
        </View>
        <View style={styles.railLine} />
        <View style={styles.drop}>
          <View style={styles.dropInner} />
        </View>
      </View>

      <View style={styles.routeText}>
        <View>
          <Text style={styles.routeLabel}>PICKUP</Text>
          <Text style={styles.routeValue}>{origin}</Text>
        </View>
        <View>
          <Text style={styles.routeLabel}>DROP-OFF</Text>
          <Text style={styles.routeValue}>{destination}</Text>
        </View>
      </View>
    </View>
  );
}

export function InfoTile({
  icon,
  label,
  value,
}: {
  icon: IconName;
  label: string;
  value: string;
}) {
  return (
    <View style={styles.tile}>
      <Ionicons name={icon} size={20} color={colors.brand} />
      <Text style={styles.tileLabel}>{label}</Text>
      <Text style={styles.tileValue}>{value}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    backgroundColor: colors.card,
    borderRadius: 20,
    padding: 18,
    ...shadow,
  },
  button: {
    height: 60,
    borderRadius: 18,
    alignItems: "center",
    justifyContent: "center",
  },
  buttonRow: { flexDirection: "row", alignItems: "center", gap: 10 },
  buttonText: { fontSize: 19, fontWeight: "800" },
  banner: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    padding: 14,
    borderRadius: 14,
  },
  bannerText: { flex: 1, fontSize: 16, fontWeight: "600" },
  route: { flexDirection: "row", gap: 14 },
  rail: { alignItems: "center", paddingVertical: 4 },
  pickup: {
    width: 22,
    height: 22,
    borderRadius: 11,
    backgroundColor: colors.brand,
    alignItems: "center",
    justifyContent: "center",
  },
  pickupInner: {
    width: 8,
    height: 8,
    borderRadius: 4,
    backgroundColor: "#fff",
  },
  railLine: {
    flex: 1,
    width: 2,
    backgroundColor: colors.line,
    marginVertical: 2,
  },
  drop: {
    width: 22,
    height: 22,
    borderRadius: 6,
    backgroundColor: colors.ink,
    alignItems: "center",
    justifyContent: "center",
  },
  dropInner: { width: 7, height: 7, borderRadius: 2, backgroundColor: "#fff" },
  routeText: { flex: 1, gap: 18 },
  routeLabel: {
    fontSize: 12,
    color: colors.muted,
    fontWeight: "700",
    letterSpacing: 0.8,
  },
  routeValue: {
    fontSize: 18,
    color: colors.ink,
    fontWeight: "800",
    marginTop: 2,
  },
  tile: {
    flex: 1,
    backgroundColor: colors.card,
    borderRadius: 16,
    padding: 14,
    gap: 4,
    ...shadow,
  },
  tileLabel: { fontSize: 13, color: colors.muted, fontWeight: "600" },
  tileValue: { fontSize: 15, color: colors.ink, fontWeight: "700" },
});
