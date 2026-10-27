import Constants from 'expo-constants';

const extra = Constants.expoConfig?.extra as { apiBaseUrl?: string } | undefined;
export const API_BASE =
  extra?.apiBaseUrl ??
  (typeof window !== 'undefined' ? 'http://localhost:8089' : 'http://10.0.2.2:8089');

export type ApiError = { message: string; status: number };

export async function apiRequest<T>(
  path: string,
  options: RequestInit & { token?: string | null } = {}
): Promise<T> {
  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    ...(options.headers as Record<string, string>),
  };
  if (options.token) {
    headers.Authorization = `Bearer ${options.token}`;
  }
  const { token: _t, ...rest } = options;
  const res = await fetch(`${API_BASE}${path}`, { ...rest, headers });
  const data = await res.json().catch(() => ({}));
  if (!res.ok) {
    throw { message: data.message ?? 'Request failed', status: res.status } as ApiError;
  }
  return data as T;
}
