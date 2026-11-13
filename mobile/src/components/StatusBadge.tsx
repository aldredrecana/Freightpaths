import { StyleSheet, Text, View } from "react-native";
import { STATUS_COLOR, STATUS_LABEL } from "../types";

export function StatusBadge({ status }: { status: string }) {
  const color = STATUS_COLOR[status] ?? STATUS_COLOR.cancelled;

  return (
    <View style={[styles.badge, { backgroundColor: color.bg }]}>
      <Text style={[styles.text, { color: color.fg }]}>
        {STATUS_LABEL[status] ?? status}
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  badge: {
    alignSelf: "flex-start",
    paddingHorizontal: 12,
    paddingVertical: 5,
    borderRadius: 999,
  },
  text: { fontSize: 14, fontWeight: "700" },
});
