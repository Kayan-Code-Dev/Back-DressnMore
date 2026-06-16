import { clearLocalSession } from '../lib/session';

export function extractErr(data: Record<string, unknown>): string {
  const msg = data.message;
  if (typeof msg === 'string') return msg;
  if (Array.isArray(msg)) return msg.filter((x) => typeof x === 'string').join(', ');
  const errors = data.errors;
  if (errors && typeof errors === 'object') {
    const flat = Object.values(errors as Record<string, unknown>).flatMap((v) =>
      Array.isArray(v) ? v : [v],
    );
    const first = flat.find((x) => typeof x === 'string') as string | undefined;
    if (first) return first;
  }
  return '';
}

export type PaginationMeta = {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
};

/**
 * Parse JSON from a Response and unwrap the platform envelope
 * `{ success, message, data, meta? }`.
 *
 * Falls back to treating the whole body as `T` when the envelope
 * keys are absent (backward compat with older endpoints).
 */
export async function parseJsonResponse<T>(
  res: Response,
): Promise<
  | { ok: true; data: T; meta?: PaginationMeta }
  | { ok: false; message: string; unauthorized?: boolean }
> {
  let raw: Record<string, unknown> = {};
  try {
    const text = await res.text();
    if (text) raw = JSON.parse(text) as Record<string, unknown>;
  } catch {
    /* ignore */
  }

  if (res.status === 401 || res.status === 403) {
    clearLocalSession();
    return { ok: false, message: extractErr(raw) || 'Unauthorized', unauthorized: true };
  }

  if (!res.ok || raw.success === false) {
    return { ok: false, message: extractErr(raw) || `HTTP ${res.status}` };
  }

  const hasEnvelope = 'success' in raw && 'data' in raw;
  const data = hasEnvelope ? (raw.data as T) : (raw as T);
  const meta =
    hasEnvelope && raw.meta && typeof raw.meta === 'object'
      ? (raw.meta as PaginationMeta)
      : undefined;

  return { ok: true, data, meta };
}
