import { useEffect, useMemo, useState } from 'react';
import { Image, ScrollView, StyleSheet, Text, View } from 'react-native';
import { apiRequest } from '../src/api/client';
import { useAuth } from '../src/auth/AuthContext';
import { t } from '../src/i18n';
import { cacheGet, cacheSet } from '../src/storage/cache';

const DEPED_SEAL = { uri: 'https://holyface.infinityfreeapp.com/assets/img/deped-seal.png' };
const SCHOOL_SEAL = { uri: 'https://holyface.infinityfreeapp.com/assets/img/hf-seal.png' };

type Period = { id: number; code: string; label: string; grade?: number | null };
type SubjectRow = {
  title: string;
  category_code?: string;
  category_name?: string;
  final_rating: number | null;
  remarks?: string | null;
  action_taken: string | null;
  periods?: Period[];
  q1?: number | null;
  q2?: number | null;
  q3?: number | null;
  q4?: number | null;
};

type CoreValue = { value: string; statement: string; rating?: string | null };

type Card = {
  card_number: string;
  issued_at: string;
  general_average: number;
  remarks: string;
  honor?: string | null;
  section_rank?: number | null;
  school: {
    name: string;
    address: string;
    logo?: string;
    region?: string;
    division?: string;
    principal_name?: string;
  };
  student: {
    full_name: string;
    student_number: string;
    strand_code: string;
    strand_name?: string;
    track_strand?: string;
    grade_level: number;
    section_code: string;
    school_year: string;
    date_of_birth?: string | null;
    age?: number | null;
    adviser_name?: string | null;
  };
  calendar?: { periods: { id: number; code: string; label: string }[] };
  subjects: SubjectRow[];
  core_values?: CoreValue[];
  attendance?: {
    months: string[];
    school_days: (number | null)[];
    days_present: (number | null)[];
    days_absent: (number | null)[];
  };
  marking_key?: Record<string, string>;
};

type Payload = {
  available: boolean;
  card: Card | null;
  progress: { total: number; submitted: number; missing: string[]; generated?: boolean; released?: boolean };
  message: string;
  status?: string;
};

const DEFAULT_CORE: CoreValue[] = [
  { value: 'Maka-Diyos', statement: "Expresses one's spiritual beliefs while respecting the spiritual beliefs of others", rating: null },
  { value: 'Makatao', statement: 'Shows sensitivity to individual differences and people in need', rating: null },
  { value: 'Makakalikasan', statement: 'Cares for the environment and uses resources wisely and responsibly', rating: null },
  { value: 'Makabansa', statement: 'Demonstrate appropriate behavior in carrying out activities in the school, community, and country', rating: null },
];

