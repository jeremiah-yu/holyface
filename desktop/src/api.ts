const FALLBACKS = [
  'http://localhost:8089',
  'http://127.0.0.1:8089',
  'http://localhost/holyface/api/public',
];

let apiBase = window.holyface?.apiBaseUrl || FALLBACKS[0];

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

export async function probeApi(): Promise<string> {
  const candidates = [apiBase, ...FALLBACKS].filter((v, i, a) => a.indexOf(v) === i);
  for (const base of candidates) {
    try {
      const res = await fetch(`${base}/health`, { method: 'GET' });
      if (res.ok) {
        setApiBase(base);
        return base;
      }
    } catch {
      /* try next */
    }
  }
  throw new Error('Cannot reach the AMGS API. Start PHP on port 8089 or Apache.');
}

export async function api<T>(
  path: string,
  token: string | null,
  init?: RequestInit
): Promise<T> {
  const headers: Record<string, string> = { 'Content-Type': 'application/json' };
  if (token) headers.Authorization = `Bearer ${token}`;
  let res: Response;
  try {
    res = await fetch(`${apiBase}${path}`, { ...init, headers: { ...headers, ...(init?.headers as Record<string, string>) } });
  } catch {
    throw new ApiError('Offline — request queued when possible.', 0, null);
  }
  const data = await res.json().catch(() => ({}));
  if (!res.ok) {
    throw new ApiError((data as { message?: string }).message ?? 'Request failed', res.status, data);
  }
  return data as T;
}
