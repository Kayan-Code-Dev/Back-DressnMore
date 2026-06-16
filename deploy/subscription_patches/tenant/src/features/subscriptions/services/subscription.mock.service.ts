import type { ApiSuccess } from "@/shared/types/api";
import type {
  RenewSubscriptionPayload,
  SubscriptionOverview,
  TenantSubscription,
  UpgradeSubscriptionPayload,
} from "@/features/subscriptions/types/subscription.types";
import { subscriptionOverviewFixture } from "@/features/subscriptions/mocks/subscription.mock.data";

const delay = (ms: number) => new Promise((resolve) => setTimeout(resolve, ms));

let currentOverview = structuredClone(subscriptionOverviewFixture);

export async function getSubscriptionOverviewMock(): Promise<ApiSuccess<SubscriptionOverview>> {
  await delay(200);
  return {
    success: true,
    message: "Success",
    data: structuredClone(currentOverview),
    meta: null,
  };
}

export async function renewSubscriptionMock(
  payload: RenewSubscriptionPayload = {},
): Promise<ApiSuccess<TenantSubscription>> {
  await delay(300);
  const extensionDays = payload.extension_days ?? 30;
  const baseDate = currentOverview.subscription.expires_at
    ? new Date(currentOverview.subscription.expires_at)
    : new Date();

  if (baseDate < new Date()) {
    baseDate.setTime(Date.now());
  }
  baseDate.setDate(baseDate.getDate() + extensionDays);

  currentOverview.subscription = {
    ...currentOverview.subscription,
    account_type: "free",
    lifecycle_status: "active",
    plan_code: "free",
    plan_name: "مجاني",
    expires_at: baseDate.toISOString().slice(0, 10),
    can_renew: true,
    days_remaining: extensionDays,
  };

  currentOverview.available_plans = currentOverview.available_plans.map((plan) => ({
    ...plan,
    is_current: plan.code === "free",
  }));

  return {
    success: true,
    message: "تم تجديد الاشتراك المجاني",
    data: structuredClone(currentOverview.subscription),
    meta: null,
  };
}

export async function upgradeSubscriptionMock(
  payload: UpgradeSubscriptionPayload,
): Promise<ApiSuccess<TenantSubscription>> {
  await delay(300);
  const selected = currentOverview.available_plans.find((plan) => plan.code === payload.plan_code);

  if (!selected) {
    throw new Error("الباقة المختارة غير موجودة");
  }

  const expiresAt = selected.billing_period_days
    ? new Date(Date.now() + selected.billing_period_days * 86400000).toISOString().slice(0, 10)
    : null;

  currentOverview.subscription = {
    account_type: selected.account_type,
    lifecycle_status: "active",
    plan_code: selected.code,
    plan_name: selected.name,
    starts_at: new Date().toISOString().slice(0, 10),
    expires_at: expiresAt,
    can_renew: selected.account_type === "free",
    days_remaining: selected.billing_period_days,
  };

  currentOverview.available_plans = currentOverview.available_plans.map((plan) => ({
    ...plan,
    is_current: plan.code === selected.code,
  }));

  return {
    success: true,
    message: selected.account_type === "paid" ? "تم تفعيل الباقة المدفوعة" : "تم تحديث الباقة",
    data: structuredClone(currentOverview.subscription),
    meta: null,
  };
}
