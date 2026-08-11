/**
 * QR Code Parser — signed v3/v4/v5 contract support.
 *
 * Contract version (`v`) is deliberately independent from template version
 * (`tpl_v`). Unknown versions and fields are rejected before capture.
 */
import type { QRPayload } from '@/types/scan';
import { parseCardPageGeometry } from './geometry-contract.ts';

export interface QRPayloadV2 extends QRPayload {}

function parseGeometry(value: unknown): number[] | undefined {
  if (!Array.isArray(value) || value.length !== 6) return undefined;
  if (!value.every((item) => Number.isInteger(item) && (item as number) >= 0 && (item as number) <= 10000)) {
    return undefined;
  }
  const geometry = value.map((item) => Math.round(item));
  if (
    geometry[2] <= 0 || geometry[3] <= 0 || geometry[4] <= 0 || geometry[5] <= 0 ||
    geometry[0] + geometry[4] > 10000 || geometry[1] + geometry[4] > 10000
  ) {
    return undefined;
  }

  return geometry;
}

const ALLOWED_FIELDS: Record<number, readonly string[]> = {
  3: ['e', 'c', 'h', 'p', 'v', 'tpl_id', 'tpl_v', 'gab_enc', 'chk'],
  4: ['e', 'c', 'h', 'p', 'pt', 'qs', 'qe', 'v', 'rpp', 'cols', 'tpl', 'tpl_id', 'tpl_v', 'g', 'oc', 'gab_enc', 'pts', 'chk'],
  5: ['e', 'c', 'h', 'p', 'pt', 'qs', 'qe', 'v', 'rpp', 'cols', 'tpl_id', 'tpl_v', 'g', 'oc', 'gab_enc', 'pts', 'chk'],
};

/**
 * Parses a QR Code data string into a structured payload.
 * Supports the explicit v3, v4 and v5 schemas emitted by the platform.
 */
export function parseQRCode(data: string): QRPayloadV2 | null {
  try {
    const parsed = JSON.parse(data);

    if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) {
      return null;
    }

    if (typeof parsed.v !== 'number') return null;
    const version = parsed.v;
    const allowedFields = ALLOWED_FIELDS[version];
    if (!Number.isInteger(version) || !allowedFields) return null;
    if (Object.keys(parsed).some((field) => !allowedFields.includes(field))) return null;
    const signaturePattern = version === 3
      ? /^(?:[a-f0-9]{16}|[a-f0-9]{32})$/
      : (version === 4 ? /^[a-f0-9]{32}$/ : /^(?:[A-Za-z0-9_-]{22}|[a-f0-9]{32})$/);
    if (typeof parsed.chk !== 'string' || !signaturePattern.test(parsed.chk)) return null;

    // Validate required fields (present in ALL modes)
    if (
      !Number.isInteger(parsed.e) || parsed.e < 1 ||
      !Number.isInteger(parsed.c) || parsed.c < 1 ||
      !Number.isInteger(parsed.p) || parsed.p < 1 ||
      !Number.isInteger(parsed.tpl_id) || parsed.tpl_id < 1 ||
      !Number.isInteger(parsed.tpl_v) || parsed.tpl_v < 1 ||
      typeof parsed.h !== 'string' || parsed.h.length < 1 || parsed.h.length > 128
    ) {
      return null;
    }

    const modernGeometry = version >= 4 ? parseCardPageGeometry(parsed) : null;
    const rawGeometry = version >= 4 ? parseGeometry(parsed.g) : undefined;
    if (version === 5 && !modernGeometry) return null;
    if (version === 4) {
      if (!rawGeometry) return null;
      const pageFields = ['pt', 'qs', 'qe', 'rpp'].filter((field) => parsed[field] !== undefined);
      if (pageFields.length !== 0 && pageFields.length !== 4) return null;
      if (parsed.oc !== undefined && (typeof parsed.oc !== 'string' || !/^(?:0|[2-9])+$/.test(parsed.oc))) return null;
      if (parsed.oc !== undefined && pageFields.length === 4 && !modernGeometry) return null;
    }
    if (parsed.cols !== undefined && (!Number.isInteger(parsed.cols) || parsed.cols < 1)) return null;
    if (parsed.tpl !== undefined && (typeof parsed.tpl !== 'string' || parsed.tpl.length < 1 || parsed.tpl.length > 100)) return null;
    if (parsed.gab_enc !== undefined && (typeof parsed.gab_enc !== 'string' || parsed.gab_enc.length < 1)) return null;
    if (parsed.pts !== undefined && !Array.isArray(parsed.pts) && typeof parsed.pts !== 'string') return null;

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
      v: version,
      tpl_id: typeof parsed.tpl_id === 'number' ? parsed.tpl_id : undefined,
      tpl_v: typeof parsed.tpl_v === 'number' ? parsed.tpl_v : undefined,
      cols: typeof parsed.cols === 'number' ? parsed.cols : undefined,
      rpp: typeof parsed.rpp === 'number' ? parsed.rpp : undefined,
      chk: typeof parsed.chk === 'string' ? parsed.chk : undefined,
      // v4 fields. Geometry is public layout data; the encrypted answer key is
      // intentionally opaque to the device and can only be graded by the API.
      g: modernGeometry?.g ?? rawGeometry,
      oc: modernGeometry?.oc ?? (typeof parsed.oc === 'string' && /^(?:0|[2-9])+$/.test(parsed.oc) ? parsed.oc : undefined),
      gab_enc: typeof parsed.gab_enc === 'string' ? parsed.gab_enc : undefined,
      // Do not rebuild this object before sync: v4 HMAC covers every field,
      // including template identifiers that the scanner does not otherwise use.
      signedPayload: parsed as Record<string, unknown>,
      // Computed metadata
      legacyQr: version === 3,
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
 * Full v4/v5 cards have no plaintext answer key. Their signed geometry is enough to
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
