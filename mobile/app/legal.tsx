import { useLocalSearchParams } from 'expo-router';
import { useEffect, useState } from 'react';
import { ScrollView, Text, View } from 'react-native';
import { apiRequest } from '../src/api/client';
import { useAuth } from '../src/auth/AuthContext';
import { POLICY_DISCLAIMER, POLICY_LAST_UPDATED, PolicyBundle, PolicyDoc } from '../src/legal/policies';

export default function LegalScreen() {
  const { colors } = useAuth();
  const { doc } = useLocalSearchParams<{ doc?: string }>();
  const [bundle, setBundle] = useState<PolicyBundle | null>(null);

  useEffect(() => {
    apiRequest<PolicyBundle>('/legal/policies').then(setBundle).catch(() => undefined);
  }, []);

  const selected: PolicyDoc | undefined = (bundle?.documents ?? []).find((d) => d.code === (doc || 'terms')) ?? bundle?.documents?.[0];

  return (
    <ScrollView contentContainerStyle={{ padding: 20, backgroundColor: colors.bg }}>
      <Text style={{ color: colors.text, fontSize: 22, fontWeight: '700' }}>{selected?.title ?? 'Policies'}</Text>
      <Text style={{ color: colors.muted, marginVertical: 8 }}>
        Version {selected?.version ?? bundle?.version_bundle} · Last updated: {selected?.last_updated ?? POLICY_LAST_UPDATED}
      </Text>
      {(bundle?.disclaimer ?? POLICY_DISCLAIMER).trim() ? (
        <Text style={{ color: colors.text, marginBottom: 12 }}>{bundle?.disclaimer ?? POLICY_DISCLAIMER}</Text>
      ) : null}
      {(selected?.sections ?? []).map((s) => (
        <View key={s.id} style={{ marginBottom: 14 }}>
          <Text style={{ color: colors.text, fontWeight: '700' }}>
            {s.id} {s.title}
          </Text>
          <Text style={{ color: colors.muted }}>{s.body}</Text>
        </View>
      ))}
    </ScrollView>
  );
}
