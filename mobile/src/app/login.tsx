import { Ionicons } from "@expo/vector-icons";
import { StatusBar } from "expo-status-bar";
import { useEffect, useState } from "react";
import {
  Image,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import { ApiError } from "../api";
import { useAuth } from "../auth";
import { Banner, Button } from "../components/ui";
import { getApiUrl, normalizeApiUrl, setApiUrl } from "../server";
import { colors } from "../theme";

// The FreightPath app icon (truck, map pin and amber route on teal).
const LOGO = require("../../assets/images/logo-mark.png");

export default function LoginScreen() {
  const { signIn } = useAuth();
  const insets = useSafeAreaInsets();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [focused, setFocused] = useState<
    "email" | "password" | "server" | null
  >(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [showServer, setShowServer] = useState(false);
  const [serverInput, setServerInput] = useState("");
  const [serverNote, setServerNote] = useState<string | null>(null);
  // Too many wrong passwords: the time the app may try again, and the seconds left.
  const [lockedUntil, setLockedUntil] = useState<number | null>(null);
  const [secondsLeft, setSecondsLeft] = useState(0);

  useEffect(() => {
    getApiUrl().then(setServerInput);
  }, []);

  // Live countdown while locked.
  useEffect(() => {
    if (!lockedUntil) return;
    const tick = () => {
      const left = Math.max(0, Math.ceil((lockedUntil - Date.now()) / 1000));
      setSecondsLeft(left);
      if (left === 0) {
        setLockedUntil(null);
        setError(null);
      }
    };
    tick();
    const timer = setInterval(tick, 1000);
    return () => clearInterval(timer);
  }, [lockedUntil]);

  const locked = lockedUntil !== null && secondsLeft > 0;
  const clock = `${Math.floor(secondsLeft / 60)}:${String(secondsLeft % 60).padStart(2, "0")}`;

  // Saves a new server address, but only after checking that it answers.
  async function saveServer() {
    const url = normalizeApiUrl(serverInput);
    setServerNote("Checking...");

    try {
      const controller = new AbortController();
      const timer = setTimeout(() => controller.abort(), 8000);
      const res = await fetch(`${url}/me`, {
        headers: { Accept: "application/json" },
        signal: controller.signal,
      });
      clearTimeout(timer);

      // Our server answers 401 ("not logged in") on this address.
      if (res.status !== 401) throw new Error("Not a FreightPath server");

      await setApiUrl(url);
      setServerInput(url);
      setServerNote("Saved. The server answered.");
    } catch {
      setServerNote(
        "Cannot reach that address. Check it and your internet connection.",
      );
    }
  }

  async function onSubmit() {
    if (busy || locked) return;
    setError(null);

    if (!email.trim() || !password) {
      setError("Please enter your email and password.");
      return;
    }

    setBusy(true);
    try {
      await signIn(email, password);
      // On success the app switches to the home screen by itself.
    } catch (e) {
      if (e instanceof ApiError && e.status === 429 && e.data?.retry_after) {
        // Locked by the server: show the wait with a live countdown.
        setLockedUntil(Date.now() + e.data.retry_after * 1000);
        setError("Too many wrong tries. Please wait, or call your dispatcher.");
      } else {
        setError(
          e instanceof ApiError
            ? e.message
            : "Something went wrong. Please try again.",
        );
      }
      setBusy(false);
    }
  }

  return (
    <KeyboardAvoidingView
      style={styles.root}
      behavior={Platform.OS === "ios" ? "padding" : "height"}
    >
      <StatusBar style="light" />
      <ScrollView
        contentContainerStyle={{ flexGrow: 1 }}
        keyboardShouldPersistTaps="handled"
        bounces={false}
      >
        {/* Teal top with the app icon and name */}
        <View style={[styles.hero, { paddingTop: insets.top + 36 }]}>
          <View style={[styles.circle, styles.circleBig]} />
          <View style={[styles.circle, styles.circleSmall]} />

          <Image
            source={LOGO}
            style={styles.logo}
            accessibilityLabel="FreightPath logo"
          />
          <Text style={styles.appName}>FreightPath</Text>
          <View style={styles.driverPill}>
            <Ionicons name="person" size={13} color={colors.navy} />
            <Text style={styles.driverPillText}>DRIVER APP</Text>
          </View>
          <Text style={styles.tagline}>
            Your trips, routes and deliveries in one place.
          </Text>

          {/* the amber dotted route from the logo */}
          <View style={styles.route}>
            {Array.from({ length: 14 }).map((_, i) => (
              <View key={i} style={styles.routeDot} />
            ))}
            <View style={styles.routeEnd} />
          </View>
        </View>

        {/* White sheet with the form */}
        <View style={[styles.sheet, { paddingBottom: insets.bottom + 24 }]}>
          <View style={styles.handle} />
          <Text style={styles.title}>Welcome back</Text>
          <Text style={styles.subtitle}>
            Log in with the account your admin gave you.
          </Text>

          <View style={styles.form}>
            <View>
              <Text style={styles.label}>Email</Text>
              <View
                style={[
                  styles.field,
                  focused === "email" && styles.fieldFocused,
                ]}
              >
                <Ionicons
                  name="mail-outline"
                  size={20}
                  color={focused === "email" ? colors.brand : colors.muted}
                />
                <TextInput
                  style={styles.fieldInput}
                  placeholder="driver@example.com"
                  placeholderTextColor="#9CA3AF"
                  autoCapitalize="none"
                  autoCorrect={false}
                  keyboardType="email-address"
                  returnKeyType="next"
                  value={email}
                  onChangeText={setEmail}
                  onFocus={() => setFocused("email")}
                  onBlur={() => setFocused(null)}
                  editable={!busy}
                />
              </View>
            </View>

            <View>
              <Text style={styles.label}>Password</Text>
              <View
                style={[
                  styles.field,
                  focused === "password" && styles.fieldFocused,
                ]}
              >
                <Ionicons
                  name="lock-closed-outline"
                  size={20}
                  color={focused === "password" ? colors.brand : colors.muted}
                />
                <TextInput
                  style={styles.fieldInput}
                  placeholder="Your password"
                  placeholderTextColor="#9CA3AF"
                  secureTextEntry={!showPassword}
                  returnKeyType="go"
                  value={password}
                  onChangeText={setPassword}
                  onSubmitEditing={onSubmit}
                  onFocus={() => setFocused("password")}
                  onBlur={() => setFocused(null)}
                  editable={!busy}
                />
                <Pressable
                  onPress={() => setShowPassword(!showPassword)}
                  hitSlop={12}
                >
                  <Ionicons
                    name={showPassword ? "eye-off-outline" : "eye-outline"}
                    size={22}
                    color={colors.muted}
                  />
                </Pressable>
              </View>
            </View>

            {error && <Banner kind="error" text={error} />}

            {locked && (
              <View style={styles.lockBox}>
                <Ionicons name="time-outline" size={22} color={colors.red} />
                <View style={{ flex: 1 }}>
                  <Text style={styles.lockTitle}>Try again in {clock}</Text>
                  <Text style={styles.lockSub}>
                    Each lock gets longer: 1 minute, then 2, then 5.
                  </Text>
                </View>
              </View>
            )}

            <View style={{ marginTop: 6 }}>
              <Button
                label={locked ? `Wait ${clock}` : "Log in"}
                icon={locked ? "lock-closed" : "log-in-outline"}
                onPress={onSubmit}
                busy={busy}
                disabled={locked}
              />
            </View>

            <View style={styles.help}>
              <Ionicons
                name="information-circle-outline"
                size={18}
                color={colors.muted}
              />
              <Text style={styles.helpText}>
                Forgot your password? Ask your admin or dispatcher to reset it.
              </Text>
            </View>
          </View>

          <View style={{ flex: 1 }} />

          <Pressable
            onPress={() => setShowServer(!showServer)}
            hitSlop={10}
            style={styles.serverLink}
          >
            <Ionicons name="settings-outline" size={15} color={colors.muted} />
            <Text style={styles.serverLinkText}>Server settings</Text>
          </Pressable>

          {showServer && (
            <View style={[styles.form, { marginTop: 12 }]}>
              <View
                style={[
                  styles.field,
                  focused === "server" && styles.fieldFocused,
                ]}
              >
                <Ionicons name="globe-outline" size={20} color={colors.muted} />
                <TextInput
                  style={styles.fieldInput}
                  placeholder="Server address"
                  placeholderTextColor="#9CA3AF"
                  autoCapitalize="none"
                  autoCorrect={false}
                  keyboardType="url"
                  value={serverInput}
                  onChangeText={setServerInput}
                  onFocus={() => setFocused("server")}
                  onBlur={() => setFocused(null)}
                />
              </View>
              <Button label="Save and test" tone="light" onPress={saveServer} />
              {serverNote && (
                <Text style={styles.serverNote}>{serverNote}</Text>
              )}
            </View>
          )}

          <Text style={styles.version}>FreightPath Driver · v1.0</Text>
        </View>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.brand },
  hero: {
    backgroundColor: colors.brand,
    alignItems: "center",
    paddingHorizontal: 24,
    paddingBottom: 56,
    overflow: "hidden",
  },
  circle: {
    position: "absolute",
    borderRadius: 999,
    backgroundColor: "rgba(255,255,255,0.07)",
  },
  circleBig: { width: 320, height: 320, top: -120, right: -110 },
  circleSmall: { width: 180, height: 180, bottom: -60, left: -70 },
  logo: { width: 88, height: 88, borderRadius: 24 },
  appName: {
    marginTop: 14,
    fontSize: 30,
    fontWeight: "900",
    color: "#fff",
    letterSpacing: 0.3,
  },
  driverPill: {
    marginTop: 8,
    flexDirection: "row",
    alignItems: "center",
    gap: 5,
    paddingHorizontal: 12,
    paddingVertical: 5,
    borderRadius: 999,
    backgroundColor: colors.accent,
  },
  driverPillText: {
    fontSize: 12,
    fontWeight: "900",
    color: colors.navy,
    letterSpacing: 1,
  },
  tagline: {
    marginTop: 12,
    fontSize: 15,
    color: "rgba(255,255,255,0.85)",
    textAlign: "center",
  },
  route: { flexDirection: "row", alignItems: "center", gap: 7, marginTop: 20 },
  routeDot: {
    width: 5,
    height: 5,
    borderRadius: 3,
    backgroundColor: colors.accent,
    opacity: 0.9,
  },
  routeEnd: {
    width: 11,
    height: 11,
    borderRadius: 6,
    backgroundColor: colors.accent,
    marginLeft: 2,
  },
  sheet: {
    flex: 1,
    marginTop: -28,
    backgroundColor: "#fff",
    borderTopLeftRadius: 30,
    borderTopRightRadius: 30,
    paddingHorizontal: 24,
    paddingTop: 12,
  },
  handle: {
    alignSelf: "center",
    width: 44,
    height: 5,
    borderRadius: 3,
    backgroundColor: colors.line,
    marginBottom: 20,
  },
  title: { fontSize: 28, fontWeight: "800", color: colors.ink },
  subtitle: { marginTop: 4, fontSize: 16, color: colors.muted },
  form: { marginTop: 22, gap: 14 },
  label: {
    fontSize: 14,
    fontWeight: "700",
    color: colors.ink,
    marginBottom: 6,
    marginLeft: 2,
  },
  field: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    height: 58,
    paddingHorizontal: 16,
    borderRadius: 16,
    backgroundColor: colors.bg,
    borderWidth: 1.5,
    borderColor: colors.bg,
  },
  fieldFocused: { borderColor: colors.brand, backgroundColor: "#fff" },
  fieldInput: { flex: 1, fontSize: 17, color: colors.ink },
  lockBox: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    padding: 14,
    borderRadius: 16,
    backgroundColor: colors.redSoft,
  },
  lockTitle: {
    fontSize: 20,
    fontWeight: "900",
    color: colors.red,
    fontVariant: ["tabular-nums"],
  },
  lockSub: { fontSize: 13, color: colors.red, marginTop: 2 },
  help: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    paddingHorizontal: 4,
  },
  helpText: { flex: 1, fontSize: 14, color: colors.muted },
  serverLink: {
    marginTop: 28,
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    gap: 6,
  },
  serverLinkText: { fontSize: 14, color: colors.muted, fontWeight: "600" },
  serverNote: { fontSize: 15, color: colors.muted, textAlign: "center" },
  version: {
    marginTop: 14,
    textAlign: "center",
    fontSize: 12,
    color: "#9CA3AF",
  },
});
