import type {
  AdminOrderPlanDetail,
  AdminOrderPlansListResponse,
  PatchOrderPlanStatusPayload,
} from '../types/orderPlan.types';
import { apiFetch } from './client';
import { parseJsonResponse, type PaginationMeta } from './parseResponse';

function buildList(
  data: AdminOrderPlanDetail[] | undefined,
  meta?: PaginationMeta,
  perPage = 15,
): AdminOrderPlansListResponse {
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
    path: '/platform/order-plans',
    per_page: meta?.per_page ?? perPage,
    prev_page_url: current > 1 ? String(current - 1) : null,
    to: total === 0 ? null : (current - 1) * perPage + (Array.isArray(data) ? data.length : 0),
    total,
  };
}

export async function fetchOrderPlansList(
  page = 1,
  perPage = 15,
  filters?: { status?: string },
): Promise<
  | { ok: true; list: AdminOrderPlansListResponse }
  | { ok: false; unauthorized?: boolean; message: string }
> {
  const params = new URLSearchParams({
    page: String(page),
    per_page: String(perPage),
  });
  if (filters?.status) params.set('status', filters.status);

  const res = await apiFetch(`/platform/order-plans?${params.toString()}`);
  const out = await parseJsonResponse<AdminOrderPlanDetail[]>(res);
  if (out.ok === false) {
    return { ok: false, message: out.message, unauthorized: out.unauthorized };
  }
  return { ok: true, list: buildList(out.data, out.meta, perPage) };
}

export async function fetchOrderPlan(
  id: number,
): Promise<
  | { ok: true; orderPlan: AdminOrderPlanDetail }
  | { ok: false; unauthorized?: boolean; message: string }
> {
  const res = await apiFetch(`/platform/order-plans/${id}`);
  const out = await parseJsonResponse<AdminOrderPlanDetail>(res);
  if (out.ok === false) {
    return { ok: false, message: out.message, unauthorized: out.unauthorized };
  }
  return { ok: true, orderPlan: out.data };
}

type ApprovePayload = {
  admin?: { email: string; password: string; warning?: string };
  tenant?: { id: number; name: string; slug: string; status?: string };
  hostname_label?: string;
  request?: AdminOrderPlanDetail;
};

export async function patchOrderPlanStatus(
  id: number,
  payload: PatchOrderPlanStatusPayload,
): Promise<
  | { ok: true; data: ApprovePayload }
  | { ok: false; unauthorized?: boolean; message: string }
> {
  const res = await apiFetch(`/platform/order-plans/${id}`, {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  });
  const out = await parseJsonResponse<ApprovePayload>(res);
  if (out.ok === false) {
    return { ok: false, message: out.message, unauthorized: out.unauthorized };
  }
  return { ok: true, data: out.data };
}
