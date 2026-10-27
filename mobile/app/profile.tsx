import { useEffect, useState } from 'react';
import { Text, View } from 'react-native';
import { apiRequest } from '../src/api/client';
import { useAuth } from '../src/auth/AuthContext';

export default function ProfileScreen() {
  const { token, colors } = useAuth();
  const [p, setP] = useState<Record<string, string> | null>(null);
  useEffect(() => {
    apiRequest<{ profile: Record<string, string> }>('/students/profile', { token })
      .then((r) => setP(r.profile))
      .catch(() => undefined);
  }, [token]);
  return (
    <View style={{ flex: 1, backgroundColor: colors.bg, padding: 16 }}>
      {p &&
        Object.entries(p).map(([k, v]) => (
          <Text key={k} style={{ color: colors.text, marginTop: 8 }}>
            <Text style={{ fontWeight: '700' }}>{k}: </Text>
            {String(v ?? '—')}
          </Text>
        ))}
    </View>
  );
}
