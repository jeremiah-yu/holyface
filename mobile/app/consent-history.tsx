import { useEffect, useState } from 'react';
import { ScrollView, Text } from 'react-native';
import { apiRequest } from '../src/api/client';
import { useAuth } from '../src/auth/AuthContext';

type Log = { consent_type: string; granted: number; version: string; created_at: string };

export default function ConsentHistoryScreen() {
  const { token, colors } = useAuth();
  const [logs, setLogs] = useState<Log[]>([]);
  useEffect(() => {
    apiRequest<{ history: Log[] }>('/students/consent', { token })
      .then((r) => setLogs(r.history))
      .catch(() => undefined);
  }, [token]);
  return (
    <ScrollView contentContainerStyle={{ padding: 16, backgroundColor: colors.bg }}>
      {logs.map((l, i) => (
        <Text key={i} style={{ color: colors.text, marginBottom: 10 }}>
          {l.created_at} · {l.consent_type} · {l.granted ? 'granted' : 'denied'} · v{l.version}
        </Text>
      ))}
    </ScrollView>
  );
}
