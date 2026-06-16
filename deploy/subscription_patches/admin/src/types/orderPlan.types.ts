import type { AdminPlan } from './plan.types';

export type OrderPlanStatusValue = 'pending' | 'payment_submitted' | 'approved' | 'rejected';

/** Row from GET /platform/order-plans */
export type AdminOrderPlan = {
  id: number;
  request_type?: string;
  name: string;
  phone: string;
  email: string;
  plan_id: number;
  old_plan_id?: number | null;
  status: OrderPlanStatusValue | string;
  tenant_id: string;
  subscription_id: number;
  payment_reference?: string | null;
  payment_proof_url?: string | null;
  payment_submitted_at?: string | null;
  payment_status?: string | null;
  amount?: string | null;
  currency?: string | null;
  billing_cycle?: string | null;
  created_at: string;
  updated_at: string;
  plan: AdminPlan;
  old_plan?: { id: number; title: string; slug?: string } | null;
  source_tenant?: { id: number; name: string; slug: string } | null;
};

export type AdminOrderPlansListResponse = {
  current_page: number;
  data: AdminOrderPlan[];
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

/** GET /admin/order-plans/:id */
export type AdminOrderPlanDetail = AdminOrderPlan;

export type PatchOrderPlanStatusPayload = {
  status: OrderPlanStatusValue | string;
};
