import AsyncStorage from '@react-native-async-storage/async-storage';

const KEY = 'amgs_study_context';

export type StudyContext = { subject: string; topic: string; notes: string };

export async function saveStudyContext(data: StudyContext): Promise<void> {
  await AsyncStorage.setItem(KEY, JSON.stringify(data));
}

export async function loadStudyContext(): Promise<StudyContext> {
  const raw = await AsyncStorage.getItem(KEY);
  if (!raw) {
    return { subject: '', topic: '', notes: '' };
  }
  try {
    const parsed = JSON.parse(raw) as StudyContext;
    return {
      subject: parsed.subject ?? '',
      topic: parsed.topic ?? '',
      notes: parsed.notes ?? '',
    };
  } catch {
    return { subject: '', topic: '', notes: '' };
  }
}
