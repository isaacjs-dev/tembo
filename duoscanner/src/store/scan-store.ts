import { create } from 'zustand';
import * as SecureStore from 'expo-secure-store';
import type { ScanResult } from '@/types/scan';
import type { QRPayload } from '@/types/scan';
import { addToSyncQueue, deleteScan, getAllScans, saveScan } from '@/db/database';

interface ScanState {
  // Current scan session
  currentScan: Partial<ScanResult> | null;
  currentQR: QRPayload | null;

  // History
  scans: ScanResult[];
  pendingCount: number;
  hydrated: boolean;

  // Actions
  startScan: (qr: QRPayload) => void;
  updateCurrentScan: (data: Partial<ScanResult>) => void;
  finalizeScan: (scan: ScanResult) => void;
  clearCurrentScan: () => void;
  setCurrentScan: (scan: Partial<ScanResult>) => void;
  addScan: (scan: ScanResult) => void;
  updateScan: (localId: string, data: Partial<ScanResult>) => void;
  setScans: (scans: ScanResult[]) => void;
  removeScan: (localId: string) => Promise<void>;
  hydrate: () => Promise<void>;
}

export const useScanStore = create<ScanState>((set, get) => ({
  currentScan: null,
  currentQR: null,
  scans: [],
  pendingCount: 0,
  hydrated: false,

  startScan: (qr) => {
    set({
      currentQR: qr,
      currentScan: {
        examId: qr.e,
        copyId: qr.c,
        validationHash: qr.h,
        qrGeometry: qr.g,
        qrOptionCounts: qr.oc,
        qrPayload: qr.signedPayload,
        qrVersion: qr.v,
        templateId: qr.tpl_id,
        templateVersion: qr.tpl_v,
        rowsPerPage: qr.rpp,
        layoutVersion: qr.tpl_v,
        status: 'processing',
        createdAt: new Date().toISOString(),
      },
    });
  },

  updateCurrentScan: (data) => {
    const current = get().currentScan;
    if (current) {
      set({ currentScan: { ...current, ...data } });
    }
  },

  finalizeScan: (scan) => {
    const scans = [scan, ...get().scans.filter((item) => item.localId !== scan.localId)];
    const pendingCount = scans.filter(
      (s) => s.status === 'confirmed' || s.status === 'review'
    ).length;
    set({ scans, pendingCount, currentScan: null, currentQR: null });
  },

  clearCurrentScan: () => set({ currentScan: null, currentQR: null }),

  setCurrentScan: (currentScan) => set({ currentScan, currentQR: null }),

  addScan: (scan) => {
    const scans = [scan, ...get().scans];
    const pendingCount = scans.filter(
      (s) => s.status === 'confirmed' || s.status === 'review'
    ).length;
    set({ scans, pendingCount });
  },

  updateScan: (localId, data) => {
    const scans = get().scans.map((s) =>
      s.localId === localId ? { ...s, ...data } : s
    );
    const pendingCount = scans.filter(
      (s) => s.status === 'confirmed' || s.status === 'review'
    ).length;
    set({ scans, pendingCount });
  },

  setScans: (scans) => {
    const pendingCount = scans.filter(
      (s) => s.status === 'confirmed' || s.status === 'review'
    ).length;
    set({ scans, pendingCount });
  },

  removeScan: async (localId) => {
    await deleteScan(localId);
    const scans = get().scans.filter((s) => s.localId !== localId);
    const pendingCount = scans.filter(
      (s) => s.status === 'confirmed' || s.status === 'review'
    ).length;
    set({ scans, pendingCount });
  },

  hydrate: async () => {
    try {
      const rows = await getAllScans();
      let scans = rows.flatMap((row) => {
        if (!row.payload_json) return [];
        try {
          const scan = JSON.parse(row.payload_json) as ScanResult;
          return [{
            ...scan,
            status: row.status as ScanResult['status'],
            syncedAt: row.synced_at,
            serverScanId: row.server_scan_id,
          }];
        } catch {
          return [];
        }
      });

      // One-time compatibility read for scans saved by old builds. New scan
      // payloads live in SQLite; SecureStore remains reserved for credentials.
      if (scans.length === 0) {
        const legacyData = await SecureStore.getItemAsync('scans');
        scans = legacyData ? JSON.parse(legacyData) : [];
        await Promise.all(scans.map(async (scan: ScanResult) => {
          await saveScan({
            local_id: scan.localId,
            exam_id: scan.examId,
            copy_id: scan.copyId,
            student_id: scan.studentId,
            status: scan.status,
            image_uri: scan.imageUri,
            detected_answers: JSON.stringify(scan.detectedAnswers),
            confirmed_answers: scan.confirmedAnswers ? JSON.stringify(scan.confirmedAnswers) : null,
            confidence_score: scan.confidenceScore,
            answer_sheet_type: 'essential',
            scan_mode: 'hybrid',
            idempotency_key: `${scan.examId}-${scan.copyId}-${scan.localId}`,
            session_id: scan.sessionId || scan.localId,
            page_index: scan.pageIndex || 1,
            total_pages: scan.pageTotal || 1,
            created_at: scan.createdAt,
            synced_at: scan.syncedAt,
            server_scan_id: scan.serverScanId,
            payload_json: JSON.stringify(scan),
          });
          if (scan.status === 'confirmed' || scan.status === 'review') {
            await addToSyncQueue(scan.localId);
          }
        }));
      }
      const pendingCount = scans.filter(
        (s: any) => s.status === 'confirmed' || s.status === 'review'
      ).length;
      set({ scans, pendingCount, hydrated: true });
    } catch {
      set({ scans: [], pendingCount: 0, hydrated: true });
    }
  },
}));
