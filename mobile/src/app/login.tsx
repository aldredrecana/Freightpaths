import { useState } from "react";
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

export default function LoginScreen() {
  const { signIn } = useAuth();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

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
});
