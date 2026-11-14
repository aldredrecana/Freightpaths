import * as SecureStore from "expo-secure-store";
import {
  createContext,
  useContext,
  useEffect,
  useState,
  type ReactNode,
} from "react";
import { api, ApiError } from "./api";
import { TOKEN_KEY } from "./config";

export type User = {
  id: number;
  name: string;
  email: string;
  driver_id: number;
  driver_status: string;
};

type AuthState = {
  user: User | null;
  token: string | null;
  loading: boolean;
  signIn: (email: string, password: string) => Promise<void>;
  signOut: () => Promise<void>;
};

const AuthContext = createContext<AuthState | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [token, setToken] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  // When the app opens: reuse the saved token if the server still accepts it.
  useEffect(() => {
    (async () => {
      try {
        const saved = await SecureStore.getItemAsync(TOKEN_KEY);
        if (saved) {
          const res = await api<{ user: User }>("/me", { token: saved });
          setToken(saved);
          setUser(res.user);
        }
      } catch (e) {
        if (e instanceof ApiError && e.status === 401) {
          await SecureStore.deleteItemAsync(TOKEN_KEY);
        }
      } finally {
        setLoading(false);
      }
    })();
  }, []);

  async function signIn(email: string, password: string) {
    const res = await api<{ token: string; user: User }>("/login", {
      method: "POST",
      body: {
        email: email.trim().toLowerCase(),
        password,
        device_name: "driver-phone",
      },
    });
    await SecureStore.setItemAsync(TOKEN_KEY, res.token);
    setToken(res.token);
    setUser(res.user);
  }

  async function signOut() {
    try {
      if (token) await api("/logout", { method: "POST", token });
    } catch {
      // Even if the server can't be reached, log out on this phone.
    }
    await SecureStore.deleteItemAsync(TOKEN_KEY);
    setToken(null);
    setUser(null);
  }

  return (
    <AuthContext.Provider value={{ user, token, loading, signIn, signOut }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error("useAuth must be used inside AuthProvider");
  return ctx;
}
