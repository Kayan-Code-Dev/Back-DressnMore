import type {
  AdminPlan,
  AdminPlansListResponse,
  CreatePlanPayload,
  PlanFeatureDefinition,
  UpdatePlanPayload,
} from '../types/plan.types';
import { apiFetch } from './client';
import { parseJsonResponse, type PaginationMeta } from './parseResponse';

function unwrapPlanResource(body: unknown): AdminPlan {
  if (body && typeof body === 'object' && body !== null) {
    const o = body as Record<string, unknown>;
    const inner = o.data;
    if (
      inner &&
      typeof inner === 'object' &&
      !Array.isArray(inner) &&
      ('id' in inner || 'title' in inner || 'name' in inner)
    ) {
      return inner as AdminPlan;
    }
  }
  return body as AdminPlan;
}

function buildList(
  data: AdminPlan[] | undefined,
  meta?: PaginationMeta,
  perPage = 15,
): AdminPlansListResponse {
  return {
    data: Array.isArray(data) ? data : [],
    current_page: meta?.current_page ?? 1,
    per_page: meta?.per_page ?? perPage,
    total: meta?.total ?? (Array.isArray(data) ? data.length : 0),
    last_page: meta?.last_page ?? 1,
  };
}

export async function fetchFeatureCatalog(): Promise<
  | { ok: true; features: PlanFeatureDefinition[] }
  | { ok: false; message: string; unauthorized?: boolean }
> {
  const res = await apiFetch('/platform/plans/feature-catalog');
  const out = await parseJsonResponse<{ features: PlanFeatureDefinition[] }>(res);
  if (out.ok === false) {
    return { ok: false, message: out.message, unauthorized: out.unauthorized };
  }
  return { ok: true, features: out.data?.features ?? [] };
}

export async function fetchPlansList(
  page: number,
  perPage = 15,
): Promise<
  | { ok: true; list: AdminPlansListResponse }
  | { ok: false; message: string; unauthorized?: boolean }
> {
  const res = await apiFetch(`/platform/plans?per_page=${perPage}&page=${page}`);
  const out = await parseJsonResponse<AdminPlan[]>(res);
  if (out.ok === false) {
    return { ok: false, message: out.message, unauthorized: out.unauthorized };
  }
  return { ok: true, list: buildList(out.data, out.meta, perPage) };
}

export async function fetchActivePlans(): Promise<
  | { ok: true; plans: AdminPlan[] }
  | { ok: false; message: string; unauthorized?: boolean }
> {
  const res = await apiFetch('/platform/plans?per_page=100&page=1&status=active');
  const out = await parseJsonResponse<AdminPlan[]>(res);
  if (out.ok === false) {
    return { ok: false, message: out.message, unauthorized: out.unauthorized };
  }
  return { ok: true, plans: Array.isArray(out.data) ? out.data.filter((p) => p.is_active) : [] };
}

export async function fetchPlan(
  planId: number,
): Promise<
  | { ok: true; plan: AdminPlan }
  | { ok: false; message: string; unauthorized?: boolean }
> {
  const res = await apiFetch(`/platform/plans/${encodeURIComponent(String(planId))}`);
  const out = await parseJsonResponse<Record<string, unknown>>(res);
  if (out.ok === false) {
    return { ok: false, message: out.message, unauthorized: out.unauthorized };
  }
  return { ok: true, plan: unwrapPlanResource(out.data) };
}

export async function createPlan(
  payload: CreatePlanPayload,
): Promise<{ ok: true } | { ok: false; message: string; unauthorized?: boolean }> {
  const res = await apiFetch('/platform/plans', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  });
  const out = await parseJsonResponse<Record<string, unknown>>(res);
  if (out.ok === false) {
    return { ok: false, message: out.message, unauthorized: out.unauthorized };
  }
  return { ok: true };
}

export async function updatePlan(
  planId: number,
  payload: UpdatePlanPayload,
): Promise<{ ok: true } | { ok: false; message: string; unauthorized?: boolean }> {
  const res = await apiFetch(`/platform/plans/${encodeURIComponent(String(planId))}`, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  });
  const out = await parseJsonResponse<Record<string, unknown>>(res);
  if (out.ok === false) {
    return { ok: false, message: out.message, unauthorized: out.unauthorized };
  }
  return { ok: true };
}

export async function deletePlan(
  planId: number,
): Promise<{ ok: true } | { ok: false; message: string; unauthorized?: boolean }> {
  const res = await apiFetch(`/platform/plans/${encodeURIComponent(String(planId))}`, {
    method: 'DELETE',
  });
  const out = await parseJsonResponse<Record<string, unknown>>(res);
  if (out.ok === false) {
    return { ok: false, message: out.message, unauthorized: out.unauthorized };
  }
  return { ok: true };
}
