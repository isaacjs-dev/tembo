import { omrService } from './omr';
import { useScanStore } from '@/store/scan-store';
import { useSyncStore } from '@/store/sync-store';
import type { ScanResult } from '@/types/scan';

async function buildFormData(scan: ScanResult): Promise<FormData> {
  const formData = new FormData();

  formData.append('exam_id', String(scan.examId));
  if (scan.copyId) {
    formData.append('copy_id', String(scan.copyId));
  }

  // Required new M2 fields
  formData.append('session_id', scan.sessionId || scan.localId);
  formData.append('page_index', String(scan.pageIndex || 1));
  formData.append('total_pages', String(scan.pageTotal || 1));

  // Image file
  formData.append('image', {
    uri: scan.imageUri,
    type: 'image/jpeg',
    name: `scan_${scan.localId}.jpg`,
  } as any);

  // Raw result dicts
  formData.append('detected_answers', JSON.stringify(scan.detectedAnswers));

  if (scan.questionConfidences) {
    formData.append('confidences', JSON.stringify(scan.questionConfidences));
  }

  if (scan.studentId) {
    formData.append('student_id', String(scan.studentId));
  }

  formData.append('overall_confidence', String(scan.confidenceScore || 0));
  if (scan.omrEvidence) {
    formData.append('processing_evidence', JSON.stringify(scan.omrEvidence));
  }

  return formData;
}

export async function syncSingleScan(localId: string): Promise<boolean> {
  const scan = useScanStore.getState().scans.find((s) => s.localId === localId);
  if (!scan) {
    useSyncStore.getState().removeFromQueue(localId);
    return false;
  }

  if (scan.status === 'synced') {
    useSyncStore.getState().removeFromQueue(localId);
    return true;
  }

  try {
    const formData = await buildFormData(scan);
    const response = await omrService.uploadScan(formData);

    useScanStore.getState().updateScan(localId, {
      status: 'synced',
      syncedAt: new Date().toISOString(),
      serverScanId: response.page?.id,
    });

    useSyncStore.getState().removeFromQueue(localId);
    useSyncStore.getState().clearSyncError(localId);

    return true;
  } catch (error: any) {
    const message =
      error.response?.data?.error ||
      error.response?.data?.message ||
      error.message ||
      'Erro desconhecido ao sincronizar';

    useSyncStore.getState().setSyncError(localId, message);
    return false;
  }
}

export async function syncAllPending(): Promise<{
  synced: number;
  failed: number;
}> {
  const { syncQueue, isSyncing, isOnline } = useSyncStore.getState();

  if (isSyncing || !isOnline || syncQueue.length === 0) {
    return { synced: 0, failed: 0 };
  }

  useSyncStore.getState().setSyncing(true);

  let synced = 0;
  let failed = 0;

  // Process sequentially to avoid overwhelming the server
  for (const localId of [...syncQueue]) {
    const success = await syncSingleScan(localId);
    if (success) {
      synced++;
    } else {
      failed++;
    }
  }

  if (synced > 0) {
    useSyncStore.getState().setLastSync(new Date().toISOString());
  }

  useSyncStore.getState().setSyncing(false);

  return { synced, failed };
}
