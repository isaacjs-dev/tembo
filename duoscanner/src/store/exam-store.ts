import { create } from 'zustand';
import * as SecureStore from 'expo-secure-store';
import type { ExamListItem, ExamDownload } from '@/types/exam';
import { deleteLocalExam, getAllLocalExams, saveLocalExam } from '@/db/database';

interface CachedExam {
  examId: number;
  data: ExamDownload;
  downloadedAt: string;
}

interface ExamState {
  examList: ExamListItem[];
  cachedExams: Map<number, CachedExam>;
  isLoadingList: boolean;
  isDownloading: number | null; // examId being downloaded
  hydrated: boolean;

  setExamList: (exams: ExamListItem[]) => void;
  cacheExam: (examId: number, data: ExamDownload) => Promise<void>;
  removeCachedExam: (examId: number) => Promise<void>;
  getCachedExam: (examId: number) => CachedExam | undefined;
  isExamCached: (examId: number) => boolean;
  setLoadingList: (loading: boolean) => void;
  setDownloading: (examId: number | null) => void;
  hydrate: () => Promise<void>;
}

export const useExamStore = create<ExamState>((set, get) => ({
  examList: [],
  cachedExams: new Map(),
  isLoadingList: false,
  isDownloading: null,
  hydrated: false,

  setExamList: (examList) => set({ examList }),

  cacheExam: async (examId, data) => {
    const cachedExams = new Map(get().cachedExams);
    cachedExams.set(examId, {
      examId,
      data,
      downloadedAt: new Date().toISOString(),
    });
    const version = Number(data.exam.settings?.layout_version ?? 1);
    await saveLocalExam(examId, JSON.stringify(data), version);
    set({ cachedExams });
  },

  removeCachedExam: async (examId) => {
    const cachedExams = new Map(get().cachedExams);
    cachedExams.delete(examId);
    await deleteLocalExam(examId);
    set({ cachedExams });
  },

  getCachedExam: (examId) => get().cachedExams.get(examId),

  isExamCached: (examId) => get().cachedExams.has(examId),

  setLoadingList: (isLoadingList) => set({ isLoadingList }),
  setDownloading: (isDownloading) => set({ isDownloading }),

  hydrate: async () => {
    try {
      let rows = await getAllLocalExams();
      if (rows.length === 0) {
        const legacyData = await SecureStore.getItemAsync('cachedExams');
        if (legacyData) {
          const legacy = JSON.parse(legacyData) as Record<number, CachedExam>;
          await Promise.all(Object.values(legacy).map((cached) => {
            const version = Number(cached.data.exam.settings?.layout_version ?? 1);
            return saveLocalExam(cached.examId, JSON.stringify(cached.data), version);
          }));
          rows = await getAllLocalExams();
        }
      }
      const cachedExams = new Map<number, CachedExam>();
      for (const row of rows) {
        const data = JSON.parse(row.data_json) as ExamDownload;
        cachedExams.set(row.exam_id, {
          examId: row.exam_id,
          data,
          downloadedAt: row.downloaded_at,
        });
      }
      set({ cachedExams, hydrated: true });
    } catch {
      set({ cachedExams: new Map(), hydrated: true });
    }
  },
}));
