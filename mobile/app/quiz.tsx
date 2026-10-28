import { useLocalSearchParams } from 'expo-router';
import { useEffect, useState } from 'react';
import { Alert, Pressable, ScrollView, Text, View } from 'react-native';
import { apiRequest } from '../src/api/client';
import { useAuth } from '../src/auth/AuthContext';
import { t } from '../src/i18n';
import { notesForAi } from '../src/study/loadContext';

type Q = {
  id: number;
  question: string;
  options: string[];
  answer_index: number;
  explanation: string;
  type: string;
};

export default function QuizScreen() {
  const { token, lang, colors } = useAuth();
  const p = useLocalSearchParams<{ subject?: string; topic?: string; notes?: string }>();
  const [qs, setQs] = useState<Q[]>([]);
  const [session, setSession] = useState('');
  const [i, setI] = useState(0);
  const [picked, setPicked] = useState<number | null>(null);
  const [score, setScore] = useState(0);
  const [missed, setMissed] = useState<Q[]>([]);
  const [done, setDone] = useState(false);
  const [retake, setRetake] = useState(false);

  const load = (body: object) =>
    apiRequest<{ result: { session_token: string; data: { questions: Q[] } } }>('/ai/study', {
      method: 'POST',
      token,
      body: JSON.stringify(body),
    }).then((res) => {
      setSession(res.result.session_token);
      setQs(res.result.data.questions);
      setI(0);
      setPicked(null);
      setScore(0);
      setMissed([]);
      setDone(false);
    });

  useEffect(() => {
    notesForAi({ subject: p.subject, topic: p.topic, notes: p.notes }).then((ctx) =>
      load({
        tool_type: 'quiz',
        subject: ctx.subject,
        topic: ctx.topic || ctx.subject,
        notes: ctx.notes,
        question_count: 8,
        difficulty: 'medium',
      })
    ).catch((e) => Alert.alert('Quiz', e.message ?? 'Failed'));
  }, [token]);

  const q = qs[i];

  function choose(idx: number) {
    if (picked !== null) return;
    setPicked(idx);
    if (idx === q.answer_index) setScore((s) => s + 1);
    else setMissed((m) => [...m, q]);
  }

  async function next() {
    if (i + 1 >= qs.length) {
      setDone(true);
      await apiRequest('/ai/quiz/submit', {
        method: 'POST',
        token,
        body: JSON.stringify({
          session_token: session,
          subject: p.subject,
          difficulty: 'medium',
          score,
          total: qs.length,
          missed: missed.map((m) => m.id),
        }),
      }).catch(() => undefined);
      return;
    }
    setI(i + 1);
    setPicked(null);
  }

  if (done) {
    const finalScore = score;
    return (
      <View style={{ flex: 1, backgroundColor: colors.bg, padding: 16, justifyContent: 'center' }}>
        <Text style={{ color: colors.text, fontSize: 22, fontWeight: '700', textAlign: 'center' }}>
          {finalScore} / {qs.length}
        </Text>
        <Text style={{ color: colors.muted, textAlign: 'center', marginTop: 8 }}>{t(lang, 'aiLabel')}</Text>
        {missed.length > 0 && (
          <Pressable
            onPress={() => {
              setQs(missed);
              setI(0);
              setPicked(null);
              setScore(0);
              setMissed([]);
              setDone(false);
              setRetake(true);
            }}
            style={{ backgroundColor: '#0b1f3a', padding: 14, borderRadius: 12, marginTop: 20 }}
          >
            <Text style={{ color: '#c9a227', textAlign: 'center' }}>Retake missed items</Text>
          </Pressable>
        )}
      </View>
    );
  }

  if (!q) {
    return (
      <View style={{ flex: 1, justifyContent: 'center', backgroundColor: colors.bg }}>
        <Text style={{ textAlign: 'center', color: colors.muted }}>Generating quiz…</Text>
      </View>
    );
  }

  return (
    <ScrollView contentContainerStyle={{ padding: 16, backgroundColor: colors.bg, flexGrow: 1 }}>
      <Text style={{ color: '#c9a227' }}>{t(lang, 'aiLabel')}</Text>
      <Text style={{ color: colors.muted }}>
        {retake ? 'Missed items' : ''} {i + 1}/{qs.length}
      </Text>
      <Text style={{ color: colors.text, fontSize: 18, fontWeight: '700', marginVertical: 12 }}>{q.question}</Text>
      {q.options.map((opt, idx) => {
        let bg = colors.card;
        if (picked !== null) {
          if (idx === q.answer_index) bg = '#d4edda';
          else if (idx === picked) bg = '#f8d7da';
        }
        return (
          <Pressable key={opt} onPress={() => choose(idx)} style={{ backgroundColor: bg, padding: 12, borderRadius: 10, marginTop: 8 }}>
            <Text style={{ color: colors.text }}>{opt}</Text>
          </Pressable>
        );
      })}
      {picked !== null && (
        <>
          <Text style={{ color: colors.muted, marginTop: 12 }}>{q.explanation}</Text>
          <Pressable onPress={next} style={{ backgroundColor: '#0b1f3a', padding: 14, borderRadius: 12, marginTop: 16 }}>
            <Text style={{ color: '#c9a227', textAlign: 'center' }}>{i + 1 >= qs.length ? 'Finish' : 'Next'}</Text>
          </Pressable>
        </>
      )}
      <Pressable
        onPress={() =>
          apiRequest('/ai/report', {
            method: 'POST',
            token,
            body: JSON.stringify({ session_token: session, item_ref: String(q.id), comment: 'Wrong quiz item' }),
          }).then(() => Alert.alert('Thanks'))
        }
      >
        <Text style={{ color: colors.muted, marginTop: 20 }}>{t(lang, 'report')}</Text>
      </Pressable>
    </ScrollView>
  );
}
