import { useEffect, useState } from "react";
import { isModuleLive } from "@/config/feature-flags";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Skeleton } from "@/components/ui/skeleton";
import { sessionStore, useSession } from "@/shared/lib/auth/session.store";
import type {
  SubscriptionOverview,
  SubscriptionPaymentGateway,
  SubscriptionPlanOption,
} from "@/features/subscriptions/types/subscription.types";
import {
  getSubscriptionOverviewMock,
  renewSubscriptionMock,
} from "@/features/subscriptions/services/subscription.mock.service";
import {
  getSubscriptionOverview,
  listSubscriptionPaymentGateways,
  renewSubscription,
  submitSubscriptionChangeRequest,
  cancelSubscription,
} from "@/features/subscriptions/services/subscription.api.service";
import { Check, Crown, RefreshCw, Sparkles } from "lucide-react";
import { formatNumber } from "@/shared/lib/format/numbers";
import { SettingsSubNav } from "@/components/shared/SettingsSubNav";

function fetchOverview() {
  return isModuleLive("subscription") ? getSubscriptionOverview() : getSubscriptionOverviewMock();
}

function renewPlan(payload = {}) {
  return isModuleLive("subscription") ? renewSubscription(payload) : renewSubscriptionMock(payload);
}

const statusLabels = {
  active: { label: "نشط", variant: "success" as const },
  expired: { label: "منتهي", variant: "destructive" as const },
  grace: { label: "فترة سماح", variant: "warning" as const },
  cancelled: { label: "ملغي", variant: "secondary" as const },
};

const planActionLabels: Record<string, string> = {
  current: "الباقة الحالية",
  renew: "تجديد الباقة",
  upgrade: "ترقية الباقة",
  downgrade: "تخفيض الباقة",
  select: "اختيار الباقة",
};

const accountTypeLabels = {
  free: "مجاني",
  paid: "مدفوع",
};

const gatewayTypeLabels: Record<string, string> = {
  bank: "تحويل بنكي",
  vodafone_cash: "فودافون كاش",
  instapay: "انستاباي",
  orange_cash: "أورنج كاش",
  etisalat_cash: "اتصالات كاش",
  fawry: "فوري",
  other: "أخرى",
};

function PlanCard({
  plan,
  loading,
  onSelect,
}: {
  plan: SubscriptionPlanOption;
  loading: boolean;
  onSelect: (code: string) => void;
}) {
  const isFree = plan.account_type === "free";

  return (
    <Card className={plan.is_current ? "border-blue-500 ring-2 ring-blue-100" : ""}>
      <CardHeader>
        <div className="flex items-center justify-between gap-2">
          <CardTitle className="text-lg font-black">{plan.name}</CardTitle>
          {plan.is_current && <Badge variant="info">الحالية</Badge>}
        </div>
        <CardDescription>{plan.description}</CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        <div>
          <p className="text-3xl font-black" style={{ color: "var(--color-text-primary)" }}>
            {plan.price === 0 ? "مجاناً" : `${formatNumber(plan.price)} ${plan.currency}`}
          </p>
          {plan.billing_period_days && (
            <p className="text-xs text-muted-foreground">/ {plan.billing_period_days} يوم</p>
          )}
        </div>
        <ul className="space-y-2">
          {plan.features.map((feature) => (
            <li key={feature} className="flex items-center gap-2 text-sm">
              <Check className="h-4 w-4 text-emerald-600 shrink-0" />
              <span>{feature}</span>
            </li>
          ))}
        </ul>
        <Button
          className="w-full"
          variant={plan.is_current ? "outline" : isFree ? "secondary" : "default"}
          disabled={plan.is_current || loading || plan.action === "current"}
          onClick={() => onSelect(plan.code)}
        >
          {planActionLabels[plan.action ?? (plan.is_current ? "current" : isFree ? "renew" : "select")] ?? "اختيار الباقة"}
        </Button>
      </CardContent>
    </Card>
  );
}

