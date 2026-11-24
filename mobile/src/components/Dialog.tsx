import { Ionicons } from "@expo/vector-icons";
import { useEffect, useState } from "react";
import { Alert, Modal, Pressable, StyleSheet, Text, View } from "react-native";
import { colors } from "../theme";

// FreightPath's own pop-up, used instead of the plain grey Android alert.
// Call showDialog(...) or confirmDialog(...) from anywhere; <DialogHost /> (in _layout.tsx) draws it.

type IconName = keyof typeof Ionicons.glyphMap;
type Tone = "brand" | "danger" | "success" | "warn";

export type DialogButton = {
  text: string;
  value: string;
  kind?: "primary" | "danger" | "cancel";
};

export type DialogOptions = {
  title: string;
  message?: string;
  icon?: IconName;
  tone?: Tone;
  buttons: DialogButton[];
};

type Request = DialogOptions & { resolve: (value: string | null) => void };

const TONES: Record<Tone, { fg: string; bg: string }> = {
  brand: { fg: colors.brand, bg: colors.brandSoft },
  danger: { fg: colors.red, bg: colors.redSoft },
  success: { fg: colors.green, bg: colors.greenSoft },
  warn: { fg: colors.amber, bg: colors.amberSoft },
};

// A small queue, so two pop-ups asked at the same time show one after the other.
let queue: Request[] = [];
let notify: (() => void) | null = null;

// Shows the pop-up and waits for the tap. Gives back the button's value (null if closed).
export function showDialog(options: DialogOptions): Promise<string | null> {
  return new Promise((resolve) => {
    if (!notify) {
      // The host is not on screen (should not happen): use the normal alert.
      Alert.alert(
        options.title,
        options.message,
        options.buttons.map((b) => ({
          text: b.text,
          style:
            b.kind === "cancel"
              ? "cancel"
              : b.kind === "danger"
                ? "destructive"
                : "default",
          onPress: () => resolve(b.value),
        })),
        { cancelable: false },
      );
      return;
    }
    queue.push({ ...options, resolve });
    notify();
  });
}

// Yes/no question. Gives back true when the driver taps the main button.
export async function confirmDialog(o: {
  title: string;
  message?: string;
  icon?: IconName;
  tone?: Tone;
  confirmText: string;
  cancelText?: string;
}): Promise<boolean> {
  const answer = await showDialog({
    title: o.title,
    message: o.message,
    icon: o.icon,
    tone: o.tone,
    buttons: [
      {
        text: o.confirmText,
        value: "yes",
        kind: o.tone === "danger" ? "danger" : "primary",
      },
      { text: o.cancelText ?? "Cancel", value: "no", kind: "cancel" },
    ],
  });
  return answer === "yes";
}

export function DialogHost() {
  const [current, setCurrent] = useState<Request | null>(null);

  useEffect(() => {
    notify = () => setCurrent((now) => now ?? queue.shift() ?? null);
    return () => {
      notify = null;
    };
  }, []);

  function answer(value: string | null) {
    current?.resolve(value);
    setCurrent(queue.shift() ?? null);
  }

  if (!current) return null;
  const tone = TONES[current.tone ?? "brand"];
  const cancel = current.buttons.find((b) => b.kind === "cancel");

  return (
    <Modal
      transparent
      visible
      animationType="fade"
      statusBarTranslucent
      onRequestClose={() => answer(cancel?.value ?? null)}
    >
      <View style={styles.backdrop}>
        <View style={styles.box}>
          {current.icon && (
            <View style={[styles.iconWrap, { backgroundColor: tone.bg }]}>
              <Ionicons name={current.icon} size={34} color={tone.fg} />
            </View>
          )}
          <Text style={styles.title}>{current.title}</Text>
          {current.message ? (
            <Text style={styles.message}>{current.message}</Text>
          ) : null}

          <View style={styles.buttons}>
            {current.buttons.map((b) => {
              const look =
                b.kind === "cancel"
                  ? { bg: "transparent", fg: colors.muted }
                  : b.kind === "danger"
                    ? { bg: colors.red, fg: "#fff" }
                    : {
                        bg: tone.fg === colors.red ? colors.red : colors.brand,
                        fg: "#fff",
                      };
              return (
                <Pressable
                  key={b.value}
                  onPress={() => answer(b.value)}
                  style={({ pressed }) => [
                    styles.button,
                    { backgroundColor: look.bg },
                    b.kind === "cancel" && styles.cancel,
                    pressed && { opacity: 0.8 },
                  ]}
                >
                  <Text style={[styles.buttonText, { color: look.fg }]}>
                    {b.text}
                  </Text>
                </Pressable>
              );
            })}
          </View>
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  backdrop: {
    flex: 1,
    backgroundColor: "rgba(7, 30, 30, 0.55)",
    alignItems: "center",
    justifyContent: "center",
    padding: 24,
  },
  box: {
    width: "100%",
    maxWidth: 400,
    backgroundColor: "#fff",
    borderRadius: 28,
    paddingTop: 28,
    paddingHorizontal: 22,
    paddingBottom: 16,
    alignItems: "center",
  },
  iconWrap: {
    width: 72,
    height: 72,
    borderRadius: 36,
    alignItems: "center",
    justifyContent: "center",
    marginBottom: 16,
  },
  title: {
    fontSize: 22,
    fontWeight: "800",
    color: colors.ink,
    textAlign: "center",
  },
  message: {
    marginTop: 8,
    fontSize: 16,
    lineHeight: 23,
    color: colors.muted,
    textAlign: "center",
  },
  buttons: { alignSelf: "stretch", marginTop: 22, gap: 6 },
  button: {
    height: 56,
    borderRadius: 16,
    alignItems: "center",
    justifyContent: "center",
  },
  cancel: { height: 48 },
  buttonText: { fontSize: 17, fontWeight: "800" },
});
