const LIVE_API = 'https://holyface.infinityfreeapp.com/api/public';
const FALLBACKS = [
  LIVE_API,
  'http://localhost:8089',
  'http://127.0.0.1:8089',
  'http://localhost/holyface/api/public',
];

function pageIsLocal(): boolean {
  const host = window.location.hostname;
  return host === 'localhost' || host === '127.0.0.1' || host === '';
}

function defaultApiBase(): string {
  if (window.holyface?.apiBaseUrl) {
    return window.holyface.apiBaseUrl.replace(/\/$/, '');
  }
  if (pageIsLocal()) {
    return LIVE_API;
  }
  return `${window.location.origin}/api/public`;
}

let apiBase = defaultApiBase();

export function getApiBase(): string {
  return apiBase;
}

export function setApiBase(url: string): void {
  apiBase = url.replace(/\/$/, '');
}

export class ApiError extends Error {
  status: number;
  payload: unknown;
  constructor(message: string, status: number, payload: unknown) {
    super(message);
    this.status = status;
    this.payload = payload;
  }
}

function looksLikeHealth(data: unknown): boolean {
  if (!data || typeof data !== 'object') {
    return false;
  }
  const row = data as { success?: boolean; service?: string };
  return row.success === true && typeof row.service === 'string';
}

async function transport(
  url: string,
  init?: RequestInit
): Promise<{ ok: boolean; status: number; data: unknown }> {
  if (window.holyface?.amgsFetch) {
    const headers = (init?.headers || {}) as Record<string, string>;
    const body = typeof init?.body === 'string' ? init.body : init?.body ? String(init.body) : null;
    return window.holyface.amgsFetch(url, {
      method: (init?.method as string) || 'GET',
      headers,
      body,
    });
  }
  const res = await fetch(url, init);
  const data = await res.json().catch(() => ({}));
  return { ok: res.ok, status: res.status, data };
}

export async function probeApi(): Promise<string> {
  const extras = pageIsLocal() || window.holyface ? FALLBACKS : [];
  const candidates = [apiBase, ...extras].filter((v, i, a) => a.indexOf(v) === i);
  let lastMessage = 'Cannot reach the Holy Face AMGS server. Check your internet connection.';
  for (const base of candidates) {
    try {
      const ctrl = new AbortController();
      const timer = window.setTimeout(() => ctrl.abort(), 25000);
      const useIpc = Boolean(window.holyface?.amgsFetch);
      const result = useIpc
        ? await transport(`${base}/health`, { method: 'GET' })
        : await fetch(`${base}/health`, { method: 'GET', signal: ctrl.signal })
            .then(async (res) => ({
              ok: res.ok,
              status: res.status,
              data: await res.json().catch(() => ({})),
            }));
      window.clearTimeout(timer);
      if (result.ok && looksLikeHealth(result.data)) {
        setApiBase(base);
        return base;
      }
    } catch (err) {
      lastMessage = (err as Error).message || lastMessage;
    }
  }
  throw new Error(lastMessage);
}

export async function api<T>(
  path: string,
  token: string | null,
  init?: RequestInit
): Promise<T> {
  const headers: Record<string, string> = { 'Content-Type': 'application/json' };
  if (token) headers.Authorization = `Bearer ${token}`;
  let result: { ok: boolean; status: number; data: unknown };
  try {
    result = await transport(`${apiBase}${path}`, {
      ...init,
      headers: { ...headers, ...(init?.headers as Record<string, string>) },
    });
  } catch {
    throw new ApiError('Offline — request queued when possible.', 0, null);
  }
  const data = result.data as { message?: string; user?: unknown };
  if (!result.ok) {
    throw new ApiError(data?.message ?? 'Request failed', result.status, data);
  }
  if (path === '/auth/login' && !(data && typeof data === 'object' && 'user' in data && data.user)) {
    throw new ApiError('Cannot reach the Holy Face AMGS server. Check your internet connection.', 0, data);
  }
  return data as T;
}
