import { router } from 'expo-router';
import { useEffect, useState } from 'react';
import { Alert, Pressable, ScrollView, Text, TextInput, View } from 'react-native';
import * as DocumentPicker from 'expo-document-picker';
import * as FileSystem from 'expo-file-system/legacy';
import * as ImagePicker from 'expo-image-picker';
import { apiRequest } from '../../src/api/client';
import { useAuth } from '../../src/auth/AuthContext';
import { t } from '../../src/i18n';
import { saveStudyContext } from '../../src/storage/studyContext';

type Subject = { id: number; title: string; subject_code: string };

const MAX_BYTES = 15_728_640;
const FILE_TYPES = [
  'application/pdf',
  'application/msword',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  'image/jpeg',
  'image/png',
  'image/webp',
];

export default function StudyHub() {
  const { token, lang, colors } = useAuth();
  const [subjects, setSubjects] = useState<Subject[]>([]);
  const [subject, setSubject] = useState('');
  const [topic, setTopic] = useState('');
  const [notes, setNotes] = useState('');
  const [fileName, setFileName] = useState('');
  const [busy, setBusy] = useState(false);
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
    if (!notes.trim()) {
      Alert.alert('Study tools', 'Upload a notes file first so Gemini can generate materials from it.');
      return;
    }
    void saveStudyContext({ subject, topic: topic || subject, notes }).then(() => {
      const path = tool === 'study_plan' ? '/study-plan' : tool === 'flashcards' ? '/flashcards' : '/quiz';
      router.push({
        pathname: path,
        params: { subject, topic: topic || subject, notes, exam_date: exam, hours_per_day: hours },
      });
    });
  };

  function chooseUpload() {
    Alert.alert('Upload notes', 'PDF, Word, or photos only. Max 15 MB.', [
      { text: 'Files (PDF / Word)', onPress: () => void pickFile() },
      { text: 'Photo library', onPress: () => void pickImage(false) },
      { text: 'Camera', onPress: () => void pickImage(true) },
      { text: 'Cancel', style: 'cancel' },
    ]);
  }

  async function pickFile() {
    const picked = await DocumentPicker.getDocumentAsync({
      type: FILE_TYPES,
      copyToCacheDirectory: true,
    });
    if (picked.canceled || !picked.assets?.[0]) {
      return;
    }
    const file = picked.assets[0];
    await sendUpload(file.uri, file.name ?? 'notes.pdf', file.size ?? 0);
  }

  async function pickImage(camera: boolean) {
    const perm = camera
      ? await ImagePicker.requestCameraPermissionsAsync()
      : await ImagePicker.requestMediaLibraryPermissionsAsync();
    if (!perm.granted) {
      Alert.alert('Upload', camera ? 'Camera permission is needed.' : 'Photo library permission is needed.');
      return;
    }
    const result = camera
      ? await ImagePicker.launchCameraAsync({ quality: 0.55, allowsEditing: false })
      : await ImagePicker.launchImageLibraryAsync({ quality: 0.55, mediaTypes: ['images'] });
    if (result.canceled || !result.assets?.[0]) {
      return;
    }
    const asset = result.assets[0];
    const name = asset.fileName ?? (camera ? 'camera-notes.jpg' : 'photo-notes.jpg');
    await sendUpload(asset.uri, name, asset.fileSize ?? 0);
  }

  async function sendUpload(uri: string, name: string, size: number) {
    if (size > MAX_BYTES) {
      Alert.alert('Upload', 'Maximum file size is 15 MB.');
      return;
    }
    setBusy(true);
    try {
      const content_base64 = await FileSystem.readAsStringAsync(uri, {
        encoding: FileSystem.EncodingType.Base64,
      });
      const res = await apiRequest<{ text: string; filename: string }>('/ai/extract-notes', {
        method: 'POST',
        token,
        timeoutMs: 60000,
        body: JSON.stringify({ filename: name, content_base64 }),
      });
      setNotes(res.text);
      setFileName(res.filename);
      const nextTopic = topic || res.filename.replace(/\.[^.]+$/, '').replace(/[_-]+/g, ' ');
      if (!topic) {
        setTopic(nextTopic);
      }
      await saveStudyContext({ subject, topic: nextTopic, notes: res.text });
    } catch (e: unknown) {
      setNotes('');
      setFileName('');
      Alert.alert('Upload', (e as { message?: string }).message ?? 'Could not read that file.');
    } finally {
      setBusy(false);
    }
  }

  return (
    <ScrollView contentContainerStyle={{ padding: 16, backgroundColor: colors.bg, flexGrow: 1 }}>
      <Text style={{ color: colors.text, fontSize: 20, fontWeight: '700' }}>AI study tools</Text>
      <Text style={{ color: colors.muted, marginVertical: 8 }}>{t(lang, 'aiLabel')}</Text>
      <Text style={{ color: colors.muted, marginBottom: 8 }}>{t(lang, 'uploadHint')}</Text>
      <Pressable onPress={chooseUpload} disabled={busy} style={btn}>
        <Text style={btnTxt}>{busy ? 'Reading file…' : t(lang, 'uploadNotes')}</Text>
      </Pressable>
      {fileName ? (
        <Text style={{ color: colors.ok, marginTop: 8 }}>
          {t(lang, 'fileReady')} ({fileName})
        </Text>
      ) : null}

      <Text style={{ color: colors.text, fontWeight: '600', marginTop: 16 }}>Subject</Text>
      {subjects.map((s) => (
        <Pressable key={s.id} onPress={() => setSubject(s.title)}>
          <Text style={{ color: subject === s.title ? '#c9a227' : colors.text, paddingVertical: 4 }}>{s.title}</Text>
        </Pressable>
      ))}
      <TextInput placeholder="Or type a subject" value={subject} onChangeText={setSubject} style={field(colors)} />
      <TextInput placeholder="Topic" value={topic} onChangeText={setTopic} style={field(colors)} />
      <TextInput
        placeholder="Notes from your file appear here (no names or student number)"
        value={notes}
        onChangeText={setNotes}
        multiline
        style={{ ...field(colors), minHeight: 100 }}
      />
      <TextInput placeholder="Exam date YYYY-MM-DD" value={exam} onChangeText={setExam} style={field(colors)} />
      <TextInput
        placeholder="Hours per day"
        value={hours}
        onChangeText={setHours}
        keyboardType="decimal-pad"
        style={field(colors)}
      />
      <Pressable onPress={() => go('flashcards')} style={btn}>
        <Text style={btnTxt}>Flashcards</Text>
      </Pressable>
      <Pressable onPress={() => go('quiz')} style={btn}>
        <Text style={btnTxt}>Sample quiz</Text>
      </Pressable>
      <Pressable onPress={() => go('study_plan')} style={btn}>
        <Text style={btnTxt}>Study plan</Text>
      </Pressable>
      <Text style={{ color: colors.muted, marginTop: 16, fontSize: 12 }}>
        Gemini reads the uploaded notes only. Your name and student number are never sent.
      </Text>
      <View style={{ height: 24 }} />
    </ScrollView>
  );
}

function field(colors: { border: string; card: string; text: string }) {
  return {
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.card,
    color: colors.text,
    padding: 10,
    borderRadius: 8,
    marginTop: 8,
  };
}

const btn = { backgroundColor: '#0b1f3a', padding: 14, borderRadius: 12, marginTop: 12 };
const btnTxt = { color: '#c9a227', textAlign: 'center' as const, fontWeight: '700' as const };
