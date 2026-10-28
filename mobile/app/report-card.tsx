import { useEffect, useState } from 'react';
import { ScrollView, Text, View } from 'react-native';
import { apiRequest } from '../src/api/client';
import { useAuth } from '../src/auth/AuthContext';
import { t } from '../src/i18n';
import { cacheGet, cacheSet } from '../src/storage/cache';

type SubjectRow = {
  title: string;
  q1: number | null;
  q2: number | null;
  sem1: number | null;
  q3: number | null;
  q4: number | null;
  sem2: number | null;
  final_rating: number | null;
  action_taken: string | null;
};

type Card = {
  card_number: string;
  issued_at: string;
  general_average: number;
  remarks: string;
  school: { name: string; address: string; logo: string };
  student: {
    full_name: string;
    student_number: string;
    strand_code: string;
    grade_level: number;
    section_code: string;
    school_year: string;
  };
  subjects: SubjectRow[];
};

type Payload = {
  available: boolean;
  card: Card | null;
  progress: { total: number; submitted: number; missing: string[] };
  message: string;
};

export default function ReportCardScreen() {
  const { token, colors, lang } = useAuth();
  const [data, setData] = useState<Payload | null>(null);

  useEffect(() => {
    let cancelled = false;
    (async () => {
      const cached = await cacheGet<Payload>('report_card');
      if (cached && !cancelled) {
        setData(cached.value);
      }
      try {
        const fresh = await apiRequest<Payload>('/students/report-card', { token, timeoutMs: 8000 });
        if (!cancelled) {
          setData(fresh);
          await cacheSet('report_card', fresh);
        }
      } catch {
        if (!cancelled && !cached) {
          setData({
            available: false,
            card: null,
            progress: { total: 0, submitted: 0, missing: [] },
            message: 'Could not load the grading card. Try again.',
          });
        }
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [token]);

  const card = data?.card;
  if (!data) {
    return (
      <View style={{ flex: 1, backgroundColor: colors.bg, padding: 16 }}>
        <Text style={{ color: colors.muted }}>Loading…</Text>
      </View>
    );
  }

  if (!data.available || !card) {
    return (
      <ScrollView contentContainerStyle={{ padding: 16, backgroundColor: colors.bg, flexGrow: 1 }}>
        <Text style={{ color: colors.text, fontSize: 22, fontWeight: '700' }}>{t(lang, 'officialCard')}</Text>
        <Text style={{ color: colors.muted, marginTop: 10 }}>{t(lang, 'officialCardPending')}</Text>
        <Text style={{ color: colors.text, marginTop: 14, fontWeight: '700' }}>
          {data.progress.submitted}/{data.progress.total} {t(lang, 'submittedOf')}
        </Text>
        {(data.progress.missing ?? []).map((name) => (
          <Text key={name} style={{ color: colors.muted, marginTop: 6 }}>
            • {name}
          </Text>
        ))}
      </ScrollView>
    );
  }

  return (
    <ScrollView contentContainerStyle={{ padding: 16, backgroundColor: colors.bg, flexGrow: 1 }}>
      <View style={{ backgroundColor: colors.card, borderRadius: 12, padding: 16, borderWidth: 2, borderColor: '#c9a227' }}>
        <View style={{ alignItems: 'center', marginBottom: 12 }}>
          <View style={{ width: 64, height: 64, borderRadius: 32, backgroundColor: '#fff', alignItems: 'center', justifyContent: 'center', borderWidth: 2, borderColor: '#c9a227' }}>
            <Text style={{ color: '#0b1f3a', fontWeight: '800', fontSize: 16 }}>HF</Text>
          </View>
          <Text style={{ color: colors.text, fontWeight: '800', textAlign: 'center', marginTop: 8 }}>{card.school.name}</Text>
          <Text style={{ color: '#c9a227', fontWeight: '700', marginTop: 4 }}>OFFICIAL GRADING CARD</Text>
          <Text style={{ color: colors.muted, fontSize: 12 }}>{card.card_number}</Text>
        </View>
        <Text style={{ color: colors.text, fontWeight: '700' }}>{card.student.full_name}</Text>
        <Text style={{ color: colors.muted }}>Student number: {card.student.student_number}</Text>
        <Text style={{ color: colors.muted }}>
          G{card.student.grade_level} {card.student.strand_code}-{card.student.section_code} · {card.student.school_year}
        </Text>

        <View style={{ marginTop: 14, borderTopWidth: 1, borderColor: colors.border, paddingTop: 8 }}>
          <Row head colors={colors} cells={['Subject', 'Q1', 'Q2', 'Final']} />
          {card.subjects.map((s) => (
            <Row
              key={s.title}
              colors={colors}
              cells={[s.title, fmt(s.q1), fmt(s.q2), fmt(s.final_rating)]}
            />
          ))}
        </View>

        <Text style={{ color: colors.text, fontWeight: '800', marginTop: 16, fontSize: 16 }}>
          {t(lang, 'generalAvg')}: {card.general_average}
        </Text>
        <Text style={{ color: card.remarks === 'PASSED' ? colors.ok : colors.danger, fontWeight: '700' }}>{card.remarks}</Text>
        <Text style={{ color: colors.muted, fontSize: 12, marginTop: 10 }}>
          Issued {card.issued_at.slice(0, 10)}. Official grades come from the Registrar’s records.
        </Text>
      </View>
    </ScrollView>
  );
}

function fmt(v: number | null): string {
  return v == null ? '—' : String(v);
}

function Row({
  cells,
  colors,
  head,
}: {
  cells: string[];
  colors: { text: string; muted: string };
  head?: boolean;
}) {
  return (
    <View style={{ flexDirection: 'row', paddingVertical: 6, gap: 6 }}>
      <Text style={{ flex: 2.2, color: colors.text, fontWeight: head ? '700' : '500', fontSize: 12 }}>{cells[0]}</Text>
      <Text style={{ flex: 0.6, color: colors.text, fontWeight: head ? '700' : '500', fontSize: 12, textAlign: 'center' }}>{cells[1]}</Text>
      <Text style={{ flex: 0.6, color: colors.text, fontWeight: head ? '700' : '500', fontSize: 12, textAlign: 'center' }}>{cells[2]}</Text>
      <Text style={{ flex: 0.7, color: colors.text, fontWeight: head ? '700' : '500', fontSize: 12, textAlign: 'center' }}>{cells[3]}</Text>
    </View>
  );
}
