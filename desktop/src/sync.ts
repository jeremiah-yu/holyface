import { api, ApiError } from './api';
import type { QueueItem, SecureCache, SyncStatus } from './types';

const MAX_QUEUE = 200;

export async function loadCache(): Promise<SecureCache> {
  if (!window.holyface?.secureLoad) {
    const raw = sessionStorage.getItem('amgs_secure_dev');
    return raw ? (JSON.parse(raw) as SecureCache) : {};
  }
  const result = await window.holyface.secureLoad();
  return result.data ?? {};
}

export async function saveCache(cache: SecureCache): Promise<void> {
  if (!window.holyface?.secureSave) {
    sessionStorage.setItem('amgs_secure_dev', JSON.stringify(cache));
    return;
  }
  await window.holyface.secureSave(cache);
}

export function enqueue(cache: SecureCache, item: Omit<QueueItem, 'id' | 'createdAt'>): SecureCache {
  const queue = [...(cache.queue ?? [])];
  if (queue.length >= MAX_QUEUE) queue.shift();
  queue.push({
    ...item,
    id: `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
    createdAt: new Date().toISOString(),
  });
  return { ...cache, queue };
}

export async function flushQueue(
  cache: SecureCache,
  token: string,
  onConflict: (payload: unknown) => void
): Promise<{ cache: SecureCache; status: SyncStatus }> {
  const remaining: QueueItem[] = [];
  let status: SyncStatus = 'online';
  for (const item of cache.queue ?? []) {
    try {
      await api(item.path, token, { method: item.method, body: JSON.stringify(item.body) });
    } catch (err) {
      if (err instanceof ApiError && err.status === 0) {
        remaining.push(item, ...(cache.queue ?? []).slice((cache.queue ?? []).indexOf(item) + 1));
        return { cache: { ...cache, queue: remaining }, status: 'offline' };
      }
      if (err instanceof ApiError && err.status === 409) {
        onConflict(err.payload);
        status = 'conflict';
        continue;
      }
      remaining.push(item);
      status = 'error';
    }
  }
  return { cache: { ...cache, queue: remaining }, status };
}
