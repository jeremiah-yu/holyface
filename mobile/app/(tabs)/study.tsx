import { router } from 'expo-router';
import { useEffect, useState } from 'react';
import { Pressable, ScrollView, Text, TextInput, View } from 'react-native';
import { apiRequest } from '../../src/api/client';
import { useAuth } from '../../src/auth/AuthContext';
import { t } from '../../src/i18n';

type Subject = { id: number; title: string; subject_code: string };

export default function StudyHub() {
  const { token, lang, colors } = useAuth();
  const [subjects, setSubjects] = useState<Subject[]>([]);
  const [subject, setSubject] = useState('');
  const [topic, setTopic] = useState('');
  const [notes, setNotes] = useState('');
  const [exam, setExam] = useState('');
  const [hours, setHours] = useState('1.5');

  useEffect(() => {
    apiRequest<{ subjects: Subject[] }>('/students/subjects', { token })
      .then((r) => {
        setSubjects(r.subjects);
        if (r.subjects[0]) setSubject(r.subjects[0].title);
      })
      .catch(() => undefined);
  }, [token]);

  const go = (tool: string) => {
    router.push({
      pathname: tool === 'study_plan' ? '/study-plan' : tool === 'flashcards' ? '/flashcards' : '/quiz',
      params: { subject, topic, notes, exam_date: exam, hours_per_day: hours },
    });
  };

  return (
    <ScrollView contentContainerStyle={{ padding: 16, backgroundColor: colors.bg, flexGrow: 1 }}>
      <Text style={{ color: colors.text, fontSize: 20, fontWeight: '700' }}>AI study tools</Text>
      <Text style={{ color: colors.muted, marginVertical: 8 }}>{t(lang, 'aiLabel')}</Text>
      <Text style={{ color: colors.text, fontWeight: '600' }}>Subject</Text>
      {subjects.map((s) => (
        <Pressable key={s.id} onPress={() => setSubject(s.title)}>
          <Text style={{ color: subject === s.title ? '#c9a227' : colors.text, paddingVertical: 4 }}>{s.title}</Text>
        </Pressable>
      ))}
      <TextInput
        placeholder="Or type a subject"
        value={subject}
        onChangeText={setSubject}
        style={{ borderWidth: 1, borderColor: colors.border, backgroundColor: colors.card, color: colors.text, padding: 10, borderRadius: 8, marginTop: 8 }}
      />
      <TextInput
        placeholder="Topic"
        value={topic}
        onChangeText={setTopic}
        style={{ borderWidth: 1, borderColor: colors.border, backgroundColor: colors.card, color: colors.text, padding: 10, borderRadius: 8, marginTop: 8 }}
      />
      <TextInput
        placeholder="Paste your own notes (no names or LRN)"
        value={notes}
        onChangeText={setNotes}
        multiline
        style={{ borderWidth: 1, borderColor: colors.border, backgroundColor: colors.card, color: colors.text, padding: 10, borderRadius: 8, marginTop: 8, minHeight: 80 }}
      />
      <TextInput
        placeholder="Exam date YYYY-MM-DD"
        value={exam}
        onChangeText={setExam}
        style={{ borderWidth: 1, borderColor: colors.border, backgroundColor: colors.card, color: colors.text, padding: 10, borderRadius: 8, marginTop: 8 }}
      />
      <TextInput
        placeholder="Hours per day"
        value={hours}
        onChangeText={setHours}
        keyboardType="decimal-pad"
        style={{ borderWidth: 1, borderColor: colors.border, backgroundColor: colors.card, color: colors.text, padding: 10, borderRadius: 8, marginTop: 8 }}
      />
      <Pressable onPress={() => go('study_plan')} style={btn}>
        <Text style={btnTxt}>Study plan</Text>
      </Pressable>
      <Pressable onPress={() => go('flashcards')} style={btn}>
        <Text style={btnTxt}>Flashcards</Text>
      </Pressable>
      <Pressable onPress={() => go('quiz')} style={btn}>
        <Text style={btnTxt}>Sample quiz</Text>
      </Pressable>
      <Text style={{ color: colors.muted, marginTop: 16, fontSize: 12 }}>
        Do not submit AI output as graded work. Weak subjects are prioritized on the server without sending your identity to any AI provider.
      </Text>
    </ScrollView>
  );
}

const btn = { backgroundColor: '#0b1f3a', padding: 14, borderRadius: 12, marginTop: 12 };
const btnTxt = { color: '#c9a227', textAlign: 'center' as const, fontWeight: '700' as const };
