import { Ionicons } from "@expo/vector-icons";
import { useEffect, useRef, useState } from "react";
import {
  Keyboard,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
  type ScrollViewInstance,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import { useAuth, type User } from "../../auth";
import { Banner, Button, Card } from "../../components/ui";
import { confirmDialog } from "../../components/Dialog";
import { colors } from "../../theme";
import { stopTracking } from "../../tracking";
import { useTrips } from "../../trips";
import { useApi } from "../../useApi";

type IconName = keyof typeof Ionicons.glyphMap;
type Notice = { kind: "ok" | "error"; text: string } | null;

// Profile tab: see your details, edit contact number and address,
// change your password, and sign out.
export default function ProfileScreen() {
  const insets = useSafeAreaInsets();
  const call = useApi();
  const { user, setUser, signOut } = useAuth();
  const { trips } = useTrips();

  // Keep the password boxes and buttons above the keyboard: add room at the bottom
  // while the keyboard is open, and scroll down when a password box is tapped.
  const scrollRef = useRef<ScrollViewInstance>(null);
  const [keyboard, setKeyboard] = useState(0);
  useEffect(() => {
    const show = Keyboard.addListener("keyboardDidShow", (e) =>
      setKeyboard(e.endCoordinates.height),
    );
    const hide = Keyboard.addListener("keyboardDidHide", () => setKeyboard(0));
    return () => {
      show.remove();
      hide.remove();
    };
  }, []);
  const scrollDown = () =>
    setTimeout(() => scrollRef.current?.scrollToEnd({ animated: true }), 250);

  const [contact, setContact] = useState(user?.contact_no ?? "");
  const [address, setAddress] = useState(user?.address ?? "");
  const [saving, setSaving] = useState(false);
  const [infoNotice, setInfoNotice] = useState<Notice>(null);

  const [current, setCurrent] = useState("");
  const [next, setNext] = useState("");
  const [confirm, setConfirm] = useState("");
  const [changing, setChanging] = useState(false);
  const [passNotice, setPassNotice] = useState<Notice>(null);

  if (!user) return null;

  const changed =
    contact.trim() !== (user.contact_no ?? "") ||
    address.trim() !== (user.address ?? "");

  async function saveInfo() {
    setInfoNotice(null);
    setSaving(true);
    try {
      const res = await call<{ user: User }>("/me", {
        method: "POST",
        body: { contact_no: contact.trim(), address: address.trim() },
      });
      setUser(res.user);
      setInfoNotice({ kind: "ok", text: "Your details were saved." });
    } catch (e) {
      setInfoNotice({ kind: "error", text: (e as Error).message });
    } finally {
      setSaving(false);
    }
  }

  async function changePassword() {
    setPassNotice(null);
    if (next.length < 8) {
      setPassNotice({
        kind: "error",
        text: "The new password needs at least 8 characters.",
      });
      return;
    }
    if (next !== confirm) {
      setPassNotice({ kind: "error", text: "The new passwords do not match." });
      return;
    }

    setChanging(true);
    try {
      await call("/me/password", {
        method: "POST",
        body: {
          current_password: current,
          password: next,
          password_confirmation: confirm,
        },
      });
      setCurrent("");
      setNext("");
      setConfirm("");
      setPassNotice({
        kind: "ok",
        text: "Password changed. Use the new one next time you sign in.",
      });
    } catch (e) {
      setPassNotice({ kind: "error", text: (e as Error).message });
    } finally {
      setChanging(false);
    }
  }

  async function confirmSignOut() {
    const onTheRoad = trips?.some((t) => t.status === "en_route");
    const yes = await confirmDialog({
      icon: "log-out-outline",
      tone: "danger",
      title: "Sign out?",
      message: onTheRoad
        ? "You have a trip on the way. Signing out stops location sharing, so the dispatcher will see No signal."
        : "You will need your email and password to sign in again.",
      confirmText: "Sign out",
    });
    if (!yes) return;
    await stopTracking(); // sends any waiting points first
    await signOut();
  }

  const initials =
    user.name
      .split(" ")
      .filter(Boolean)
      .slice(0, 2)
      .map((w) => w[0]!.toUpperCase())
      .join("") || "D";

  const expiry = user.license_expiry
    ? new Date(user.license_expiry + "T00:00:00").toLocaleDateString("en-PH", {
        month: "short",
        day: "numeric",
        year: "numeric",
      })
    : "—";

  return (
    <View style={styles.root}>
      <ScrollView
        ref={scrollRef}
        contentContainerStyle={[
          styles.body,
          { paddingTop: insets.top + 18, paddingBottom: 40 + keyboard },
        ]}
        keyboardShouldPersistTaps="handled"
      >
        <Text style={styles.title}>Profile</Text>

        {/* Who is signed in */}
        <Card style={styles.header}>
          <View style={styles.avatar}>
            <Text style={styles.avatarText}>{initials}</Text>
          </View>
          <View style={{ flex: 1 }}>
            <Text style={styles.name}>{user.name}</Text>
            <Text style={styles.muted}>{user.email}</Text>
          </View>
        </Card>

        {/* Details only the admin can change */}
        <Card style={styles.section}>
          <Text style={styles.sectionTitle}>Driver details</Text>
          <Row
            icon="card-outline"
            label="License no."
            value={user.license_no}
          />
          <Row icon="calendar-outline" label="License expiry" value={expiry} />
          <Text style={styles.hint}>
            To change your name, email or license, ask your admin.
          </Text>
        </Card>

        {/* Details the driver can change */}
        <Card style={styles.section}>
          <Text style={styles.sectionTitle}>Contact info</Text>
          {infoNotice && (
            <Banner kind={infoNotice.kind} text={infoNotice.text} />
          )}
          <Field
            icon="call-outline"
            placeholder="Contact number"
            value={contact}
            onChangeText={setContact}
            keyboardType="phone-pad"
          />
          <Field
            icon="home-outline"
            placeholder="Address"
            value={address}
            onChangeText={setAddress}
          />
          <Button
            label="Save changes"
            icon="checkmark"
            onPress={saveInfo}
            busy={saving}
            disabled={!changed}
          />
        </Card>

        {/* Password */}
        <Card style={styles.section}>
          <Text style={styles.sectionTitle}>Change password</Text>
          {passNotice && (
            <Banner kind={passNotice.kind} text={passNotice.text} />
          )}
          <Field
            icon="lock-closed-outline"
            placeholder="Current password"
            onFocus={scrollDown}
            value={current}
            onChangeText={setCurrent}
            secure
          />
          <Field
            icon="key-outline"
            placeholder="New password (8+ characters)"
            onFocus={scrollDown}
            value={next}
            onChangeText={setNext}
            secure
          />
          <Field
            icon="key-outline"
            placeholder="Type the new password again"
            onFocus={scrollDown}
            value={confirm}
            onChangeText={setConfirm}
            secure
          />
          <Button
            label="Change password"
            tone="light"
            onPress={changePassword}
            busy={changing}
            disabled={!current || !next || !confirm}
          />
        </Card>

        <Button
          label="Sign out"
          tone="danger"
          icon="log-out-outline"
          onPress={confirmSignOut}
        />
      </ScrollView>
    </View>
  );
}

function Row({
  icon,
  label,
  value,
}: {
  icon: IconName;
  label: string;
  value: string;
}) {
  return (
    <View style={styles.row}>
      <Ionicons name={icon} size={20} color={colors.muted} />
      <Text style={[styles.muted, { flex: 1 }]}>{label}</Text>
      <Text style={styles.rowValue}>{value}</Text>
    </View>
  );
}

function Field({
  icon,
  placeholder,
  value,
  onChangeText,
  secure = false,
  keyboardType = "default",
  onFocus,
}: {
  icon: IconName;
  placeholder: string;
  value: string;
  onChangeText: (text: string) => void;
  secure?: boolean;
  keyboardType?: "default" | "phone-pad";
  onFocus?: () => void;
}) {
  return (
    <View style={styles.field}>
      <Ionicons name={icon} size={20} color={colors.muted} />
      <TextInput
        style={styles.fieldInput}
        placeholder={placeholder}
        placeholderTextColor={colors.muted}
        value={value}
        onChangeText={onChangeText}
        secureTextEntry={secure}
        autoCapitalize={secure ? "none" : "sentences"}
        autoCorrect={false}
        keyboardType={keyboardType}
        onFocus={onFocus}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.bg },
  body: { paddingHorizontal: 18, gap: 14 },
  title: {
    fontSize: 30,
    fontWeight: "800",
    color: colors.ink,
    paddingHorizontal: 2,
  },
  header: { flexDirection: "row", alignItems: "center", gap: 14 },
  avatar: {
    width: 60,
    height: 60,
    borderRadius: 30,
    backgroundColor: colors.brand,
    alignItems: "center",
    justifyContent: "center",
  },
  avatarText: { color: "#fff", fontSize: 22, fontWeight: "800" },
  name: { fontSize: 20, fontWeight: "800", color: colors.ink },
  muted: { fontSize: 15, color: colors.muted },
  section: { gap: 12 },
  sectionTitle: { fontSize: 18, fontWeight: "800", color: colors.ink },
  row: { flexDirection: "row", alignItems: "center", gap: 10 },
  rowValue: { fontSize: 16, fontWeight: "700", color: colors.ink },
  hint: { fontSize: 14, color: colors.muted },
  field: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    height: 56,
    paddingHorizontal: 16,
    borderRadius: 16,
    backgroundColor: colors.bg,
  },
  fieldInput: { flex: 1, fontSize: 17, color: colors.ink },
});
