import { apiFetch } from './client';
import { parseJsonResponse, type PaginationMeta } from './parseResponse';

export type AdminPayment = {
  id: number;
  tenant_id: number;
  plan_id: number | null;
  plan_request_id: number | null;
  order_reference: string | null;
  amount: string;
  currency: string;
  currency_symbol: string;
  method: string | null;
  reference: string | null;
  proof_url: string | null;
  status: string;
  paid_at: string | null;
  notes: string | null;
  created_at: string;
  tenant?: { id: number; name: string; slug: string } | null;
  plan?: { id: number; title: string; slug: string } | null;
  payment_gateway?: { id: number; name: string; type: string } | null;
};

export type AdminPaymentsListResponse = {
  current_page: number;
  data: AdminPayment[];
  per_page: number;
  total: number;
  last_page: number;
};

function buildList(
  data: AdminPayment[] | undefined,
  meta?: PaginationMeta,
  perPage = 15,
): AdminPaymentsListResponse {
  return {
    current_page: meta?.current_page ?? 1,
    data: Array.isArray(data) ? data : [],
    per_page: meta?.per_page ?? perPage,
    total: meta?.total ?? (Array.isArray(data) ? data.length : 0),
    last_page: meta?.last_page ?? 1,
  };
}

export async function fetchPaymentsList(
  page = 1,
  perPage = 15,
  opts?: { status?: string; search?: string },
): Promise<
  | { ok: true; list: AdminPaymentsListResponse }
  | { ok: false; message: string; unauthorized?: boolean }
> {
  const qs = new URLSearchParams({ page: String(page), per_page: String(perPage) });
  if (opts?.status) qs.set('status', opts.status);
  if (opts?.search) qs.set('search', opts.search);
  const res = await apiFetch(`/platform/payments?${qs.toString()}`);
  const out = await parseJsonResponse<AdminPayment[]>(res);
  if (out.ok === false) {
    return { ok: false, message: out.message, unauthorized: out.unauthorized };
  }
  return { ok: true, list: buildList(out.data, out.meta, perPage) };
}

export async function markPaymentPaid(
  id: number,
): Promise<{ ok: true } | { ok: false; message: string; unauthorized?: boolean }> {
  const res = await apiFetch(`/platform/payments/${id}/mark-paid`, { method: 'POST' });
  const out = await parseJsonResponse<unknown>(res);
  if (out.ok === false) {
    return { ok: false, message: out.message, unauthorized: out.unauthorized };
  }
  return { ok: true };
}

export async function rejectPayment(
  id: number,
  notes?: string,
): Promise<{ ok: true } | { ok: false; message: string; unauthorized?: boolean }> {
  const res = await apiFetch(`/platform/payments/${id}/reject`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ notes: notes ?? '' }),
  });
  const out = await parseJsonResponse<unknown>(res);
  if (out.ok === false) {
    return { ok: false, message: out.message, unauthorized: out.unauthorized };
  }
  return { ok: true };
}

export async function refundPayment(
  id: number,
  notes?: string,
): Promise<{ ok: true } | { ok: false; message: string; unauthorized?: boolean }> {
  const res = await apiFetch(`/platform/payments/${id}/refund`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ notes: notes ?? '' }),
  });
  const out = await parseJsonResponse<unknown>(res);
  if (out.ok === false) {
    return { ok: false, message: out.message, unauthorized: out.unauthorized };
  }
  return { ok: true };
}
