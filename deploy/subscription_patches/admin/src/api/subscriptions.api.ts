import type {
  AdminSubscription,
  AdminSubscriptionsListResponse,
  PatchSubscriptionStatusPayload,
} from '../types/subscription.types';
import { apiFetch } from './client';
import { parseJsonResponse, type PaginationMeta } from './parseResponse';

function buildList(
  data: AdminSubscription[] | undefined,
  meta?: PaginationMeta,
  perPage = 15,
): AdminSubscriptionsListResponse {
  const current = meta?.current_page ?? 1;
  const total = meta?.total ?? (Array.isArray(data) ? data.length : 0);
  const last = meta?.last_page ?? 1;
  return {
    current_page: current,
    data: Array.isArray(data) ? data : [],
    first_page_url: null,
    from: total === 0 ? null : (current - 1) * perPage + 1,
    last_page: last,
    last_page_url: null,
    next_page_url: current < last ? String(current + 1) : null,
    path: '/platform/subscriptions',
    per_page: meta?.per_page ?? perPage,
    prev_page_url: current > 1 ? String(current - 1) : null,
    to: total === 0 ? null : (current - 1) * perPage + (Array.isArray(data) ? data.length : 0),
    total,
  };
}

function unwrapSubscriptionDetail(body: unknown): AdminSubscription {
  if (body && typeof body === 'object' && body !== null) {
    const o = body as Record<string, unknown>;
    const inner = o.data;
    if (
      inner &&
      typeof inner === 'object' &&
      !Array.isArray(inner) &&
      ('id' in inner || 'tenant_id' in inner)
    ) {
      return inner as AdminSubscription;
    }
  }
  return body as AdminSubscription;
}

export async function fetchSubscriptionsList(
  page: number,
  perPage = 15,
  opts?: { status?: string; search?: string },
): Promise<
  | { ok: true; list: AdminSubscriptionsListResponse }
  | { ok: false; message: string; unauthorized?: boolean }
> {
  const qs = new URLSearchParams({
    per_page: String(perPage),
    page: String(page),
  });
  if (opts?.status) qs.set('status', opts.status);
  if (opts?.search) qs.set('search', opts.search);
  const res = await apiFetch(`/platform/subscriptions?${qs.toString()}`);
  const out = await parseJsonResponse<AdminSubscription[]>(res);
  if (out.ok === false) {
    return { ok: false, message: out.message, unauthorized: out.unauthorized };
  }
  return { ok: true, list: buildList(out.data, out.meta, perPage) };
}

export async function fetchSubscription(
  subscriptionId: number,
): Promise<
  | { ok: true; subscription: AdminSubscription }
  | { ok: false; message: string; unauthorized?: boolean }
> {
  const res = await apiFetch(`/platform/subscriptions/${encodeURIComponent(String(subscriptionId))}`);
  const out = await parseJsonResponse<Record<string, unknown>>(res);
  if (out.ok === false) {
    return { ok: false, message: out.message, unauthorized: out.unauthorized };
  }
  return { ok: true, subscription: unwrapSubscriptionDetail(out.data) };
}

export async function patchSubscriptionStatus(
  subscriptionId: number,
  payload: PatchSubscriptionStatusPayload,
): Promise<{ ok: true } | { ok: false; message: string; unauthorized?: boolean }> {
  const res = await apiFetch(`/platform/subscriptions/${encodeURIComponent(String(subscriptionId))}`, {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  });
  const out = await parseJsonResponse<Record<string, unknown>>(res);
  if (out.ok === false) {
    return { ok: false, message: out.message, unauthorized: out.unauthorized };
  }
  return { ok: true };
}

export async function cancelSubscription(
  subscriptionId: number,
  reason?: string,
): Promise<{ ok: true } | { ok: false; message: string; unauthorized?: boolean }> {
  const res = await apiFetch(`/platform/subscriptions/${encodeURIComponent(String(subscriptionId))}/cancel`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ cancellation_reason: reason ?? '' }),
  });
  const out = await parseJsonResponse<Record<string, unknown>>(res);
  if (out.ok === false) {
    return { ok: false, message: out.message, unauthorized: out.unauthorized };
  }
  return { ok: true };
}
