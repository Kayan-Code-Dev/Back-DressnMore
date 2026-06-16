import { httpClient } from "@/shared/lib/http/client";
import { tenantPath } from "@/config/api";
import type { ApiSuccess } from "@/shared/types/api";
import type {
  RenewSubscriptionPayload,
  SubscriptionChangeRequestPayload,
  SubscriptionOverview,
  SubscriptionPaymentGateway,
  TenantSubscription,
} from "@/features/subscriptions/types/subscription.types";

export async function getSubscriptionOverview(): Promise<ApiSuccess<SubscriptionOverview>> {
  const response = await httpClient.get<SubscriptionOverview>(tenantPath("/subscription/overview"));
  if (!response.success) throw new Error(response.message);
  return response as ApiSuccess<SubscriptionOverview>;
}

export async function listSubscriptionPaymentGateways(): Promise<SubscriptionPaymentGateway[]> {
  const response = await httpClient.get<SubscriptionPaymentGateway[]>(
    tenantPath("/subscription/payment-gateways"),
  );
  if (!response.success) throw new Error(response.message);
  return response.data;
}

export async function renewSubscription(
  payload: RenewSubscriptionPayload = {},
): Promise<ApiSuccess<TenantSubscription>> {
  const response = await httpClient.post<TenantSubscription>(
    tenantPath("/subscription/renew"),
    payload,
  );
  if (!response.success) throw new Error(response.message);
  return response;
}

export async function submitSubscriptionChangeRequest(
  payload: SubscriptionChangeRequestPayload,
): Promise<ApiSuccess<{ request_id: number; message: string }>> {
  const form = new FormData();
  form.append("plan_code", payload.plan_code);
  form.append("payment_gateway_id", String(payload.payment_gateway_id));
  form.append("payment_reference", payload.payment_reference);
  if (payload.tenant_notes) form.append("tenant_notes", payload.tenant_notes);
  form.append("payment_proof", payload.payment_proof);

  const response = await httpClient.post<{ request_id: number; message: string }>(
    tenantPath("/subscription/change-request"),
    form,
  );
  if (!response.success) throw new Error(response.message);
  return response as ApiSuccess<{ request_id: number; message: string }>;
}

export async function cancelSubscription(
  reason?: string,
): Promise<ApiSuccess<{ message: string }>> {
  const response = await httpClient.post<{ message: string }>(
    tenantPath("/subscription/cancel"),
    { reason: reason ?? "" },
  );
  if (!response.success) throw new Error(response.message);
  return response as ApiSuccess<{ message: string }>;
}
