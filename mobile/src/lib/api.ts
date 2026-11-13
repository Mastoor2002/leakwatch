import * as SecureStore from 'expo-secure-store';
import Constants from 'expo-constants';
import { Platform } from 'react-native';
import type { Overview, Scan, Site, User } from './types';

const TOKEN_KEY = 'leakwatch.token';
const URL_KEY = 'leakwatch.apiUrl';

export class ApiError extends Error {
  constructor(
    public status: number,
    message: string,
    public errors: Record<string, string[]> = {},
  ) {
    super(message);
  }
}

// SecureStore is unavailable on web; fall back to localStorage there so the
// app still runs in a browser during development.
const storage = {
  async get(key: string): Promise<string | null> {
    if (Platform.OS === 'web') return globalThis.localStorage?.getItem(key) ?? null;
    return SecureStore.getItemAsync(key);
  },
  async set(key: string, value: string): Promise<void> {
    if (Platform.OS === 'web') return void globalThis.localStorage?.setItem(key, value);
    return SecureStore.setItemAsync(key, value);
  },
  async remove(key: string): Promise<void> {
    if (Platform.OS === 'web') return void globalThis.localStorage?.removeItem(key);
    return SecureStore.deleteItemAsync(key);
  },
};

export const defaultApiUrl: string =
  (Constants.expoConfig?.extra?.defaultApiUrl as string | undefined) ?? 'http://leakwatch.test';

export const session = {
  getToken: () => storage.get(TOKEN_KEY),
  setToken: (token: string) => storage.set(TOKEN_KEY, token),
  clearToken: () => storage.remove(TOKEN_KEY),
  getApiUrl: async () => (await storage.get(URL_KEY)) ?? defaultApiUrl,
  setApiUrl: (url: string) => storage.set(URL_KEY, normalizeUrl(url)),
};

export function normalizeUrl(input: string): string {
  let url = input.trim().replace(/\/+$/, '');
  if (!/^https?:\/\//i.test(url)) url = `https://${url}`;
  return url;
}

async function request<T>(method: string, path: string, body?: unknown, auth = true): Promise<T> {
  const base = await session.getApiUrl();
  const headers: Record<string, string> = {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  };

  if (auth) {
    const token = await session.getToken();
    if (!token) throw new ApiError(401, 'Not signed in.');
    headers.Authorization = `Bearer ${token}`;
  }

  let response: Response;

  try {
    response = await fetch(`${base}/api/v1${path}`, {
      method,
      headers,
      body: body === undefined ? undefined : JSON.stringify(body),
    });
  } catch (error) {
    throw new ApiError(0, `Could not reach ${base}. Check the server URL and that your phone can see it.`);
  }

  const text = await response.text();
  let data: any = {};

  try {
    data = text ? JSON.parse(text) : {};
  } catch {
    throw new ApiError(response.status, `Unexpected response from server (${response.status}).`);
  }

  if (!response.ok) {
    if (response.status === 401 && auth) await session.clearToken();
    throw new ApiError(response.status, data.message ?? `Request failed (${response.status}).`, data.errors ?? {});
  }

  return data as T;
}

export const api = {
  login(email: string, password: string, deviceName: string) {
    return request<{ token: string; user: User }>('POST', '/auth/token', { email, password, device_name: deviceName }, false);
  },
  logout() {
    return request<{ message: string }>('DELETE', '/auth/token');
  },
  me() {
    return request<{ user: User }>('GET', '/me');
  },
  overview(team: string) {
    return request<Overview>('GET', `/teams/${team}/overview`);
  },
  sites(team: string) {
    return request<{ sites: Site[] }>('GET', `/teams/${team}/sites`);
  },
  site(team: string, id: number) {
    return request<{ site: Site }>('GET', `/teams/${team}/sites/${id}`);
  },
  startScan(team: string, id: number) {
    return request<{ scan: Scan }>('POST', `/teams/${team}/sites/${id}/scan`);
  },
  scanStatus(team: string, id: number) {
    return request<{ scan: Scan | null; site: { score: number | null; grade: string | null; last_scanned_at: string | null; open_findings: number } }>(
      'GET',
      `/teams/${team}/sites/${id}/scan`,
    );
  },
  verify(team: string, id: number) {
    return request<{ verified: boolean; method: string | null; message: string }>('POST', `/teams/${team}/sites/${id}/verify`);
  },
};
