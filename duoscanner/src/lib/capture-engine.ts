/**
 * Capture Engine — validates image quality before capture.
 *
 * Checks:
 * - Edge detection (paper boundaries visible)
 * - Fiducial markers detected (≥3 of 4)
 * - Focus quality (Laplacian variance)
 * - Tilt angle (< 15°)
 * - Lighting (brightness between 80-220)
 *
 * Auto-capture triggers when qualityScore ≥ 0.75.
 */

export interface PreCaptureValidation {
  edgesDetected: boolean;
  markersDetected: boolean;
  markerCount: number;
  orientationOk: boolean;
  focusOk: boolean;
  focusScore: number;        // Laplacian variance
  tiltOk: boolean;
  tiltAngle: number;          // degrees
  lightingOk: boolean;
  brightness: number;         // 0-255
  qualityScore: number;       // 0-1, weighted average
}

/**
 * Threshold constants for pre-capture validation.
 */
const THRESHOLDS = {
  FOCUS_MIN_VARIANCE: 100,       // Laplacian variance threshold
  TILT_MAX_DEGREES: 15,          // Max acceptable tilt
  BRIGHTNESS_MIN: 80,            // Min acceptable brightness
  BRIGHTNESS_MAX: 220,           // Max acceptable brightness (avoid overexposure)
  MIN_MARKERS: 3,                // Min fiducial markers detected
  AUTO_CAPTURE_QUALITY: 0.75,    // Quality score threshold for auto-capture
} as const;

/**
 * Weights for quality score calculation.
 */
const WEIGHTS = {
  edges: 0.20,
  markers: 0.25,
  focus: 0.25,
  tilt: 0.15,
  lighting: 0.15,
} as const;

/**
 * Evaluates pre-capture quality from frame analysis data.
 *
 * NOTE: The actual image analysis (edge detection, Laplacian, etc.)
 * is performed by the native OMR processor or OpenCV bridge.
 * This function evaluates the results and produces a unified quality score.
 */
export function evaluatePreCapture(frameData: {
  edgeCount: number;
  markerPositions: { x: number; y: number }[];
  laplacianVariance: number;
  tiltAngle: number;
  meanBrightness: number;
}): PreCaptureValidation {
  const edgesDetected = frameData.edgeCount >= 4; // 4 edges of paper
  const markerCount = frameData.markerPositions.length;
  const markersDetected = markerCount >= THRESHOLDS.MIN_MARKERS;
  const orientationOk = markerCount >= 2; // At least 2 markers to determine orientation

  const focusScore = frameData.laplacianVariance;
  const focusOk = focusScore >= THRESHOLDS.FOCUS_MIN_VARIANCE;

  const tiltAngle = Math.abs(frameData.tiltAngle);
  const tiltOk = tiltAngle <= THRESHOLDS.TILT_MAX_DEGREES;

  const brightness = frameData.meanBrightness;
  const lightingOk =
    brightness >= THRESHOLDS.BRIGHTNESS_MIN &&
    brightness <= THRESHOLDS.BRIGHTNESS_MAX;

  // Calculate weighted quality score
  const scores = {
    edges: edgesDetected ? 1.0 : 0.0,
    markers: Math.min(markerCount / 4, 1.0),
    focus: Math.min(focusScore / (THRESHOLDS.FOCUS_MIN_VARIANCE * 2), 1.0),
    tilt: tiltOk ? 1.0 - tiltAngle / THRESHOLDS.TILT_MAX_DEGREES : 0.0,
    lighting: lightingOk
      ? 1.0 - Math.abs(brightness - 150) / 150 // Best at ~150
      : 0.0,
  };

  const qualityScore =
    scores.edges * WEIGHTS.edges +
    scores.markers * WEIGHTS.markers +
    scores.focus * WEIGHTS.focus +
    scores.tilt * WEIGHTS.tilt +
    scores.lighting * WEIGHTS.lighting;

  return {
    edgesDetected,
    markersDetected,
    markerCount,
    orientationOk,
    focusOk,
    focusScore,
    tiltOk,
    tiltAngle,
    lightingOk,
    brightness,
    qualityScore: Math.round(qualityScore * 100) / 100,
  };
}

/**
 * Determines if auto-capture should trigger.
 */
export function shouldAutoCapture(validation: PreCaptureValidation): boolean {
  return validation.qualityScore >= THRESHOLDS.AUTO_CAPTURE_QUALITY;
}

/**
 * Returns user-facing guidance messages based on current validation state.
 */
export function getCaptureGuidance(validation: PreCaptureValidation): string[] {
  const messages: string[] = [];

  if (!validation.edgesDetected) {
    messages.push('📄 Posicione a folha dentro da moldura');
  }
  if (!validation.markersDetected) {
    messages.push('🔲 Marcadores não detectados. Ajuste o enquadramento');
  }
  if (!validation.focusOk) {
    messages.push('🔍 Mantenha o celular parado para melhorar o foco');
  }
  if (!validation.tiltOk) {
    messages.push(`📐 Endireite o celular (inclinação: ${Math.round(validation.tiltAngle)}°)`);
  }
  if (!validation.lightingOk) {
    if (validation.brightness < THRESHOLDS.BRIGHTNESS_MIN) {
      messages.push('💡 Melhore a iluminação');
    } else {
      messages.push('💡 Reduza o brilho (reflexo excessivo)');
    }
  }

  if (messages.length === 0) {
    messages.push('✅ Pronto para captura');
  }

  return messages;
}

export { THRESHOLDS };
