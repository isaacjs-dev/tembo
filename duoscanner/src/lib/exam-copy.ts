import type { ExamDownload } from '@/types/exam';

export function individualizedStudent(
  data: ExamDownload,
  copyId: number
): { studentId: number; studentName: string | null } | null {
  const copy = data.copies.find((item) => item.id === copyId);
  if (!copy?.student_id) return null;

  const student = data.students.find((item) => item.id === copy.student_id);

  return {
    studentId: copy.student_id,
    studentName: student?.name ?? null,
  };
}