const DEFAULT_MARKING: Record<string, string> = {
  AO: 'Always Observed',
  SO: 'Sometimes Observed',
  RO: 'Rarely Observed',
  NO: 'Not Observed',
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
  const groups = useMemo(() => groupSubjects(card?.subjects ?? []), [card?.subjects]);

  if (!data) {
    return (
      <View style={[styles.screen, { backgroundColor: colors.bg }]}>
        <Text style={{ color: colors.muted }}>Loading…</Text>
      </View>
    );
  }

  if (!data.available || !card) {
    return (
      <ScrollView contentContainerStyle={[styles.screen, { backgroundColor: colors.bg, flexGrow: 1 }]}>
        <Text style={{ color: colors.text, fontSize: 22, fontWeight: '700' }}>{t(lang, 'officialCard')}</Text>
        <Text style={{ color: colors.muted, marginTop: 10 }}>
          {data.status === 'generated' ? t(lang, 'officialCardWaitingRelease') : data.message || t(lang, 'officialCardPending')}
        </Text>
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

  const periods = card.calendar?.periods ?? [];
  const gradeCols = periods.length ? periods : [{ id: 1, code: 'T1', label: 'Term 1 Final Grade' }];
  const track =
    card.student.track_strand ||
    `ACADEMIC - ${String(card.student.strand_name || card.student.strand_code).toUpperCase()}`;
  const coreValues = card.core_values?.length ? card.core_values : DEFAULT_CORE;
  const marking = card.marking_key ?? DEFAULT_MARKING;
  const attendance = card.attendance ?? {
    months: ['Jun', 'Jul', 'Aug', 'Sep', 'Oct'],
    school_days: [null, null, null, null, null],
    days_present: [null, null, null, null, null],
    days_absent: [null, null, null, null, null],
  };
  const principal = card.school.principal_name || 'DR. MA BELLA T. TOLENTINO';
  const adviser = card.student.adviser_name || '—';
  const region = card.school.region || 'REGION IV-A CALABARZON';
  const division = card.school.division || 'DIVISION OF RIZAL';

  return (
    <ScrollView contentContainerStyle={styles.scroll} horizontal={false}>
      <ScrollView horizontal showsHorizontalScrollIndicator contentContainerStyle={styles.hScroll}>
        <View style={styles.paper}>
          <View style={styles.headerRow}>
            <Image source={DEPED_SEAL} style={styles.seal} resizeMode="contain" />
            <View style={styles.headerCenter}>
              <Text style={styles.depedLine}>Republic of the Philippines</Text>
              <Text style={styles.depedLine}>Department of Education</Text>
              <Text style={styles.depedLine}>{region}</Text>
              <Text style={styles.depedLine}>{division}</Text>
              <Text style={styles.schoolName}>{card.school.name}</Text>
              <Text style={styles.schoolAddress}>{card.school.address}</Text>
            </View>
            <Image source={SCHOOL_SEAL} style={styles.seal} resizeMode="contain" />
          </View>

          <View style={styles.titleBanner}>
            <Text style={styles.titleBannerText}>PROGRESS REPORT CARD</Text>
          </View>

          <View style={styles.infoGrid}>
            <InfoRow label="NAME" value={card.student.full_name} wide />
            <View style={styles.infoSplit}>
              <InfoRow label="AGE" value={card.student.age != null ? String(card.student.age) : '—'} flex={1} />
              <InfoRow label="SEX" value="—" flex={1} />
            </View>
            <InfoRow label="GRADE & SECTION" value={`Grade ${card.student.grade_level} - ${card.student.section_code}`} wide />
            <InfoRow label="LRN" value={card.student.student_number} wide />
            <InfoRow label="TRACK / STRAND" value={track} wide />
            <InfoRow label="SCHOOL YEAR" value={card.student.school_year} wide />
          </View>

          <Text style={styles.letter}>
            Dear Parent,{'\n\n'}
            This report card shows the ability and progress your child has made in the different learning areas as well as his/her core values.{'\n\n'}
            The school looks forward to your continuous support to ensure your child's total development.
          </Text>

          <View style={styles.signRow}>
            <View style={styles.signBlock}>
              <Text style={styles.signName}>{adviser}</Text>
              <Text style={styles.signRole}>Adviser</Text>
            </View>
            <View style={styles.signBlock}>
              <Text style={styles.signName}>{principal}</Text>
              <Text style={styles.signRole}>Principal</Text>
            </View>
          </View>

          <Text style={styles.sectionHead}>REPORT ON LEARNING PROGRESS AND ACHIEVEMENT</Text>
          <View style={styles.table}>
            <View style={[styles.tr, styles.trHead]}>
              <Text style={[styles.th, styles.colLearn]}>Learning Areas</Text>
              {gradeCols.map((p) => (
                <Text key={p.code} style={[styles.th, styles.colGrade]}>
                  {shortPeriodLabel(p.label, p.code)}
                </Text>
              ))}
              <Text style={[styles.th, styles.colRemarks]}>Remarks</Text>
            </View>

            {groups.map((group) => (
              <View key={group.code}>
                <View style={styles.trGroup}>
                  <Text style={styles.groupLabel}>{group.name}</Text>
                </View>
                {group.items.map((s) => (
                  <View key={s.title} style={styles.tr}>
                    <Text style={[styles.td, styles.colLearn]}>{s.title}</Text>
                    {gradeCols.map((p) => (
                      <Text key={`${s.title}-${p.code}`} style={[styles.td, styles.colGrade]}>
                        {fmt(gradeFor(s, p))}
                      </Text>
                    ))}
                    <Text style={[styles.td, styles.colRemarks]}>{remarkFor(s)}</Text>
                  </View>
                ))}
              </View>
            ))}

            <View style={[styles.tr, styles.trAvg]}>
              <Text style={[styles.td, styles.colLearn, styles.bold]}>General Average</Text>
              {gradeCols.map((p, i) => (
                <Text key={`avg-${p.code}`} style={[styles.td, styles.colGrade, styles.bold]}>
                  {i === gradeCols.length - 1 ? fmt(card.general_average) : '—'}
                </Text>
              ))}
              <Text style={[styles.td, styles.colRemarks, styles.bold]}>{card.remarks}</Text>
            </View>
          </View>

          {card.honor ? <Text style={styles.honor}>Honor: {card.honor}</Text> : null}

          <Text style={[styles.sectionHead, { marginTop: 16 }]}>REPORT ON LEARNER'S OBSERVED VALUES</Text>
          <View style={styles.twoCol}>
            <View style={[styles.table, { flex: 1.4 }]}>
              <View style={[styles.tr, styles.trHead]}>
                <Text style={[styles.th, { flex: 1.1 }]}>Core Values</Text>
                <Text style={[styles.th, { flex: 2.2 }]}>Behavior Statements</Text>
                <Text style={[styles.th, styles.colGrade]}>Q / T</Text>
              </View>
              {coreValues.map((cv) => (
                <View key={cv.value} style={styles.tr}>
                  <Text style={[styles.td, { flex: 1.1, fontWeight: '700' }]}>{cv.value}</Text>
                  <Text style={[styles.td, { flex: 2.2, fontSize: 9 }]}>{cv.statement}</Text>
                  <Text style={[styles.td, styles.colGrade]}>{cv.rating || '—'}</Text>
                </View>
              ))}
            </View>
            <View style={styles.markingBox}>
              <Text style={styles.markingTitle}>Marking</Text>
              {Object.entries(marking).map(([k, v]) => (
                <Text key={k} style={styles.markingLine}>
                  {k} – {v}
                </Text>
              ))}
            </View>
          </View>

          <Text style={[styles.sectionHead, { marginTop: 16 }]}>REPORT ON ATTENDANCE</Text>
          <View style={styles.table}>
            <View style={[styles.tr, styles.trHead]}>
              <Text style={[styles.th, styles.colLearn]}> </Text>
              {attendance.months.map((m) => (
                <Text key={m} style={[styles.th, styles.colMonth]}>
                  {m}
                </Text>
              ))}
              <Text style={[styles.th, styles.colMonth]}>Total</Text>
            </View>
            {(
              [
                ['No. of School Days', attendance.school_days],
                ['No. of Days Present', attendance.days_present],
                ['No. of Days Absent', attendance.days_absent],
              ] as const
            ).map(([label, vals]) => (
              <View key={label} style={styles.tr}>
                <Text style={[styles.td, styles.colLearn]}>{label}</Text>
                {vals.map((v, i) => (
                  <Text key={`${label}-${i}`} style={[styles.td, styles.colMonth]}>
                    {fmt(v)}
                  </Text>
                ))}
                <Text style={[styles.td, styles.colMonth]}>{sumOrDash(vals)}</Text>
              </View>
            ))}
          </View>

          <View style={styles.parentAck}>
            <Text style={styles.parentTitle}>Parent / Guardian's Signature</Text>
            <View style={styles.parentLines}>
              <Text style={styles.parentLine}>1st ________________</Text>
              <Text style={styles.parentLine}>2nd ________________</Text>
              <Text style={styles.parentLine}>3rd ________________</Text>
              <Text style={styles.parentLine}>4th ________________</Text>
            </View>
          </View>

          <Text style={styles.footerNote}>
            Please return this Progress Report Card duly signed by the parent / guardian. This card is not valid without the school seal.
          </Text>
          <Text style={styles.meta}>
            {card.card_number} · Released {card.issued_at.slice(0, 10)}
            {card.section_rank ? ` · Rank ${card.section_rank}` : ''}
          </Text>
        </View>
      </ScrollView>
    </ScrollView>
  );
}

function groupSubjects(subjects: SubjectRow[]) {
  const order = ['core', 'applied', 'specialized'];
  const map = new Map<string, { code: string; name: string; items: SubjectRow[] }>();
  for (const s of subjects) {
    const code = (s.category_code || 'core').toLowerCase();
    const name = s.category_name || categoryLabel(code);
    if (!map.has(code)) {
      map.set(code, { code, name, items: [] });
    }
    map.get(code)!.items.push(s);
  }
  return order
    .filter((c) => map.has(c))
    .map((c) => map.get(c)!)
    .concat([...map.values()].filter((g) => !order.includes(g.code)));
}

function categoryLabel(code: string): string {
  if (code === 'applied') return 'Applied Subjects';
  if (code === 'specialized') return 'Specialized Subjects';
  return 'Core Subjects';
}

function gradeFor(s: SubjectRow, p: { id: number; code: string }): number | null {
  const fromPeriods = s.periods?.find((x) => x.code === p.code || x.id === p.id);
  if (fromPeriods) return fromPeriods.grade ?? null;
  const key = `q${p.id}` as keyof SubjectRow;
  const v = s[key];
  return typeof v === 'number' ? v : null;
}

function remarkFor(s: SubjectRow): string {
  if (s.remarks) return s.remarks;
  if (s.final_rating == null) return '—';
  return s.final_rating >= 75 ? 'PASSED' : 'FAILED';
}

function shortPeriodLabel(label: string, code: string): string {
  if (/term/i.test(label)) return label.replace(/Final Grade/i, '').trim() || code;
  return label.length > 18 ? code : label;
}

function fmt(v: number | null | undefined): string {
  return v == null ? '—' : String(v);
}

function sumOrDash(vals: (number | null)[]): string {
  const nums = vals.filter((v): v is number => v != null);
  return nums.length ? String(nums.reduce((a, b) => a + b, 0)) : '—';
}

function InfoRow({
  label,
  value,
  wide,
  flex,
}: {
  label: string;
  value: string;
  wide?: boolean;
  flex?: number;
}) {
  return (
    <View style={[styles.infoRow, wide ? styles.infoWide : null, flex != null ? { flex } : null]}>
      <Text style={styles.infoLabel}>{label}</Text>
      <Text style={styles.infoValue}>{value}</Text>
    </View>
  );
}

const PAPER_W = 720;

const styles = StyleSheet.create({
  screen: { flex: 1, padding: 16 },
  scroll: { paddingVertical: 12, paddingHorizontal: 8, backgroundColor: '#d8d4cc', flexGrow: 1 },
  hScroll: { paddingBottom: 24 },
  paper: {
    width: PAPER_W,
    backgroundColor: '#fffef8',
    borderWidth: 1,
    borderColor: '#1a1a1a',
    padding: 16,
  },
  headerRow: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  seal: { width: 78, height: 78 },
  headerCenter: { flex: 1, alignItems: 'center' },
  depedLine: { fontSize: 11, color: '#111', textAlign: 'center', fontWeight: '600' },
  schoolName: {
    marginTop: 4,
    fontSize: 13,
    fontWeight: '800',
    color: '#111',
    textAlign: 'center',
    letterSpacing: 0.3,
  },
  schoolAddress: { fontSize: 10, color: '#222', textAlign: 'center', marginTop: 2 },
  titleBanner: {
    marginTop: 12,
    backgroundColor: '#0b2f6b',
    paddingVertical: 8,
    alignItems: 'center',
  },
  titleBannerText: { color: '#fff', fontWeight: '800', fontSize: 16, letterSpacing: 1.2 },
  infoGrid: { marginTop: 12, borderWidth: 1, borderColor: '#222' },
  infoSplit: { flexDirection: 'row' },
  infoRow: {
    flexDirection: 'row',
    borderBottomWidth: 1,
    borderColor: '#222',
    minHeight: 28,
    alignItems: 'stretch',
  },
  infoWide: { width: '100%' },
  infoLabel: {
    width: 140,
    backgroundColor: '#eef2f8',
    paddingHorizontal: 8,
    paddingVertical: 6,
    fontWeight: '700',
    fontSize: 11,
    color: '#111',
    borderRightWidth: 1,
    borderColor: '#222',
  },
  infoValue: { flex: 1, paddingHorizontal: 8, paddingVertical: 6, fontSize: 12, color: '#111', fontWeight: '600' },
  letter: { marginTop: 14, fontSize: 12, color: '#111', lineHeight: 18 },
  signRow: { marginTop: 18, flexDirection: 'row', justifyContent: 'space-between', paddingHorizontal: 24 },
  signBlock: { alignItems: 'center', minWidth: 200 },
  signName: {
    fontSize: 12,
    fontWeight: '800',
    color: '#111',
    borderBottomWidth: 1,
    borderColor: '#111',
    paddingBottom: 2,
    minWidth: 180,
    textAlign: 'center',
  },
  signRole: { marginTop: 4, fontSize: 11, color: '#333' },
  sectionHead: {
    marginTop: 18,
    fontSize: 12,
    fontWeight: '800',
    color: '#0b2f6b',
    textAlign: 'center',
    letterSpacing: 0.4,
  },
  table: { marginTop: 8, borderWidth: 1, borderColor: '#222' },
  tr: { flexDirection: 'row', borderBottomWidth: 1, borderColor: '#222', minHeight: 26, alignItems: 'stretch' },
  trHead: { backgroundColor: '#e8eef8' },
  trGroup: { backgroundColor: '#f3f3f3', paddingVertical: 4, paddingHorizontal: 8, borderBottomWidth: 1, borderColor: '#222' },
  groupLabel: { fontSize: 11, fontWeight: '800', color: '#111', fontStyle: 'italic' },
  trAvg: { backgroundColor: '#f7f2e8' },
  th: { fontSize: 10, fontWeight: '800', color: '#111', paddingVertical: 6, paddingHorizontal: 4, textAlign: 'center', borderRightWidth: 1, borderColor: '#222' },
  td: { fontSize: 10, color: '#111', paddingVertical: 5, paddingHorizontal: 4, borderRightWidth: 1, borderColor: '#222' },
  colLearn: { flex: 2.6, textAlign: 'left' },
  colGrade: { width: 88, textAlign: 'center' },
  colRemarks: { width: 78, textAlign: 'center' },
  colMonth: { width: 54, textAlign: 'center' },
  bold: { fontWeight: '800' },
  honor: { marginTop: 8, textAlign: 'center', fontWeight: '700', color: '#8a6a12' },
  twoCol: { flexDirection: 'row', gap: 10, marginTop: 8, alignItems: 'flex-start' },
  markingBox: {
    width: 150,
    borderWidth: 1,
    borderColor: '#222',
    padding: 8,
    backgroundColor: '#fafafa',
  },
  markingTitle: { fontWeight: '800', fontSize: 11, marginBottom: 6, color: '#111' },
  markingLine: { fontSize: 10, color: '#222', marginBottom: 4 },
  parentAck: { marginTop: 16, borderWidth: 1, borderColor: '#222', padding: 10 },
  parentTitle: { fontWeight: '800', fontSize: 11, color: '#111', marginBottom: 8 },
  parentLines: { flexDirection: 'row', flexWrap: 'wrap', gap: 10 },
  parentLine: { fontSize: 11, color: '#111', minWidth: 150 },
  footerNote: {
    marginTop: 14,
    fontSize: 10,
    color: '#333',
    textAlign: 'center',
    fontStyle: 'italic',
  },
  meta: { marginTop: 8, fontSize: 9, color: '#666', textAlign: 'center' },
});
