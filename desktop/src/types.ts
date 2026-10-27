export type Role = 'admin' | 'teacher' | 'student';

export type User = {
  id: number;
  full_name: string;
  role: Role;
  username: string;
  email?: string;
};

export type Section = {
  id: number;
  section_code: string;
  grade_level: number;
  strand: string;
  strand_id?: number;
  school_year_id: number;
  school_year: string;
  is_current?: number;
  subject_id: number;
  subject_title: string;
  subject_code?: string;
  weight_profile_code?: string | null;
  class_subject_assignment_id: number;
  teacher_name?: string;
  written_work_pct?: string;
  performance_task_pct?: string;
  quarterly_assessment_pct?: string;
};

export type StudentRow = {
  student_id: number;
  full_name: string;
  lrn: string;
  enrollment_id: number;
  class_section_id: number;
  grade_level?: number;
  section_code?: string;
  strand?: string;
  photo_path?: string | null;
  school_year?: string;
  current_grade?: string | number | null;
  remarks?: string | null;
};

export type GradeItem = {
  id: number;
  component_type: 'written_work' | 'performance_task' | 'quarterly_assessment';
  item_label: string;
  max_score: string | number;
  sort_order: number;
};

export type Cell = {
  component_id: number;
  score: number | null;
  entry_status: 'scored' | 'absent' | 'excused';
  max_score: number;
  recorded_at: string | null;
};

export type GridStudent = StudentRow & {
  cells: Record<string, Cell>;
  q1?: string | null;
  q2?: string | null;
  q3?: string | null;
  q4?: string | null;
  sem1?: string | null;
  sem2?: string | null;
  final_rating?: string | null;
  action_taken?: string | null;
  is_submitted?: number;
  written_work_avg?: string | null;
  performance_task_avg?: string | null;
  quarterly_assessment_score?: string | null;
  initial_grade?: string | null;
  transmuted_grade?: string | null;
  remarks?: string | null;
};

export type Weights = {
  written_work_pct: number;
  performance_task_pct: number;
  quarterly_assessment_pct: number;
  profile_code?: string | null;
  confirmed?: boolean;
};

export type GridPayload = {
  class: Record<string, string | number>;
  quarter: number;
  locked: boolean;
  weights: Weights;
  passing_mark: number;
  items: GradeItem[];
  students: GridStudent[];
};

export type QueueItem = {
  id: string;
  method: string;
  path: string;
  body: unknown;
  createdAt: string;
};

export type SecureCache = {
  token?: string | null;
  user?: User | null;
  apiBase?: string;
  queue?: QueueItem[];
  lastGrid?: { key: string; payload: GridPayload; savedAt: string };
};

export type SyncStatus = 'online' | 'offline' | 'syncing' | 'conflict' | 'error';

declare global {
  interface Window {
    holyface?: {
      apiBaseUrl: string;
      secureLoad: () => Promise<{ ok: boolean; data: SecureCache | null }>;
      secureSave: (data: SecureCache) => Promise<{ ok: boolean }>;
      encryptionAvailable: () => Promise<{ ok: boolean; safeStorage: boolean }>;
      exportFile: (opts: {
        defaultName: string;
        content: string;
        filters?: { name: string; extensions: string[] }[];
      }) => Promise<{ canceled: boolean; filePath?: string }>;
      printPage: () => Promise<{ ok: boolean }>;
    };
  }
}