export function SubscriptionPage() {
  const sessionSubscription = useSession((state) => state.subscription);
  const [data, setData] = useState<SubscriptionOverview | null>(null);
  const [loading, setLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState(false);
  const [message, setMessage] = useState<string | null>(null);
  const [paymentOpen, setPaymentOpen] = useState(false);
  const [selectedPlan, setSelectedPlan] = useState<SubscriptionPlanOption | null>(null);
  const [gateways, setGateways] = useState<SubscriptionPaymentGateway[]>([]);
  const [selectedGatewayId, setSelectedGatewayId] = useState<string>("");
  const [paymentReference, setPaymentReference] = useState("");
  const [paymentProof, setPaymentProof] = useState<File | null>(null);
  const [cancelOpen, setCancelOpen] = useState(false);
  const [cancelReason, setCancelReason] = useState("");

  const loadOverview = () => {
    setLoading(true);
    fetchOverview()
      .then((response) => setData(response.data))
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    loadOverview();
  }, []);

  const subscription = data?.subscription ?? sessionSubscription;
  const status = subscription ? statusLabels[subscription.lifecycle_status] : null;

  const syncSessionSubscription = (next: SubscriptionOverview["subscription"]) => {
    const session = sessionStore.getState();
    sessionStore.setSession({
      token: session.token,
      tenant: session.tenant,
      user: session.user,
      permissions: session.permissions,
      subscription: next,
    });
  };

  const completeChangeRequest = async (planCode: string, gatewayId: number) => {
    if (!paymentProof) throw new Error("يرجى إرفاق صورة إيصال التحويل");
    const response = await submitSubscriptionChangeRequest({
      plan_code: planCode,
      payment_gateway_id: gatewayId,
      payment_reference: paymentReference.trim(),
      payment_proof: paymentProof,
    });
    setMessage(response.message);
    setPaymentOpen(false);
    setSelectedPlan(null);
    setPaymentReference("");
    setPaymentProof(null);
    loadOverview();
  };

  const handlePlanSelect = async (planCode: string) => {
    setActionLoading(true);
    setMessage(null);
    try {
      const plan = data?.available_plans.find((item) => item.code === planCode);
      if (!plan) return;

      if (plan.account_type === "free") {
        const response = await renewPlan({ extension_days: plan.billing_period_days ?? 30 });
        syncSessionSubscription(response.data);
        setMessage(response.message);
        loadOverview();
        return;
      }

      if (!isModuleLive("subscription")) {
        setMessage("وضع العرض التجريبي: الترقية تتطلب API حقيقي");
        return;
      }

      const rows = await listSubscriptionPaymentGateways();
      setGateways(rows);
      setSelectedPlan(plan);
      setSelectedGatewayId("");
      setPaymentReference("");
      setPaymentProof(null);
      setPaymentOpen(true);
    } catch (error) {
      setMessage(error instanceof Error ? error.message : "حدث خطأ");
    } finally {
      setActionLoading(false);
    }
  };

  const handleConfirmPayment = async () => {
    if (!selectedPlan || !selectedGatewayId || !paymentReference.trim() || !paymentProof) return;
    setActionLoading(true);
    setMessage(null);
    try {
      await completeChangeRequest(selectedPlan.code, Number(selectedGatewayId));
    } catch (error) {
      setMessage(error instanceof Error ? error.message : "حدث خطأ");
    } finally {
      setActionLoading(false);
    }
  };

  const handleCancelSubscription = async () => {
    setActionLoading(true);
    try {
      const response = await cancelSubscription(cancelReason);
      setMessage(response.message);
      setCancelOpen(false);
      loadOverview();
    } catch (error) {
      setMessage(error instanceof Error ? error.message : "حدث خطأ");
    } finally {
      setActionLoading(false);
    }
  };

  return (
    <div className="w-full max-w-full space-y-5 overflow-x-hidden" dir="rtl">
      <div className="flex items-center gap-3">
        <div
          className="w-11 h-11 rounded-xl flex items-center justify-center shrink-0"
          style={{ background: "linear-gradient(135deg, #6366F1, #818CF8)" }}
        >
          <Crown className="w-5 h-5 text-white" />
        </div>
        <div>
          <h1 className="text-xl font-black">الاشتراك والباقات</h1>
          <p className="text-sm text-muted-foreground">إدارة حالة الحساب والترقية بين الباقات</p>
        </div>
      </div>

      <SettingsSubNav />

      <div className="rounded-xl border bg-white p-5 shadow-sm space-y-4" style={{ borderColor: "var(--color-border)" }}>
        <h2 className="font-black text-base pb-2 border-b" style={{ borderColor: "var(--color-border)" }}>الاشتراك الحالي</h2>
          {!subscription || loading ? (
            <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
              {Array.from({ length: 4 }).map((_, index) => (
                <Skeleton key={index} className="h-20 w-full" />
              ))}
            </div>
          ) : (
            <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
              <div className="rounded-xl border p-4">
                <p className="text-xs text-muted-foreground mb-1">نوع الحساب</p>
                <div className="flex items-center gap-2">
                  {subscription.account_type === "paid" ? (
                    <Sparkles className="h-4 w-4 text-amber-500" />
                  ) : (
                    <RefreshCw className="h-4 w-4 text-sky-500" />
                  )}
                  <p className="font-black">{accountTypeLabels[subscription.account_type]}</p>
                </div>
              </div>
              <div className="rounded-xl border p-4">
                <p className="text-xs text-muted-foreground mb-1">الباقة</p>
                <p className="font-black">{subscription.plan_name}</p>
              </div>
              <div className="rounded-xl border p-4">
                <p className="text-xs text-muted-foreground mb-1">الحالة</p>
                {status && <Badge variant={status.variant}>{status.label}</Badge>}
              </div>
              <div className="rounded-xl border p-4">
                <p className="text-xs text-muted-foreground mb-1">تاريخ الانتهاء</p>
                <p className="font-black">{subscription.expires_at ?? "—"}</p>
                {subscription.days_remaining != null && (
                  <p className="text-xs text-muted-foreground mt-1">
                    متبقي {subscription.days_remaining} يوم
                  </p>
                )}
              </div>
            </div>
          )}

          {data?.pending_change_request ? (
            <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
              {data.pending_change_request.message}
            </div>
          ) : null}

          {subscription?.can_cancel && subscription.lifecycle_status !== "cancelled" ? (
            <div className="mt-4">
              <Button variant="outline" className="text-rose-600 border-rose-200" onClick={() => setCancelOpen(true)}>
                إلغاء الاشتراك
              </Button>
            </div>
          ) : null}
      </div>

      {message && (
        <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
          {message}
        </div>
      )}

      <div>
        <h2 className="text-base font-black mb-4">الباقات المتاحة</h2>
        <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
          {loading
            ? Array.from({ length: 4 }).map((_, index) => <Skeleton key={index} className="h-72 w-full" />)
            : (data?.available_plans ?? []).map((plan) => (
                <PlanCard
                  key={plan.code}
                  plan={plan}
                  loading={actionLoading}
                  onSelect={handlePlanSelect}
                />
              ))}
        </div>
      </div>

      <Dialog open={paymentOpen} onOpenChange={setPaymentOpen}>
        <DialogContent className="sm:max-w-lg" dir="rtl">
          <DialogHeader>
            <DialogTitle>إتمام الدفع — {selectedPlan?.name}</DialogTitle>
            <DialogDescription>
              اختر بوابة الدفع، أدخل رقم التحويل، وأرفق صورة الإيصال. سيتم مراجعة الطلب من الإدارة.
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-3 max-h-72 overflow-y-auto">
            {(gateways.length > 0 ? gateways : []).map((gateway) => (
              <button
                key={gateway.id}
                type="button"
                onClick={() => setSelectedGatewayId(gateway.id)}
                className={`w-full text-right rounded-xl border p-4 transition-colors ${
                  selectedGatewayId === gateway.id ? "border-blue-500 bg-blue-50" : "border-border"
                }`}
              >
                <div className="flex items-center justify-between gap-2">
                  <span className="font-bold">{gateway.name}</span>
                  <Badge variant="outline">{gatewayTypeLabels[gateway.type] ?? gateway.type}</Badge>
                </div>
                <p className="text-sm text-muted-foreground mt-1">{gateway.account_holder}</p>
                <p className="text-sm font-mono mt-1" dir="ltr">{gateway.account_number}</p>
                {gateway.instructions ? (
                  <p className="text-xs text-muted-foreground mt-2">{gateway.instructions}</p>
                ) : null}
              </button>
            ))}
            {gateways.length === 0 && (
              <p className="text-sm text-muted-foreground text-center py-4">
                لا توجد بوابات دفع مفعّلة. يرجى التواصل مع الإدارة.
              </p>
            )}
          </div>

          <div className="space-y-3">
            <label className="text-sm font-semibold">رقم التحويل / المحفظة</label>
            <input
              type="text"
              value={paymentReference}
              onChange={(e) => setPaymentReference(e.target.value)}
              className="w-full rounded-lg border px-3 py-2 text-sm"
              placeholder="رقم العملية أو المحفظة التي دفعت منها"
            />
            <label className="text-sm font-semibold">صورة إيصال التحويل</label>
            <input
              type="file"
              accept="image/*,.pdf"
              onChange={(e) => setPaymentProof(e.target.files?.[0] ?? null)}
              className="w-full text-sm"
            />
          </div>

          <DialogFooter className="gap-2 sm:gap-0">
            <Button variant="outline" onClick={() => setPaymentOpen(false)} disabled={actionLoading}>
              إلغاء
            </Button>
            <Button
              onClick={handleConfirmPayment}
              disabled={actionLoading || !selectedGatewayId || !paymentReference.trim() || !paymentProof}
            >
              {actionLoading ? "جاري الإرسال..." : "إرسال للمراجعة"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={cancelOpen} onOpenChange={setCancelOpen}>
        <DialogContent className="sm:max-w-md" dir="rtl">
          <DialogHeader>
            <DialogTitle>إلغاء الاشتراك</DialogTitle>
            <DialogDescription>سيتم إيقاف التجديد الحالي مع الاحتفاظ ببياناتك.</DialogDescription>
          </DialogHeader>
          <textarea
            value={cancelReason}
            onChange={(e) => setCancelReason(e.target.value)}
            className="w-full rounded-lg border px-3 py-2 text-sm min-h-24"
            placeholder="سبب الإلغاء (اختياري)"
          />
          <DialogFooter>
            <Button variant="outline" onClick={() => setCancelOpen(false)}>تراجع</Button>
            <Button variant="destructive" onClick={() => void handleCancelSubscription()} disabled={actionLoading}>
              تأكيد الإلغاء
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
