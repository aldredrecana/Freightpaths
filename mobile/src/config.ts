// Default server address. The address can also be changed in the app, on the
// login screen under "Server settings" (see src/server.ts).
export const DEFAULT_API_URL =
  process.env.EXPO_PUBLIC_API_URL ??
  "https://freightpaths-production.up.railway.app/api";

// Where the login token is kept on the phone.
export const TOKEN_KEY = "freightpath_token";
