import { isValidGeometryVector, parseCardPageGeometry } from './geometry-contract';

export interface OmrQrPayload extends Record<string, unknown> {
    e: number;
    c: number;
    h: string;
    p: number;
    v: 3 | 4 | 5;
    tpl_id: number;
    tpl_v: number;
    chk: string;
    pt?: number;
    qs?: number;
    qe?: number;
    rpp?: number;
    g?: number[];
    oc?: string;
}

const ALLOWED_FIELDS: Record<number, readonly string[]> = {
    3: ['e', 'c', 'h', 'p', 'v', 'tpl_id', 'tpl_v', 'gab_enc', 'chk'],
    4: ['e', 'c', 'h', 'p', 'pt', 'qs', 'qe', 'v', 'rpp', 'cols', 'tpl', 'tpl_id', 'tpl_v', 'g', 'oc', 'gab_enc', 'pts', 'chk'],
    5: ['e', 'c', 'h', 'p', 'pt', 'qs', 'qe', 'v', 'rpp', 'cols', 'tpl_id', 'tpl_v', 'g', 'oc', 'gab_enc', 'pts', 'chk'],
};

const positiveInteger = (value: unknown): value is number =>
    typeof value === 'number' && Number.isInteger(value) && value > 0;

/**
 * Structural parser shared by the Web worker boundary. Authenticity remains a
 * server responsibility because browsers never receive the tenant HMAC key.
 */
export function parseOmrQrPayload(value: unknown): OmrQrPayload | null {
    if (!value || typeof value !== 'object' || Array.isArray(value)) return null;
    const raw = value as Record<string, unknown>;
    const version = raw.v;
    if (!positiveInteger(version) || !ALLOWED_FIELDS[version]) return null;
    if (Object.keys(raw).some((field) => !ALLOWED_FIELDS[version].includes(field))) return null;
    const signaturePattern = version === 3
        ? /^(?:[a-f0-9]{16}|[a-f0-9]{32})$/
        : (version === 4 ? /^[a-f0-9]{32}$/ : /^(?:[A-Za-z0-9_-]{22}|[a-f0-9]{32})$/);
    if (![raw.e, raw.c, raw.p, raw.tpl_id, raw.tpl_v].every(positiveInteger)) return null;
    if (typeof raw.h !== 'string' || raw.h.length < 1 || raw.h.length > 128) return null;
    if (typeof raw.chk !== 'string' || !signaturePattern.test(raw.chk)) return null;

    if (version >= 4) {
        if (!isValidGeometryVector(raw.g)) return null;
        const pageFields = [raw.pt, raw.qs, raw.qe, raw.rpp];
        const presentPageFields = pageFields.filter((field) => field !== undefined);
        if (presentPageFields.length !== 0 && presentPageFields.length !== 4) return null;
        if (presentPageFields.length === 4 && !pageFields.every(positiveInteger)) return null;
        if (positiveInteger(raw.pt) && (raw.p as number) > raw.pt) return null;
        if (positiveInteger(raw.qs) && positiveInteger(raw.qe) && raw.qs > raw.qe) return null;
        if (raw.oc !== undefined && (typeof raw.oc !== 'string' || !/^(?:0|[2-9])+$/.test(raw.oc))) return null;
    }

    if (raw.cols !== undefined && !positiveInteger(raw.cols)) return null;
    if (raw.tpl !== undefined && (typeof raw.tpl !== 'string' || raw.tpl.length < 1 || raw.tpl.length > 100)) return null;
    if (raw.gab_enc !== undefined && (typeof raw.gab_enc !== 'string' || raw.gab_enc.length < 1)) return null;
    if (raw.pts !== undefined && !Array.isArray(raw.pts) && typeof raw.pts !== 'string') return null;

    if (version === 5 && !parseCardPageGeometry(raw)) return null;

    return raw as OmrQrPayload;
}
