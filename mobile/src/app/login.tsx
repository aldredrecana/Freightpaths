import { Ionicons } from "@expo/vector-icons";
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

// The FreightPath truck mark (white truck and amber route), shown on a teal square.
const LOGO = require("../../assets/images/logo-mark.png");

export default function LoginScreen() {
  const { signIn } = useAuth();
  const insets = useSafeAreaInsets();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [showServer, setShowServer] = useState(false);
  const [serverInput, setServerInput] = useState("");
  const [serverNote, setServerNote] = useState<string | null>(null);

  useEffect(() => {
    getApiUrl().then(setServerInput);
  }, []);

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
    if (busy) return;
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
      setError(
        e instanceof ApiError
          ? e.message
          : "Something went wrong. Please try again.",
      );
      setBusy(false);
    }
  }

  return (
    <KeyboardAvoidingView
      style={styles.root}
      behavior={Platform.OS === "ios" ? "padding" : "height"}
    >
      <ScrollView
        contentContainerStyle={[
          styles.scroll,
          { paddingTop: insets.top + 56, paddingBottom: insets.bottom + 28 },
        ]}
        keyboardShouldPersistTaps="handled"
      >
        <View style={styles.logo}>
          <Image
            source={LOGO}
            style={styles.logoImage}
            accessibilityLabel="FreightPath logo"
          />
        </View>
        <Text style={styles.title}>Welcome back</Text>
        <Text style={styles.subtitle}>Log in to see your trips.</Text>

        <View style={styles.form}>
          <View style={styles.field}>
            <Ionicons name="mail-outline" size={20} color={colors.muted} />
            <TextInput
              style={styles.fieldInput}
              placeholder="Email"
              placeholderTextColor={colors.muted}
              autoCapitalize="none"
              autoCorrect={false}
              keyboardType="email-address"
              value={email}
              onChangeText={setEmail}
              editable={!busy}
            />
          </View>

          <View style={styles.field}>
            <Ionicons
              name="lock-closed-outline"
              size={20}
              color={colors.muted}
            />
            <TextInput
              style={styles.fieldInput}
              placeholder="Password"
              placeholderTextColor={colors.muted}
              secureTextEntry={!showPassword}
              returnKeyType="go"
              value={password}
              onChangeText={setPassword}
              onSubmitEditing={onSubmit}
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

          {error && <Banner kind="error" text={error} />}

          <Button label="Log in" onPress={onSubmit} busy={busy} />
        </View>

        <Pressable onPress={() => setShowServer(!showServer)} hitSlop={10}>
          <Text style={styles.serverLink}>Server settings</Text>
        </Pressable>

        {showServer && (
          <View style={styles.form}>
            <View style={styles.field}>
              <Ionicons name="globe-outline" size={20} color={colors.muted} />
              <TextInput
                style={styles.fieldInput}
                placeholder="Server address"
                placeholderTextColor={colors.muted}
                autoCapitalize="none"
                autoCorrect={false}
                keyboardType="url"
                value={serverInput}
                onChangeText={setServerInput}
              />
            </View>
            <Button label="Save and test" tone="light" onPress={saveServer} />
            {serverNote && <Text style={styles.serverNote}>{serverNote}</Text>}
          </View>
        )}
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: "#fff" },
  scroll: { flexGrow: 1, paddingHorizontal: 24 },
  logo: {
    width: 64,
    height: 64,
    borderRadius: 20,
    backgroundColor: colors.brand,
    alignItems: "center",
    justifyContent: "center",
  },
  logoImage: { width: 64, height: 64 },
  title: { marginTop: 28, fontSize: 32, fontWeight: "800", color: colors.ink },
  subtitle: { marginTop: 4, fontSize: 17, color: colors.muted },
  form: { marginTop: 28, gap: 14 },
  field: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    height: 60,
    paddingHorizontal: 16,
    borderRadius: 16,
    backgroundColor: colors.bg,
  },
  fieldInput: { flex: 1, fontSize: 18, color: colors.ink },
  serverLink: {
    marginTop: 28,
    textAlign: "center",
    fontSize: 15,
    color: colors.muted,
    textDecorationLine: "underline",
  },
  serverNote: { fontSize: 15, color: colors.muted, textAlign: "center" },
});
