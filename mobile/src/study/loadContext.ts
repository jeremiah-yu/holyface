import { loadStudyContext, type StudyContext } from '../storage/studyContext';

export async function notesForAi(fallback: Partial<StudyContext> = {}): Promise<StudyContext> {
  const saved = await loadStudyContext();
  return {
    subject: saved.subject || fallback.subject || '',
    topic: saved.topic || fallback.topic || saved.subject || fallback.subject || '',
    notes: saved.notes || fallback.notes || '',
  };
}
