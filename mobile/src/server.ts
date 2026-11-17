import AsyncStorage from "@react-native-async-storage/async-storage";
import { DEFAULT_API_URL } from "./config";

const KEY = "freightpath_api_url";

let cached: string | null = null;

// The server address the app talks to: the one saved on the phone, else the default.
export async function getApiUrl(): Promise<string> {
  if (!cached) cached = (await AsyncStorage.getItem(KEY)) ?? DEFAULT_API_URL;
  return cached;
}

export async function setApiUrl(url: string): Promise<void> {
  cached = url;
  await AsyncStorage.setItem(KEY, url);
}

// Turns what a person types ("192.168.1.5:8000", "my-app.up.railway.app")
// into a full API address ("http://192.168.1.5:8000/api", "https://my-app.up.railway.app/api").
export function normalizeApiUrl(input: string): string {
  let url = input.trim().replace(/\/+$/, "");

  if (!/^https?:\/\//i.test(url)) {
    const isLocal =
      /^(\d{1,3}\.){3}\d{1,3}(:\d+)?/.test(url) || url.startsWith("localhost");
    url = (isLocal ? "http://" : "https://") + url;
  }
  if (!/\/api$/i.test(url)) url += "/api";

  return url;
}
