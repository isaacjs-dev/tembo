import { create } from 'zustand';
import * as SecureStore from 'expo-secure-store';
import { getSyncQueueCount } from '@/db/database';

async function persistQueue(queue: string[], errors: Record<string, string>) {
  try {
    await SecureStore.setItemAsync('syncQueue', JSON.stringify(queue));
    await SecureStore.setItemAsync('syncErrors', JSON.stringify(errors));
  } catch { }
}

interface SyncState {
  isOnline: boolean;
  isSyncing: boolean;
  lastSyncAt: string | null;
  syncQueue: string[]; // localIds pending sync (legacy, kept for backward compat)
  syncErrors: Record<string, string>; // localId -> error message
  pendingCount: number; // count from SQLite sync_queue
  hydrated: boolean;

  setOnline: (online: boolean) => void;
  setSyncing: (syncing: boolean) => void;
  setLastSync: (date: string) => void;
  addToQueue: (localId: string) => void;
  removeFromQueue: (localId: string) => void;
  setSyncError: (localId: string, error: string) => void;
  clearSyncError: (localId: string) => void;
  clearQueue: () => void;
  updatePendingCount: (count: number) => void;
  hydrate: () => Promise<void>;
}

export const useSyncStore = create<SyncState>((set, get) => ({
  isOnline: true,
  isSyncing: false,
  lastSyncAt: null,
  syncQueue: [],
  syncErrors: {},
  pendingCount: 0,
  hydrated: false,

  setOnline: (isOnline) => set({ isOnline }),
  setSyncing: (isSyncing) => set({ isSyncing }),
  setLastSync: (lastSyncAt) => {
    SecureStore.setItemAsync('lastSyncAt', lastSyncAt);
    set({ lastSyncAt });
  },

  addToQueue: (localId) => {
    const queue = [...get().syncQueue];
    if (!queue.includes(localId)) {
      queue.push(localId);
    }
    set({ syncQueue: queue });
    persistQueue(queue, get().syncErrors);
  },

  removeFromQueue: (localId) => {
    const queue = get().syncQueue.filter((id) => id !== localId);
    set({ syncQueue: queue });
    persistQueue(queue, get().syncErrors);
  },

  setSyncError: (localId, error) => {
    const errors = { ...get().syncErrors, [localId]: error };
    set({ syncErrors: errors });
    persistQueue(get().syncQueue, errors);
  },

  clearSyncError: (localId) => {
    const errors = { ...get().syncErrors };
    delete errors[localId];
    set({ syncErrors: errors });
    persistQueue(get().syncQueue, errors);
  },

  clearQueue: () => {
    set({ syncQueue: [], syncErrors: {}, pendingCount: 0 });
    persistQueue([], {});
  },

  updatePendingCount: (count) => set({ pendingCount: count }),

  hydrate: async () => {
    try {
      const [queueData, errorsData, lastSyncAt] = await Promise.all([
        SecureStore.getItemAsync('syncQueue'),
        SecureStore.getItemAsync('syncErrors'),
        SecureStore.getItemAsync('lastSyncAt'),
      ]);
      const queue = queueData ? JSON.parse(queueData) : [];
      const errors = errorsData ? JSON.parse(errorsData) : {};
      const pendingCount = await getSyncQueueCount();

      set({ syncQueue: queue, syncErrors: errors, pendingCount, lastSyncAt, hydrated: true });
    } catch {
      set({ syncQueue: [], syncErrors: {}, lastSyncAt: null, hydrated: true });
    }
  },
}));
