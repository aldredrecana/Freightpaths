import { api, ApiError } from "./api";
import { useAuth } from "./auth";

// Calls the API with the saved token. If the server says 401, log out.
export function useApi() {
  const { token, signOut } = useAuth();

  return async function call<T>(
    path: string,
    options: { method?: "GET" | "POST"; body?: unknown } = {},
  ): Promise<T> {
    try {
      return await api<T>(path, { ...options, token });
    } catch (e) {
      if (e instanceof ApiError && e.status === 401) await signOut();
      throw e;
    }
  };
}
