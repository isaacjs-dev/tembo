import api from './api';
import type { ExamListItem, ExamDownload } from '@/types/exam';

export const examService = {
  async list(): Promise<ExamListItem[]> {
    const response = await api.get<{ exams: ExamListItem[] }>('/exams');
    return response.data.exams;
  },

  async download(examId: number): Promise<ExamDownload> {
    const response = await api.get<ExamDownload>(`/exams/${examId}/download`);
    return response.data;
  },
};
