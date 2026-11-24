import { Ionicons } from "@expo/vector-icons";
import { ImageManipulator, SaveFormat } from "expo-image-manipulator";
import * as ImagePicker from "expo-image-picker";
import * as Location from "expo-location";
import { router, useLocalSearchParams } from "expo-router";
import { useRef, useState } from "react";
import {
  Alert,
  Image,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";
import SignatureScreen, {
  type SignatureViewRef,
} from "react-native-signature-canvas";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import { ApiError } from "../../api";
import { Banner, Button, Card } from "../../components/ui";
import { colors } from "../../theme";
import { stopTracking } from "../../tracking";
import { useApi } from "../../useApi";

// Hide the signature pad's own buttons; we use ours.
const PAD_STYLE = `
  .m-signature-pad { box-shadow: none; border: none; margin: 0; }
  .m-signature-pad--body { border: none; }
  .m-signature-pad--footer { display: none; margin: 0; }
  body, html { height: 100%; background: #fff; }
`;

// Proof of delivery: a photo of the goods, the receiver's name and signature.
// Only after these are sent can the trip be marked Delivered.
export default function DeliverScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const call = useApi();
  const insets = useSafeAreaInsets();
  const pad = useRef<SignatureViewRef>(null);

  const [photo, setPhoto] = useState<{ uri: string; base64: string } | null>(
    null,
  );
  const [receiver, setReceiver] = useState("");
  const [note, setNote] = useState("");
  const [signature, setSignature] = useState<string | null>(null);
  const [signing, setSigning] = useState(false); // stops the page from scrolling while signing
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function takePhoto() {
    setError(null);
    const perm = await ImagePicker.requestCameraPermissionsAsync();
    if (!perm.granted) {
      setError(
        "Allow the camera in your phone settings to take the delivery photo.",
      );
      return;
    }
    const shot = await ImagePicker.launchCameraAsync({
      mediaTypes: ["images"],
      quality: 0.8,
    });
    if (shot.canceled || !shot.assets[0]) return;

    // Make it small before sending: 1280 px wide JPEG (about 150-300 KB).
    const rendered = await ImageManipulator.manipulate(shot.assets[0].uri)
      .resize({ width: 1280 })
      .renderAsync();
    const saved = await rendered.saveAsync({
      compress: 0.6,
      format: SaveFormat.JPEG,
      base64: true,
    });
    if (saved.base64) setPhoto({ uri: saved.uri, base64: saved.base64 });
  }

  async function here() {
    try {
      const perm = await Location.getForegroundPermissionsAsync();
      if (!perm.granted) return {};
      const pos = await Location.getLastKnownPositionAsync({ maxAge: 120_000 });
      return pos ? { lat: pos.coords.latitude, lng: pos.coords.longitude } : {};
    } catch {
      return {};
    }
  }

  const ready = Boolean(photo && receiver.trim() && signature);

  async function confirm() {
    if (!photo || !signature || !receiver.trim()) return;
    setBusy(true);
    setError(null);
    try {
      // 1. Save the proof. 2. Mark the trip delivered. 3. Stop sharing the location.
      await call(`/trips/${id}/proof`, {
        method: "POST",
        body: {
          photo: photo.base64,
          signature,
          received_by: receiver.trim(),
          note: note.trim() || null,
          ...(await here()),
        },
      });
      await call(`/trips/${id}/status`, {
        method: "POST",
        body: { status: "delivered" },
      });
      await stopTracking();

      Alert.alert("Delivered", "Thank you! The trip is marked as delivered.", [
        { text: "OK", onPress: () => router.back() },
      ]);
    } catch (e) {
      setError(
        e instanceof ApiError
          ? e.message
          : "Could not send. Check your internet and try again.",
      );
    } finally {
      setBusy(false);
    }
  }

  return (
    <View style={{ flex: 1, backgroundColor: colors.bg }}>
      <ScrollView
        scrollEnabled={!signing}
        contentContainerStyle={[
          styles.body,
          { paddingBottom: insets.bottom + 140 },
        ]}
        keyboardShouldPersistTaps="handled"
      >
        {error && <Banner kind="error" text={error} />}

        {/* 1. Photo */}
        <Card style={styles.section}>
          <Step
            n={1}
            title="Photo of the delivered goods"
            done={Boolean(photo)}
          />
          {photo ? (
            <>
              <Image source={{ uri: photo.uri }} style={styles.photo} />
              <Button
                label="Retake photo"
                tone="light"
                icon="camera-outline"
                onPress={takePhoto}
              />
            </>
          ) : (
            <Button label="Take photo" icon="camera" onPress={takePhoto} />
          )}
        </Card>

        {/* 2. Receiver */}
        <Card style={styles.section}>
          <Step
            n={2}
            title="Who received the goods?"
            done={Boolean(receiver.trim())}
          />
          <TextInput
            style={styles.input}
            placeholder="Full name of the receiver"
            placeholderTextColor={colors.muted}
            value={receiver}
            onChangeText={setReceiver}
            autoCapitalize="words"
            maxLength={100}
          />
          <TextInput
            style={[styles.input, { minHeight: 60 }]}
            placeholder="Note (optional), e.g. 2 boxes damaged"
            placeholderTextColor={colors.muted}
            value={note}
            onChangeText={setNote}
            multiline
            maxLength={300}
          />
        </Card>

        {/* 3. Signature */}
        <Card style={styles.section}>
          <Step n={3} title="Receiver's signature" done={Boolean(signature)} />
          <Text style={styles.sub}>
            Hand the phone to the receiver and ask them to sign in the box.
          </Text>
          <View style={styles.padBox}>
            <SignatureScreen
              ref={pad}
              webStyle={PAD_STYLE}
              descriptionText=""
              penColor="#111827"
              backgroundColor="#ffffff"
              imageType="image/png"
              onBegin={() => setSigning(true)}
              onEnd={() => {
                setSigning(false);
                pad.current?.readSignature(); // sends the picture to onOK
              }}
              onOK={(img) => setSignature(img)}
              onEmpty={() => setSignature(null)}
            />
          </View>
          <Pressable
            style={styles.clear}
            onPress={() => {
              pad.current?.clearSignature();
              setSignature(null);
            }}
          >
            <Ionicons name="refresh" size={18} color={colors.muted} />
            <Text style={styles.clearText}>Clear signature</Text>
          </Pressable>
        </Card>
      </ScrollView>

      <View style={[styles.bar, { paddingBottom: insets.bottom + 14 }]}>
        {!ready && (
          <Text style={styles.hint}>
            Take the photo, type the receiver's name, and get the signature.
          </Text>
        )}
        <Button
          label="Confirm delivery"
          icon="flag"
          busy={busy}
          disabled={!ready}
          onPress={confirm}
        />
      </View>
    </View>
  );
}

