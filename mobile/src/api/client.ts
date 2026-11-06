import Constants from 'expo-constants';
import { isChallengeHtml, solveChallenge } from './infinityfreeCookie';

const extra = Constants.expoConfig?.extra as { apiBaseUrl?: string } | undefined;
export const API_BASE =
  extra?.apiBaseUrl ??
  (typeof window !== 'undefined' ? 'http://localhost:8089' : 'http://10.0.2.2:8089');

let hostCookie = '';
let hostReady = false;

export function setHostCookie(cookie: string): void {
  hostCookie = cookie;
  hostReady = Boolean(cookie);
}

export type ApiError = { message: string; status: number };

type HttpResult = { ok: boolean; status: number; text: string };

function parseJson(text: string): Record<string, unknown> {
  try {
    const data = JSON.parse(text) as unknown;
    return data && typeof data === 'object' ? (data as Record<string, unknown>) : {};
  } catch {
    return {};
  }
}

function nativeHttp(
  url: string,
  opts: { method: string; headers: Record<string, string>; body?: BodyInit | null; timeoutMs: number }
): Promise<HttpResult> {
  return new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest();
    xhr.timeout = opts.timeoutMs;
    xhr.onload = () => {
      const status = xhr.status || 0;
      resolve({
        status,
        ok: status >= 200 && status < 300,
        text: String(xhr.responseText || ''),
      });
    };
    xhr.onerror = () => reject(new Error('Network error'));
    xhr.ontimeout = () => {
      const err = new Error('Aborted');
      err.name = 'AbortError';
      reject(err);
    };
    xhr.open(opts.method, url, true);
    Object.entries(opts.headers).forEach(([key, value]) => {
      if (value) {
        xhr.setRequestHeader(key, value);
      }
    });
    const body = opts.body == null || typeof opts.body === 'string' ? opts.body : String(opts.body);
    xhr.send(body ?? null);
  });
}

async function nativeFetch(path: string, options: RequestInit & { token?: string | null; timeoutMs?: number }) {
  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    Accept: 'application/json',
    ...(options.headers as Record<string, string>),
  };
  if (hostCookie) {
    headers.Cookie = hostCookie.includes('=') ? hostCookie : `__test=${hostCookie}`;
  }
  if (options.token) {
    headers.Authorization = `Bearer ${options.token}`;
  }
  const timeoutMs =
    options.timeoutMs ??
    (path.includes('extract-notes') ? 60000 : path.startsWith('/ai/chat') ? 20000 : path.startsWith('/ai/') ? 25000 : 25000);
  return nativeHttp(`${API_BASE}${path}`, {
    method: (options.method as string) || 'GET',
    headers,
    body: options.body,
    timeoutMs,
  });
}

async function unlockHost(): Promise<void> {
  const round = await nativeFetch('/health', { method: 'GET', timeoutMs: 25000 });
  if (!isChallengeHtml(round.text)) {
    hostReady = true;
    return;
  }
  const token = solveChallenge(round.text);
  if (!token) {
    throw { message: 'Cannot reach Holy Face AMGS. Check your internet.', status: 0 } as ApiError;
  }
  hostCookie = `__test=${token}`;
  const retry = await nativeFetch('/health?i=1', { method: 'GET', timeoutMs: 25000 });
  if (isChallengeHtml(retry.text) || !parseJson(retry.text).success) {
    hostCookie = '';
    throw { message: 'Cannot reach Holy Face AMGS. Check your internet.', status: 0 } as ApiError;
  }
  hostReady = true;
}

let unlocking: Promise<void> | null = null;

async function ensureHost(): Promise<void> {
  if (hostReady) {
    return;
  }
  if (!unlocking) {
    unlocking = unlockHost().finally(() => {
      unlocking = null;
    });
  }
  await unlocking;
}

export async function apiRequest<T>(
  path: string,
  options: RequestInit & { token?: string | null; timeoutMs?: number } = {}
): Promise<T> {
  try {
    await ensureHost();
    let round = await nativeFetch(path, options);
    if (isChallengeHtml(round.text)) {
      hostCookie = '';
      hostReady = false;
      await ensureHost();
      round = await nativeFetch(path, options);
    }
    const data = parseJson(round.text) as T & { message?: string };
    if (isChallengeHtml(round.text) || (round.ok && round.text.trim().startsWith('<') && !(data as { success?: boolean }).success)) {
      throw { message: 'Cannot reach Holy Face AMGS. Check your internet.', status: 0 } as ApiError;
    }
    if (!round.ok) {
      throw { message: data.message ?? 'Request failed', status: round.status } as ApiError;
    }
    return data as T;
  } catch (e: unknown) {
    if (e instanceof Error && e.name === 'AbortError') {
      throw { message: 'That took too long. Try again.', status: 408 } as ApiError;
    }
    if ((e as ApiError).status !== undefined) {
      throw e;
    }
    throw { message: (e as Error).message ?? 'Request failed', status: 0 } as ApiError;
  }
}
