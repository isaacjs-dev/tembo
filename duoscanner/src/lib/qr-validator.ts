/**
 * QR Validator — validates HMAC and structural integrity of QR payloads.
 *
 * NOTE: Full HMAC validation requires the server-side tenant key and is
 * performed at sync time. On the device, we do structural validation only and
 * preserve the exact signed payload for deferred verification.
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

  if (![3, 4, 5].includes(qr.v ?? -1)) {
    errors.push('Versão do contrato QR não suportada. Use um cartão v3, v4 ou v5.');
  }
  if (!qr.tpl_id || qr.tpl_id < 1 || !qr.tpl_v || qr.tpl_v < 1) {
    errors.push('Identificação/versionamento do template OMR ausente ou inválido.');
  }

  // All supported contracts are signed. An unsigned code must never be
  // downgraded into a legacy path on the device.
  const hasHmac = !!qr.chk && qr.chk.length > 0;
  if (!hasHmac) {
    errors.push('QR Code sem autenticador (chk).');
  }

  // Layout version check
  if (qr.v === undefined) {
    warnings.push('Versão de layout não especificada. Usando configuração padrão.');
  }

  if (qr.v !== undefined && qr.v >= 4) {
    if (!qr.g || qr.g.length !== 6 || qr.g.some((item) => !Number.isFinite(item))) {
      errors.push('Geometria do cartão (g) ausente ou inválida. Gere o cartão novamente.');
    }
    if (!qr.oc || !/^[0-9]+$/.test(qr.oc)) {
      errors.push('Contagem de alternativas (oc) ausente ou inválida. Gere o cartão novamente.');
    }
    if (!qr.rpp || qr.rpp < 1 || !qr.qs || !qr.qe || !qr.pt) {
      errors.push('Metadados de página (pt/qs/qe/rpp) ausentes ou inválidos.');
    }
  }

  // Page validation
  if (qr.p !== undefined && qr.pt !== undefined) {
    if (qr.p < 1 || qr.p > qr.pt) {
      errors.push(`Página ${qr.p} fora do intervalo (1-${qr.pt}).`);
    }
    if (qr.pt > 1) {
      errors.push('Este build do aplicativo aceita somente cartões de uma página. Use a leitura Web para avaliações multipágina.');
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
  templateVersion: number | undefined,
  cachedTemplateVersion: number | null
): { hasConflict: boolean; message: string | null } {
  if (templateVersion === undefined || cachedTemplateVersion === null) {
    return { hasConflict: false, message: null };
  }

  if (templateVersion !== cachedTemplateVersion) {
    return {
      hasConflict: true,
      message: `Conflito de template: folha impressa (v${templateVersion}) ≠ template local (v${cachedTemplateVersion}). Atualize o cache ou use os dados assinados do QR.`,
    };
  }

  return { hasConflict: false, message: null };
}
