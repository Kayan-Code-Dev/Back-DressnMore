export type SubscriptionAccountType = "free" | "paid";

export type SubscriptionLifecycleStatus = "active" | "expired" | "grace" | "cancelled";

export type TenantSubscription = {
  account_type: SubscriptionAccountType;
  lifecycle_status: SubscriptionLifecycleStatus;
  plan_code: string;
  plan_name: string;
  plan_id?: number | null;
  price?: number;
  currency?: string;
  currency_symbol?: string;
  billing_cycle?: string;
  starts_at: string;
  expires_at: string | null;
  can_renew: boolean;
  can_cancel?: boolean;
  days_remaining: number | null;
  cancelled_at?: string | null;
  cancellation_reason?: string | null;
  enabled_modules?: string[];
  features?: Record<string, string>;
};

export type SubscriptionPlanOption = {
  code: string;
  name: string;
  account_type: SubscriptionAccountType;
  price: number;
  currency: string;
  currency_symbol?: string;
  billing_cycle?: string;
  billing_period_days: number | null;
  description: string;
  features: string[];
  is_current: boolean;
  action?: "current" | "upgrade" | "downgrade" | "renew" | "select";
  recommended?: boolean;
};

export type SubscriptionOverview = {
  subscription: TenantSubscription;
  tenant: {
    id: string;
    name: string;
    slug: string;
  };
  available_plans: SubscriptionPlanOption[];
  pending_change_request?: {
    request_id: number;
    request_type?: string;
    status: string;
    plan_name?: string;
    message: string;
  } | null;
};

export type RenewSubscriptionPayload = {
  extension_days?: number;
};

export type UpgradeSubscriptionPayload = {
  plan_code: string;
  payment_gateway_id?: number;
  mock_payment_confirmed?: boolean;
};

export type SubscriptionChangeRequestPayload = {
  plan_code: string;
  payment_gateway_id: number;
  payment_reference: string;
  payment_proof: File;
  tenant_notes?: string;
};

export type SubscriptionPaymentGateway = {
  id: string;
  name: string;
  type: string;
  account_holder: string;
  account_number: string;
  bank_name?: string | null;
  iban?: string | null;
  instructions?: string | null;
  is_active: boolean;
  display_order: number;
};
