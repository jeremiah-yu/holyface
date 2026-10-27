import { Link } from 'expo-router';
import { useEffect, useState } from 'react';
import { ScrollView, Text, View } from 'react-native';
import { apiRequest } from '../../src/api/client';
import { useAuth } from '../../src/auth/AuthContext';
import { t } from '../../src/i18n';

type Academics = {
  general_average: number | null;
  official_card_available?: boolean;
  at_risk: { badge: string; level: string }[];
  trend: { quarter: number; average: number }[];
  subjects: {
    subject: string;
    final_rating: number | null;
    quarters: { quarter: number; grade: number | null }[];
  }[];
};

export default function GradesScreen() {
  const { token, lang, colors } = useAuth();
  const [data, setData] = useState<Academics | null>(null);

  useEffect(() => {
    apiRequest<Academics>('/students/academics', { token }).then(setData).catch(() => undefined);
  }, [token]);

  const maxH = 100;
  return (
    <ScrollView contentContainerStyle={{ padding: 16, backgroundColor: colors.bg, flexGrow: 1 }}>
      <Text style={{ color: colors.text, fontSize: 20, fontWeight: '700' }}>{t(lang, 'academics')}</Text>
      <Text style={{ color: colors.muted, marginTop: 4 }}>
        {t(lang, 'generalAvg')}: {data?.general_average ?? '—'}
      </Text>
      <Link href="/report-card" style={{ marginTop: 12, color: '#c9a227', fontWeight: '700' }}>
        {t(lang, 'officialCard')}
        {data?.official_card_available ? ' ✓' : ''} →
      </Link>
      {data?.at_risk?.length ? (
        <View style={{ marginTop: 12 }}>
          <Text style={{ color: colors.danger, fontWeight: '700' }}>{t(lang, 'atRisk')}</Text>
          {data.at_risk.map((a) => (
            <Text key={a.badge} style={{ color: colors.danger, marginTop: 4 }}>
              {a.badge}
            </Text>
          ))}
        </View>
      ) : null}
      <Text style={{ color: colors.text, fontWeight: '700', marginTop: 16 }}>Trend</Text>
      <View style={{ flexDirection: 'row', alignItems: 'flex-end', height: 120, gap: 8, marginTop: 8 }}>
        {(data?.trend ?? []).map((p) => (
          <View key={p.quarter} style={{ flex: 1, alignItems: 'center' }}>
            <View
              style={{
                height: Math.max(8, (p.average / 100) * maxH),
                width: 18,
                backgroundColor: '#c9a227',
                borderRadius: 4,
              }}
            />
            <Text style={{ color: colors.muted, fontSize: 12 }}>Q{p.quarter}</Text>
          </View>
        ))}
      </View>
      {(data?.subjects ?? []).map((s) => (
        <View key={s.subject} style={{ backgroundColor: colors.card, padding: 12, borderRadius: 10, marginTop: 10 }}>
          <Text style={{ color: colors.text, fontWeight: '700' }}>{s.subject}</Text>
          <Text style={{ color: colors.muted }}>Final: {s.final_rating ?? '—'}</Text>
          {s.quarters.map((q) => (
            <Text key={q.quarter} style={{ color: colors.text }}>
              Q{q.quarter}: {q.grade ?? '—'}
            </Text>
          ))}
        </View>
      ))}
      <Link href="/schedule" style={{ marginTop: 16, color: '#0b1f3a', fontWeight: '700' }}>
        {t(lang, 'schedule')} →
      </Link>
      <Link href="/announcements" style={{ marginTop: 8, color: '#0b1f3a', fontWeight: '700' }}>
        {t(lang, 'announcements')} →
      </Link>
    </ScrollView>
  );
}
