/**
 * Config Store — manages effective configuration from the Platform.
 *
 * Fetches from GET /api/v2/config/effective and caches locally in SQLite.
 * The Duoscanner NEVER decides autonomously which gabarito or mode to use;
 * it always consumes the config resolved by the Platform.
 */
import { create } from 'zustand';
import api from '@/services/api';
import { getConfig, setConfig } from '@/db/database';

export interface AnswerSheetTypeConfig {
  slug: string;
  name: string;
  layout_config: Record<string, any>;
  grading_config: Record<string, any>;
  version: number;
}

export interface ScanModeConfig {
  slug: string;
  name: string;
  requires_predownload: boolean;
  requires_qr_data: boolean;
  offline_capable: boolean;
}

export interface EffectiveConfig {
  answer_sheet_type: string;
  answer_sheet_type_resolved_from: string;
  scan_mode: string;
  scan_mode_resolved_from: string;
}

export interface ConfigState {
  effective: EffectiveConfig | null;
  answerSheetTypeConfig: AnswerSheetTypeConfig | null;
  scanModeConfig: ScanModeConfig | null;
  configVersion: number;
  resolvedAt: string | null;
  isLoading: boolean;
  error: string | null;
  hydrated: boolean;

  // Actions
  fetchEffectiveConfig: () => Promise<void>;
  hydrateFromCache: () => Promise<void>;
  clear: () => void;
}

export const useConfigStore = create<ConfigState>((set, get) => ({
  effective: null,
  answerSheetTypeConfig: null,
  scanModeConfig: null,
  configVersion: 0,
  resolvedAt: null,
  isLoading: false,
  error: null,
  hydrated: false,

  fetchEffectiveConfig: async () => {
    set({ isLoading: true, error: null });
    try {
      // Use v2 API for config
      const baseURL = api.defaults.baseURL?.replace('/v1', '/v2') ?? '';
      const response = await api.get(`${baseURL}/config/effective`);
      const data = response.data;

      const state = {
        effective: data.effective,
        answerSheetTypeConfig: data.answer_sheet_type_config,
        scanModeConfig: data.scan_mode_config,
        configVersion: data.config_version,
        resolvedAt: data.resolved_at,
        isLoading: false,
        error: null,
      };

      set(state);

      // Persist to SQLite cache
      await setConfig('effective', JSON.stringify(data.effective), data.config_version);
      await setConfig('answer_sheet_type_config', JSON.stringify(data.answer_sheet_type_config), data.config_version);
      await setConfig('scan_mode_config', JSON.stringify(data.scan_mode_config), data.config_version);
      await setConfig('config_version', String(data.config_version), data.config_version);
    } catch (err: any) {
      const message = err.response?.data?.error || err.message || 'Erro ao carregar configuração';
      set({ isLoading: false, error: message });

      // If failed to fetch, try to hydrate from cache
      if (!get().effective) {
        await get().hydrateFromCache();
      }
    }
  },

  hydrateFromCache: async () => {
    try {
      const effectiveRow = await getConfig('effective');
      const sheetTypeRow = await getConfig('answer_sheet_type_config');
      const scanModeRow = await getConfig('scan_mode_config');
      const versionRow = await getConfig('config_version');

      if (effectiveRow) {
        set({
          effective: JSON.parse(effectiveRow.value),
          answerSheetTypeConfig: sheetTypeRow ? JSON.parse(sheetTypeRow.value) : null,
          scanModeConfig: scanModeRow ? JSON.parse(scanModeRow.value) : null,
          configVersion: versionRow ? parseInt(versionRow.value, 10) : 0,
          hydrated: true,
        });
      } else {
        // No cache: use system defaults
        set({
          effective: {
            answer_sheet_type: 'essential',
            answer_sheet_type_resolved_from: 'system_default',
            scan_mode: 'hybrid',
            scan_mode_resolved_from: 'system_default',
          },
          hydrated: true,
        });
      }
    } catch {
      set({
        effective: {
          answer_sheet_type: 'essential',
          answer_sheet_type_resolved_from: 'system_default',
          scan_mode: 'hybrid',
          scan_mode_resolved_from: 'system_default',
        },
        hydrated: true,
      });
    }
  },

  clear: () => {
    set({
      effective: null,
      answerSheetTypeConfig: null,
      scanModeConfig: null,
      configVersion: 0,
      resolvedAt: null,
      isLoading: false,
      error: null,
      hydrated: false,
    });
  },
}));
