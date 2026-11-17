import { useEffect, useState } from "react";
import {
  ActivityIndicator,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  StyleSheet,
  Text,
  TextInput,
} from "react-native";
import { ApiError } from "../api";
import { useAuth } from "../auth";
import { getApiUrl, normalizeApiUrl, setApiUrl } from "../server";

export default function LoginScreen() {
  const { signIn } = useAuth();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
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
      style={styles.container}
      behavior={Platform.OS === "ios" ? "padding" : "height"}
    >
      <Text style={styles.title}>FreightPath</Text>
      <Text style={styles.subtitle}>Driver app</Text>

      <TextInput
        style={styles.input}
        placeholder="Email"
        autoCapitalize="none"
        autoCorrect={false}
        keyboardType="email-address"
        value={email}
        onChangeText={setEmail}
        editable={!busy}
      />
      <TextInput
        style={styles.input}
        placeholder="Password"
        secureTextEntry
        returnKeyType="go"
        value={password}
        onChangeText={setPassword}
        onSubmitEditing={onSubmit}
        editable={!busy}
      />

      {error && <Text style={styles.error}>{error}</Text>}

      <Pressable
        style={[styles.button, busy && styles.buttonDisabled]}
        onPress={onSubmit}
        disabled={busy}
      >
        {busy ? (
          <ActivityIndicator color="#fff" />
        ) : (
          <Text style={styles.buttonText}>Log in</Text>
        )}
      </Pressable>

      <Pressable onPress={() => setShowServer(!showServer)}>
        <Text style={styles.serverLink}>Server settings</Text>
      </Pressable>

      {showServer && (
        <>
          <TextInput
            style={styles.input}
            placeholder="Server address"
            autoCapitalize="none"
            autoCorrect={false}
            keyboardType="url"
            value={serverInput}
            onChangeText={setServerInput}
          />
          <Pressable style={styles.serverButton} onPress={saveServer}>
            <Text style={styles.serverButtonText}>Save and test</Text>
          </Pressable>
          {serverNote && <Text style={styles.serverNote}>{serverNote}</Text>}
        </>
      )}
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    justifyContent: "center",
    padding: 24,
    backgroundColor: "#fff",
  },
  title: { fontSize: 34, fontWeight: "800", color: "#0f172a" },
  subtitle: { fontSize: 18, color: "#64748b", marginBottom: 32 },
  input: {
    height: 58,
    borderWidth: 1.5,
    borderColor: "#cbd5e1",
    borderRadius: 12,
    paddingHorizontal: 16,
    fontSize: 18,
    marginBottom: 14,
  },
  error: { color: "#b91c1c", fontSize: 16, marginBottom: 14 },
  button: {
    height: 60,
    borderRadius: 12,
    backgroundColor: "#1d4ed8",
    alignItems: "center",
    justifyContent: "center",
    marginTop: 6,
  },
  buttonDisabled: { opacity: 0.6 },
  buttonText: { color: "#fff", fontSize: 20, fontWeight: "700" },

  serverLink: {
    marginTop: 28,
    textAlign: "center",
    fontSize: 16,
    color: "#64748b",
    textDecorationLine: "underline",
  },
  serverButton: {
    height: 52,
    borderRadius: 12,
    backgroundColor: "#e2e8f0",
    alignItems: "center",
    justifyContent: "center",
  },
  serverButtonText: { fontSize: 18, fontWeight: "700", color: "#334155" },
  serverNote: {
    marginTop: 10,
    fontSize: 15,
    color: "#475569",
    textAlign: "center",
  },
});
