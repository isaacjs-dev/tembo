/**
 * QR Code Parser — v2 with full schema support.
 *
 * Reads all v0 and v1 fields from the QR Code payload, including
 * gabarito compacto (gab), pontos (pts), layout metadata, and HMAC checksum.
 */
import type { QRPayload } from '@/types/scan';

export interface QRPayloadV2 extends QRPayload {
  // v1+ fields for qr_embedded and hybrid modes
  gab?: string;    // compact answer key: each digit = correct option index (0-4)
  pts?: string;    // compact points: each char = weight (1-9)
}

function parseGeometry(value: unknown): number[] | undefined {
  if (!Array.isArray(value) || value.length !== 6) return undefined;
  if (!value.every((item) => typeof item === 'number' && Number.isFinite(item) && item >= 0)) {
    return undefined;
  }
  return value.map((item) => Math.round(item));
}

/**
 * Parses a QR Code data string into a structured payload.
 * Supports both v0 (legacy) and v1+ (modern) schemas.
 */
export function parseQRCode(data: string): QRPayloadV2 | null {
  try {
    const parsed = JSON.parse(data);

    if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) {
      return null;
    }

    // Validate required fields (present in ALL modes)
    if (
      typeof parsed.e !== 'number' ||
      typeof parsed.c !== 'number' ||
      typeof parsed.h !== 'string'
    ) {
      return null;
    }

    const payload: QRPayloadV2 = {
      e: parsed.e,
      c: parsed.c,
      h: parsed.h,
      // Optional v0 fields
      p: typeof parsed.p === 'number' ? parsed.p : undefined,
      pt: typeof parsed.pt === 'number' ? parsed.pt : undefined,
      qs: typeof parsed.qs === 'number' ? parsed.qs : undefined,
      qe: typeof parsed.qe === 'number' ? parsed.qe : undefined,
      // v1 fields
      v: typeof parsed.v === 'number' ? parsed.v : undefined,
      cols: typeof parsed.cols === 'number' ? parsed.cols : undefined,
      rpp: typeof parsed.rpp === 'number' ? parsed.rpp : undefined,
      chk: typeof parsed.chk === 'string' ? parsed.chk : undefined,
      // v1+ embedded data fields
      gab: typeof parsed.gab === 'string' ? parsed.gab : undefined,
      pts: typeof parsed.pts === 'string' ? parsed.pts : undefined,
      // v4 fields. Geometry is public layout data; the encrypted answer key is
      // intentionally opaque to the device and can only be graded by the API.
      g: parseGeometry(parsed.g),
      oc: typeof parsed.oc === 'string' && /^[0-9]+$/.test(parsed.oc) ? parsed.oc : undefined,
      gab_enc: typeof parsed.gab_enc === 'string' ? parsed.gab_enc : undefined,
      // Do not rebuild this object before sync: v4 HMAC covers every field,
      // including template identifiers that the scanner does not otherwise use.
      signedPayload: parsed as Record<string, unknown>,
      // Computed metadata
      legacyQr: parsed.v === undefined || parsed.v === 0,
    };

    return payload;
  } catch {
    return null;
  }
}

/**
 * Validates a QR payload against a locally cached exam.
 */
export function validateQRAgainstExam(
  qr: QRPayloadV2,
  examId: number,
  copies: { id: number; validation_hash: string }[]
): { valid: boolean; copyId?: number; error?: string } {
  if (qr.e !== examId) {
    return { valid: false, error: 'QR Code pertence a outra prova.' };
  }

  const matchingCopy = copies.find(
    (c) => c.id === qr.c && c.validation_hash === qr.h
  );

  if (!matchingCopy) {
    return { valid: false, error: 'Versão da prova não encontrada. Verifique o QR Code.' };
  }

  return { valid: true, copyId: matchingCopy.id };
}

/**
 * Extracts a compact answer key from QR payload.
 * Each digit in `gab` represents the correct option index (0-4) for that question.
 *
 * @example gab="24013" → Q1=C, Q2=E, Q3=A, Q4=B, Q5=D
 */
export function extractAnswerKeyFromQR(qr: QRPayloadV2): Record<number, number> | null {
  if (!qr.gab) return null;

  const startQ = qr.qs ?? 1;
  const answers: Record<number, number> = {};

  for (let i = 0; i < qr.gab.length; i++) {
    const digit = parseInt(qr.gab[i], 10);
    if (isNaN(digit) || digit < 0 || digit > 4) continue;
    answers[startQ + i] = digit;
  }

  return answers;
}

/**
 * Extracts point weights from QR payload.
 * Each char in `pts` represents the weight (1-9) for that question.
 */
export function extractPointsFromQR(qr: QRPayloadV2): Record<number, number> | null {
  if (!qr.pts) return null;

  const startQ = qr.qs ?? 1;
  const points: Record<number, number> = {};

  for (let i = 0; i < qr.pts.length; i++) {
    const digit = parseInt(qr.pts[i], 10);
    if (isNaN(digit) || digit < 1 || digit > 9) {
      points[startQ + i] = 1; // Default weight
    } else {
      points[startQ + i] = digit;
    }
  }

  return points;
}

/**
 * Checks if a QR payload has embedded data sufficient for offline correction.
 */
export function hasEmbeddedData(qr: QRPayloadV2): boolean {
  return !!qr.gab && qr.gab.length > 0;
}

/**
 * v4 cards have no plaintext answer key. Their signed geometry is enough to
 * capture answers offline; final grading remains exclusively on the server.
 */
export function canCaptureOffline(qr: QRPayloadV2): boolean {
  const count = (qr.qe ?? 0) - (qr.qs ?? 1) + 1;
  return !!qr.g
    && qr.g.length === 6
    && !!qr.oc
    && count > 0
    && qr.oc.length === count;
}
