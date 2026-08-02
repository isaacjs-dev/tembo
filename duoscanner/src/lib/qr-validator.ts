/**
 * QR Validator — validates HMAC and structural integrity of QR payloads.
 *
 * NOTE: Full HMAC validation requires the server-side secret key and is
 * performed at sync time. On the device, we do structural validation only.
 * The `hmac_verified` flag in the scan record tracks this.
 */
import type { QRPayloadV2 } from './qr-parser';

export interface QRValidationResult {
  isValid: boolean;
  hasHmac: boolean;
  hmacVerifiedLocally: boolean; // Always false — real validation is server-side
  errors: string[];
  warnings: string[];
}

/**
 * Validates the structural integrity of a QR payload.
 * Does NOT verify HMAC (that requires the server key).
 */
export function validateQRPayload(qr: QRPayloadV2): QRValidationResult {
  const errors: string[] = [];
  const warnings: string[] = [];

  // Required fields
  if (!qr.e || qr.e <= 0) errors.push('exam_id (e) ausente ou inválido');
  if (!qr.c || qr.c <= 0) errors.push('copy_id (c) ausente ou inválido');
  if (!qr.h || qr.h.length === 0) errors.push('validation_hash (h) ausente');

  // HMAC presence check
  const hasHmac = !!qr.chk && qr.chk.length > 0;
  if (!hasHmac) {
    warnings.push('QR Code sem HMAC checksum (chk). Integridade será verificada no servidor.');
  }

  // Layout version check
  if (qr.v === undefined) {
    warnings.push('Versão de layout não especificada. Usando configuração padrão.');
  }

  // Gabarito embedded data validation (for qr_embedded/hybrid modes)
  if (qr.gab) {
    if (!/^[0-4]+$/.test(qr.gab)) {
      errors.push('Gabarito compacto (gab) contém caracteres inválidos. Esperado: dígitos 0-4.');
    }

    const expectedLength = (qr.qe ?? 0) - (qr.qs ?? 1) + 1;
    if (expectedLength > 0 && qr.gab.length !== expectedLength) {
      warnings.push(`Tamanho do gabarito (${qr.gab.length}) difere do esperado (${expectedLength}).`);
    }
  }

  // Points validation
  if (qr.pts) {
    if (!/^[1-9]+$/.test(qr.pts)) {
      errors.push('Pontos (pts) contém caracteres inválidos. Esperado: dígitos 1-9.');
    }
    if (qr.gab && qr.pts.length !== qr.gab.length) {
      warnings.push(`Tamanho dos pontos (${qr.pts.length}) difere do gabarito (${qr.gab.length}).`);
    }
  }

  // Page validation
  if (qr.p !== undefined && qr.pt !== undefined) {
    if (qr.p < 1 || qr.p > qr.pt) {
      errors.push(`Página ${qr.p} fora do intervalo (1-${qr.pt}).`);
    }
  }

  return {
    isValid: errors.length === 0,
    hasHmac,
    hmacVerifiedLocally: false, // Always false — server verifies
    errors,
    warnings,
  };
}

/**
 * Detects version conflicts between QR data and cached exam data.
 */
export function detectVersionConflict(
  qrVersion: number | undefined,
  cachedVersion: number | null
): { hasConflict: boolean; message: string | null } {
  if (qrVersion === undefined || cachedVersion === null) {
    return { hasConflict: false, message: null };
  }

  if (qrVersion !== cachedVersion) {
    return {
      hasConflict: true,
      message: `Conflito de versão: folha impressa (v${qrVersion}) ≠ cache local (v${cachedVersion}). Atualize a prova ou use os dados do QR.`,
    };
  }

  return { hasConflict: false, message: null };
}
