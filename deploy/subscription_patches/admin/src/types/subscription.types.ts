import type { AdminPlan } from './plan.types';

/** Matches backend subscription statuses. */
export type SubscriptionStatusValue = 'pending' | 'active' | 'rejected' | 'cancelled';

/** List + nested plan from GET /admin/subscriptions */
export type AdminSubscription = {
  id: number;
  tenant_id: string;
  plan_id: number;
  status: SubscriptionStatusValue | string;
  starts_at: string;
  ends_at: string;
  created_at: string;
  updated_at: string;
  plan: AdminPlan;
};

export type AdminSubscriptionsListResponse = {
  current_page: number;
  data: AdminSubscription[];
  first_page_url: string | null;
  from: number | null;
  last_page: number;
  last_page_url: string | null;
  next_page_url: string | null;
  path: string;
  per_page: number;
  prev_page_url: string | null;
  to: number | null;
  total: number;
};

/** Payment row nested under GET /admin/subscriptions/:id */
export type SubscriptionPayment = {
  id: number;
  subscription_id: number;
  plan_id: number;
  plan_title: string;
  tenant_id: string;
  starts_at: string;
  ends_at: string;
  price: string;
  paid_at: string;
  status: string;
  created_at: string;
  updated_at: string;
};

/** GET /admin/subscriptions/:id */
export type AdminSubscriptionDetail = AdminSubscription & {
  payments: SubscriptionPayment[];
};

export type PatchSubscriptionStatusPayload = {
  status: SubscriptionStatusValue | string;
};
