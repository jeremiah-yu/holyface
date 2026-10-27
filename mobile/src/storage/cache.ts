import AsyncStorage from '@react-native-async-storage/async-storage';

export async function cacheSet(key: string, value: unknown): Promise<void> {
  await AsyncStorage.setItem(key, JSON.stringify({ at: Date.now(), value }));
}

export async function cacheGet<T>(key: string): Promise<{ at: number; value: T } | null> {
  const raw = await AsyncStorage.getItem(key);
  if (!raw) return null;
  try {
    return JSON.parse(raw) as { at: number; value: T };
  } catch {
    return null;
  }
}
