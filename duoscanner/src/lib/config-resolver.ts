/**
 * Config Resolver — resolves which template and scan mode to use
 * based on the effective config from the Platform.
 *
 * This is the mobile-side equivalent of ConfigPrecedenceResolver.
 * It reads the cached config and makes local decisions:
 *   - Which layout geometry to use for OMR processing
 *   - Which data acquisition strategy to follow (preloaded, qr, hybrid)
 */
import { useConfigStore, type AnswerSheetTypeConfig, type ScanModeConfig } from '@/store/config-store';

export type ScanModeSlug = 'preloaded' | 'qr_embedded' | 'hybrid';
export type AnswerSheetTypeSlug = 'essential' | 'detailed';

export interface ResolvedScanConfig {
  answerSheetType: AnswerSheetTypeSlug;
  scanMode: ScanModeSlug;
  layoutConfig: Record<string, any> | null;
  gradingConfig: Record<string, any> | null;
  requiresPredownload: boolean;
  requiresQrData: boolean;
  offlineCapable: boolean;
}

/**
 * Returns the resolved scan configuration for the current user.
 * Falls back to Essential + Hybrid if no config is available.
 */
export function getResolvedConfig(): ResolvedScanConfig {
  const { effective, answerSheetTypeConfig, scanModeConfig } = useConfigStore.getState();

  const answerSheetType = (effective?.answer_sheet_type ?? 'essential') as AnswerSheetTypeSlug;
  const scanMode = (effective?.scan_mode ?? 'hybrid') as ScanModeSlug;

  return {
    answerSheetType,
    scanMode,
    layoutConfig: answerSheetTypeConfig?.layout_config ?? null,
    gradingConfig: answerSheetTypeConfig?.grading_config ?? null,
    requiresPredownload: scanModeConfig?.requires_predownload ?? true,
    requiresQrData: scanModeConfig?.requires_qr_data ?? true,
    offlineCapable: scanModeConfig?.offline_capable ?? true,
  };
}

/**
 * Determines data acquisition strategy based on scan mode.
 */
export function getDataStrategy(
  scanMode: ScanModeSlug,
  hasCache: boolean,
  isOnline: boolean
): 'use_cache' | 'download_on_demand' | 'use_qr_fallback' | 'error_no_data' {
  switch (scanMode) {
    case 'preloaded':
      if (hasCache) return 'use_cache';
      if (isOnline) return 'download_on_demand';
      return 'error_no_data';

    case 'qr_embedded':
      return 'use_qr_fallback'; // Always uses QR data

    case 'hybrid':
      if (hasCache) return 'use_cache';
      if (isOnline) return 'download_on_demand';
      return 'use_qr_fallback'; // Fallback to QR when offline without cache

    default:
      return hasCache ? 'use_cache' : 'error_no_data';
  }
}

/**
 * Returns the OMR layout dimensions based on the answer sheet type config.
 * Used by the OMR engine to map bubble positions.
 */
export function getLayoutDimensions(config: ResolvedScanConfig) {
  const layout = config.layoutConfig;
  if (!layout) {
    // Fallback: Essential defaults
    return {
      columns: 2,
      rowsPerColumn: 20,
      maxOptions: 5,
      bubbleDiameterMm: 5.5,
      fiducialSizeMm: 6.0,
      areaWidthMm: 186.0,
      areaHeightMm: 240.0,
      colBubbleStartMm: [25.0, 110.0],
      colBubbleEndMm: [85.0, 170.0],
      gridTopOffsetMm: 20.0,
      disciplineHeaderHeightMm: 0,
      maxQuestions: 40,
    };
  }

  return {
    columns: layout.columns ?? 2,
    rowsPerColumn: layout.rows_per_column ?? 20,
    maxOptions: layout.max_options ?? 5,
    bubbleDiameterMm: layout.bubble_diameter_mm ?? 5.5,
    fiducialSizeMm: layout.fiducial_size_mm ?? 6.0,
    areaWidthMm: layout.area_width_mm ?? 186.0,
    areaHeightMm: layout.area_height_mm ?? 240.0,
    colBubbleStartMm: layout.col_bubble_start_mm ?? [25.0, 110.0],
    colBubbleEndMm: layout.col_bubble_end_mm ?? [85.0, 170.0],
    gridTopOffsetMm: layout.grid_top_offset_mm ?? 20.0,
    disciplineHeaderHeightMm: layout.discipline_header_height_mm ?? 0,
    maxQuestions: layout.max_questions ?? 40,
  };
}
