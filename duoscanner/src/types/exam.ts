export interface ExamListItem {
  id: number;
  title: string;
  status: string;
  questions_count: number;
  submissions_count: number;
  discipline?: ExamDiscipline | null;
  classes: { id: number; name: string }[];
  updated_at: string;
}

export interface ExamDownload {
  exam: {
    id: number;
    title: string;
    status: string;
    settings: Record<string, unknown>;
    discipline?: ExamDiscipline | null;
  };
  copies: ExamCopy[];
  questions: QuestionData[];
  students: StudentData[];
  downloaded_at: string;
}

export interface ExamDiscipline {
  id: number;
  name: string;
}

export interface ExamCopy {
  id: number;
  copy_number: number;
  validation_hash: string;
  questions_map: number[];
  options_map: Record<string, number[]>;
}

export interface QuestionData {
  id: number;
  type: 'multiple_choice' | 'true_false' | 'essay';
  correct_option: number | null;
  option_count: number;
  points: number;
  order: number;
}

export interface StudentData {
  id: number;
  name: string;
  registration_number: string | null;
}
