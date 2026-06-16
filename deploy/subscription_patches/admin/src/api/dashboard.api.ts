import { apiFetch } from './client';
import { parseJsonResponse } from './parseResponse';

export type SubscriptionDashboardStats = {
  total_subscriptions: number;
  active_subscriptions: number;
  expired_subscriptions: number;
  cancelled_subscriptions: number;
  pending_plan_requests: number;
  total_subscription_revenue: string;
  pending_payments: number;
  failed_payments: number;
  refunded_payments: number;
};

export async function fetchSubscriptionDashboardStats(): Promise<
  | { ok: true; stats: SubscriptionDashboardStats }
  | { ok: false; message: string; unauthorized?: boolean }
> {
  const res = await apiFetch('/platform/dashboard/subscription-stats');
  const out = await parseJsonResponse<SubscriptionDashboardStats>(res);
  if (out.ok === false) {
    return { ok: false, message: out.message, unauthorized: out.unauthorized };
  }
  return { ok: true, stats: out.data };
}
