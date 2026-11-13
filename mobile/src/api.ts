import { API_URL } from "./config";

export class ApiError extends Error {
  status: number;

  constructor(message: string, status: number) {
    super(message);
    this.status = status;
  }
}

type Options = {
  method?: "GET" | "POST";
  body?: unknown;
  token?: string | null;
};

// One place that talks to the Laravel API.
export async function api<T>(
  path: string,
  { method = "GET", body, token }: Options = {},
): Promise<T> {
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), 15000);

  try {
    const res = await fetch(`${API_URL}${path}`, {
      method,
      headers: {
        Accept: "application/json",
        ...(body ? { "Content-Type": "application/json" } : {}),
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
      },
      body: body ? JSON.stringify(body) : undefined,
      signal: controller.signal,
    });

    const data = await res.json().catch(() => null);

    if (!res.ok) {
      // Laravel sends {"message": "...", "errors": {"field": ["..."]}}
      const firstError = data?.errors
        ? (Object.values(data.errors)[0] as string[])[0]
        : null;
      throw new ApiError(
        firstError ?? data?.message ?? "Something went wrong.",
        res.status,
      );
    }

    return data as T;
  } catch (e) {
    if (e instanceof ApiError) throw e;
    throw new ApiError(
      "Cannot reach the server. Check your internet connection.",
      0,
    );
  } finally {
    clearTimeout(timer);
  }
}
