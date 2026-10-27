import { useLocalSearchParams } from 'expo-router';
import { useEffect, useMemo, useState } from 'react';
import { Alert, Pressable, ScrollView, Text, View } from 'react-native';
import { apiRequest } from '../src/api/client';
import { useAuth } from '../src/auth/AuthContext';
import { t } from '../src/i18n';

type Task = { id: number; day_no: number; task_text: string; is_done: number };
type Plan = {
  session_token: string;
  data: {
    title: string;
    duration_days: number;
    schedule: { day: number; focus: string; minutes: number; tasks: string[] }[];
    disclaimer: string;
  };
};

export default function StudyPlanScreen() {
  const { token, lang, colors } = useAuth();
  const p = useLocalSearchParams<{ subject?: string; topic?: string; notes?: string; exam_date?: string; hours_per_day?: string }>();
  const [plan, setPlan] = useState<Plan | null>(null);
  const [tasks, setTasks] = useState<Task[]>([]);

  useEffect(() => {
    (async () => {
      const res = await apiRequest<{ result: Plan }>('/ai/study', {
        method: 'POST',
        token,
        body: JSON.stringify({
          tool_type: 'study_plan',
          subject: p.subject,
          topic: p.topic || p.subject,
          notes: p.notes,
          exam_date: p.exam_date,
          hours_per_day: Number(p.hours_per_day || 1.5),
        }),
      });
      setPlan(res.result);
      const list = await apiRequest<{ tasks: Task[] }>(
        `/ai/plan/tasks?session_token=${encodeURIComponent(res.result.session_token)}`,
        { token }
      );
      setTasks(list.tasks);
    })().catch((e) => Alert.alert('Study plan', e.message ?? 'Failed'));
  }, [token]);

  const done = tasks.filter((x) => x.is_done).length;
  const pct = tasks.length ? Math.round((done / tasks.length) * 100) : 0;

  const byDay = useMemo(() => {
    const m: Record<number, Task[]> = {};
    tasks.forEach((tk) => {
      m[tk.day_no] = m[tk.day_no] || [];
      m[tk.day_no].push(tk);
    });
    return m;
  }, [tasks]);

  async function toggle(id: number) {
    await apiRequest('/ai/plan/tasks', { method: 'POST', token, body: JSON.stringify({ task_id: id }) });
    setTasks((prev) => prev.map((x) => (x.id === id ? { ...x, is_done: x.is_done ? 0 : 1 } : x)));
  }

  return (
    <ScrollView contentContainerStyle={{ padding: 16, backgroundColor: colors.bg }}>
      <Text style={{ color: colors.text, fontSize: 20, fontWeight: '700' }}>{plan?.data.title ?? 'Study plan'}</Text>
      <Text style={{ color: colors.gold, marginVertical: 8 }}>{plan?.data.disclaimer ?? t(lang, 'aiLabel')}</Text>
      <View style={{ height: 10, backgroundColor: colors.border, borderRadius: 6 }}>
        <View style={{ width: `${pct}%`, height: 10, backgroundColor: '#c9a227', borderRadius: 6 }} />
      </View>
      <Text style={{ color: colors.muted, marginTop: 4 }}>{pct}% · reminders: review each day at your chosen study hour</Text>
      {Object.entries(byDay).map(([day, list]) => (
        <View key={day} style={{ marginTop: 14, backgroundColor: colors.card, padding: 12, borderRadius: 10 }}>
          <Text style={{ color: colors.text, fontWeight: '700' }}>Day {day}</Text>
          {list.map((tk) => (
            <Pressable key={tk.id} onPress={() => toggle(tk.id)} style={{ flexDirection: 'row', gap: 8, marginTop: 8 }}>
              <Text>{tk.is_done ? '☑' : '☐'}</Text>
              <Text style={{ color: colors.text, flex: 1 }}>{tk.task_text}</Text>
            </Pressable>
          ))}
        </View>
      ))}
      <ReportButton token={token} session={plan?.session_token} colors={colors} />
    </ScrollView>
  );
}

function ReportButton({ token, session, colors }: { token: string | null; session?: string; colors: { muted: string } }) {
  const { lang } = useAuth();
  return (
    <Pressable
      style={{ marginTop: 20, marginBottom: 40 }}
      onPress={() =>
        apiRequest('/ai/report', {
          method: 'POST',
          token,
          body: JSON.stringify({ session_token: session, comment: 'Wrong or unhelpful study plan item', item_ref: 'plan' }),
        }).then(() => Alert.alert('Thanks', 'Report received.')).catch(() => undefined)
      }
    >
      <Text style={{ color: colors.muted }}>{t(lang, 'report')}</Text>
    </Pressable>
  );
}
