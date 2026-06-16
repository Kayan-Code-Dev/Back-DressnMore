import type { PaymentGateway } from "../mocks/paymentGateways";
import { apiFetch } from "./client";
import { parseJsonResponse } from "./parseResponse";

export async function listPaymentGateways(): Promise<PaymentGateway[]> {
  const response = await apiFetch("/platform/payment-gateways?per_page=100");
  const body = await parseJsonResponse<{ data?: PaymentGateway[]; list?: { data?: PaymentGateway[] } }>(response);
  if (body.ok === false) return [];
  const data = body.data;
  if (Array.isArray(data)) return data;
  if (data && typeof data === "object") {
    if (Array.isArray((data as any).data)) return (data as any).data;
    if (Array.isArray((data as any).list?.data)) return (data as any).list.data;
  }
  return [];
}

export async function createPaymentGateway(
  payload: Omit<PaymentGateway, "id" | "createdAt" | "usageCount">,
): Promise<PaymentGateway> {
  const response = await apiFetch("/platform/payment-gateways", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload),
  });
  return parseJsonResponse<PaymentGateway>(response) as any;
}

export async function updatePaymentGateway(
  id: string,
  payload: Partial<Omit<PaymentGateway, "id" | "createdAt" | "usageCount">>,
): Promise<PaymentGateway> {
  const response = await apiFetch(`/platform/payment-gateways/${id}`, {
    method: "PUT",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload),
  });
  return parseJsonResponse<PaymentGateway>(response) as any;
}

export async function deletePaymentGateway(id: string): Promise<void> {
  await apiFetch(`/platform/payment-gateways/${id}`, { method: "DELETE" });
}
