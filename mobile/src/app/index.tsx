import { Pressable, StyleSheet, Text, View } from "react-native";
import { useAuth } from "../auth";

export default function Home() {
  const { user, signOut } = useAuth();

  return (
    <View style={styles.container}>
      <Text style={styles.hello}>Hello, {user?.name}</Text>
      <Text style={styles.sub}>Status: {user?.driver_status}</Text>

      <Pressable style={styles.button} onPress={signOut}>
        <Text style={styles.buttonText}>Log out</Text>
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    justifyContent: "center",
    padding: 24,
    backgroundColor: "#fff",
  },
  hello: { fontSize: 28, fontWeight: "800", color: "#0f172a" },
  sub: { fontSize: 18, color: "#64748b", marginTop: 6, marginBottom: 32 },
  button: {
    height: 60,
    borderRadius: 12,
    backgroundColor: "#475569",
    alignItems: "center",
    justifyContent: "center",
  },
  buttonText: { color: "#fff", fontSize: 20, fontWeight: "700" },
});
