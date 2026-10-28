import Constants from 'expo-constants';

const extra = Constants.expoConfig?.extra as { apiBaseUrl?: string } | undefined;
export const API_BASE =
  extra?.apiBaseUrl ??
  (typeof window !== 'undefined' ? 'http://localhost:8089' : 'http://10.0.2.2:8089');

export type ApiError = { message: string; status: number };

export async function apiRequest<T>(
  path: string,
  options: RequestInit & { token?: string | null; timeoutMs?: number } = {}
): Promise<T> {
  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    ...(options.headers as Record<string, string>),
  };
  if (options.token) {
    headers.Authorization = `Bearer ${options.token}`;
  }
  const { token: _t, timeoutMs, ...rest } = options;
  const controller = new AbortController();
  const ms =
    timeoutMs ??
    (path.includes('extract-notes') ? 60000 : path.startsWith('/ai/chat') ? 10000 : path.startsWith('/ai/') ? 20000 : 8000);
  const timer = setTimeout(() => controller.abort(), ms);
  try {
    const res = await fetch(`${API_BASE}${path}`, { ...rest, headers, signal: controller.signal });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
      throw { message: data.message ?? 'Request failed', status: res.status } as ApiError;
    }
    return data as T;
  } catch (e: unknown) {
    if (e instanceof Error && e.name === 'AbortError') {
      throw { message: 'That took too long. Try again.', status: 408 } as ApiError;
    }
    if ((e as ApiError).status) {
      throw e;
    }
    throw { message: (e as Error).message ?? 'Request failed', status: 0 } as ApiError;
  } finally {
    clearTimeout(timer);
  }
}