function Step({ n, title, done }: { n: number; title: string; done: boolean }) {
  return (
    <View style={styles.stepRow}>
      <View style={[styles.stepDot, done && { backgroundColor: colors.green }]}>
        {done ? (
          <Ionicons name="checkmark" size={16} color="#fff" />
        ) : (
          <Text style={styles.stepNum}>{n}</Text>
        )}
      </View>
      <Text style={styles.stepTitle}>{title}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  body: { padding: 18, gap: 14 },
  section: { gap: 12 },
  stepRow: { flexDirection: "row", alignItems: "center", gap: 10 },
  stepDot: {
    width: 28,
    height: 28,
    borderRadius: 14,
    backgroundColor: colors.brand,
    alignItems: "center",
    justifyContent: "center",
  },
  stepNum: { color: "#fff", fontWeight: "800" },
  stepTitle: { fontSize: 17, fontWeight: "800", color: colors.text, flex: 1 },
  sub: { fontSize: 14, color: colors.muted },
  photo: {
    width: "100%",
    height: 220,
    borderRadius: 14,
    backgroundColor: colors.line,
  },
  input: {
    minHeight: 52,
    borderRadius: 14,
    backgroundColor: colors.bg,
    paddingHorizontal: 14,
    paddingVertical: 12,
    fontSize: 16,
    color: colors.text,
    textAlignVertical: "top",
  },
  padBox: {
    height: 220,
    borderRadius: 14,
    borderWidth: 1.5,
    borderColor: colors.line,
    borderStyle: "dashed",
    overflow: "hidden",
    backgroundColor: "#fff",
  },
  clear: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    alignSelf: "flex-end",
    padding: 4,
  },
  clearText: { fontSize: 15, fontWeight: "700", color: colors.muted },
  bar: {
    position: "absolute",
    left: 0,
    right: 0,
    bottom: 0,
    paddingHorizontal: 18,
    paddingTop: 12,
    gap: 8,
    backgroundColor: "#fff",
    borderTopWidth: 1,
    borderTopColor: colors.line,
  },
  hint: { fontSize: 13, color: colors.muted, textAlign: "center" },
});
