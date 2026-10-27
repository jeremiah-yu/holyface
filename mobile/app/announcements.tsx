import { useEffect, useState } from 'react';
import { ScrollView, Text, View } from 'react-native';
import { apiRequest } from '../src/api/client';
import { useAuth } from '../src/auth/AuthContext';

type A = { id: number; title: string; body: string; published_at: string };

export default function AnnouncementsScreen() {
  const { token, colors } = useAuth();
  const [items, setItems] = useState<A[]>([]);
  useEffect(() => {
    apiRequest<{ announcements: A[] }>('/students/announcements', { token })
      .then((r) => setItems(r.announcements))
      .catch(() => undefined);
  }, [token]);
  return (
    <ScrollView contentContainerStyle={{ padding: 16, backgroundColor: colors.bg }}>
      {items.map((a) => (
        <View key={a.id} style={{ backgroundColor: colors.card, padding: 12, borderRadius: 10, marginBottom: 10 }}>
          <Text style={{ color: colors.muted, fontSize: 12 }}>{a.published_at}</Text>
          <Text style={{ color: colors.text, fontWeight: '700' }}>{a.title}</Text>
          <Text style={{ color: colors.text }}>{a.body}</Text>
        </View>
      ))}
    </ScrollView>
  );
}
