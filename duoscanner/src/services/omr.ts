import api from './api';
import type { ScanResponse } from '@/types/api';

export const omrService = {
  async uploadScan(formData: FormData): Promise<ScanResponse> {
    const response = await api.post<ScanResponse>('/omr/scans', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
      timeout: 60000,
    });
    return response.data;
  },

  async listScans(page = 1) {
    const response = await api.get(`/omr/scans?page=${page}`);
    return response.data;
  },

  async getScan(scanId: number) {
    const response = await api.get(`/omr/scans/${scanId}`);
    return response.data;
  },

  async confirmScan(scanId: number, studentId: number, confirmedAnswers: Record<string, number>) {
    const response = await api.put(`/omr/scans/${scanId}/confirm`, {
      student_id: studentId,
      confirmed_answers: confirmedAnswers,
    });
    return response.data;
  },

  async rejectScan(scanId: number, notes?: string) {
    const response = await api.put(`/omr/scans/${scanId}/reject`, { notes });
    return response.data;
  },
};
