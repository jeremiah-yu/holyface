import { useEffect, useState } from 'react';
import { ScrollView, Text, View } from 'react-native';
import { apiRequest } from '../src/api/client';
import { useAuth } from '../src/auth/AuthContext';

type Row = { weekday_name: string; start_time: string; end_time: string; subject: string; room: string };

export default function ScheduleScreen() {
  const { token, colors } = useAuth();
  const [rows, setRows] = useState<Row[]>([]);
  useEffect(() => {
    apiRequest<{ schedule: Row[] }>('/students/schedule', { token })
      .then((r) => setRows(r.schedule))
      .catch(() => undefined);
  }, [token]);
  return (
    <ScrollView contentContainerStyle={{ padding: 16, backgroundColor: colors.bg }}>
      {rows.map((r, i) => (
        <View key={i} style={{ backgroundColor: colors.card, padding: 12, borderRadius: 10, marginBottom: 8 }}>
          <Text style={{ color: colors.text, fontWeight: '700' }}>
            {r.weekday_name} {r.start_time?.slice(0, 5)}–{r.end_time?.slice(0, 5)}
          </Text>
          <Text style={{ color: colors.muted }}>
            {r.subject} · {r.room}
          </Text>
        </View>
      ))}
      {!rows.length && <Text style={{ color: colors.muted }}>No schedule encoded yet.</Text>}
    </ScrollView>
  );
}
