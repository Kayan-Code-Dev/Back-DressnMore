import { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';
import StatusBadge, { PlanBadge } from '../../../components/base/StatusBadge';
import { fetchOrderPlan, patchOrderPlanStatus } from '../../../api/orderPlans.api';
import type { AdminOrderPlanDetail, OrderPlanStatusValue } from '../../../types/orderPlan.types';

const STATUS_OPTIONS: OrderPlanStatusValue[] = ['approved', 'rejected'];

interface OrderPlanDetailModalProps {
  orderPlanId: number | null;
  onClose: () => void;
  onUpdated: () => void;
  onShowCredentials?: (email: string, password: string, tenantName?: string) => void; // Add this
}


export default function OrderPlanDetailModal({
  orderPlanId,
  onClose,
  onUpdated,
  onShowCredentials, // Add this
}: OrderPlanDetailModalProps) {

  const { t } = useTranslation();
  const navigate = useNavigate();
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [detail, setDetail] = useState<AdminOrderPlanDetail | null>(null);
  const [statusDraft, setStatusDraft] = useState<OrderPlanStatusValue>('approved');

  useEffect(() => {
    if (orderPlanId == null) {
      setDetail(null);
      setError('');
      return;
    }
    let cancelled = false;
    setLoading(true);
    setError('');
    void fetchOrderPlan(orderPlanId).then((r) => {
      if (cancelled) return;
      setLoading(false);
      if (r.ok === false) {
        if (r.unauthorized) {
          navigate('/admin/login', { replace: true });
          return;
        }
        setError(r.message);
        setDetail(null);
        return;
      }
      setDetail(r.orderPlan);
      const st = r.orderPlan.status as OrderPlanStatusValue;
      setStatusDraft(STATUS_OPTIONS.includes(st) ? st : 'approved');
    });
    return () => {
      cancelled = true;
    };
  }, [orderPlanId, navigate]);

  if (orderPlanId == null) return null;

  const handleSaveStatus = async () => {
    if (!detail || statusDraft === detail.status) return;
    setSaving(true);
    setError('');
    const r = await patchOrderPlanStatus(detail.id, { status: statusDraft });
    setSaving(false);
    if (r.ok === false) {
      if (r.unauthorized) {
        navigate('/admin/login', { replace: true });
        return;
      }
      setError(r.message);
      return;
    }
    if (statusDraft === 'approved' && r.data.admin) {
      onShowCredentials?.(
        r.data.admin.email,
        r.data.admin.password,
        r.data.tenant?.name || r.data.hostname_label
      );
    }
    onUpdated();
    onClose();
  };

  return (
    <div
      className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4 overflow-y-auto"
      onClick={onClose}
    >
      <div
        className="bg-white rounded-2xl w-full max-w-lg my-4 shadow-xl"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
          <h3 className="text-base font-bold text-gray-900">{t('order_plans.detail.title')}</h3>
          <button
            type="button"
            onClick={onClose}
            className="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 text-gray-400 cursor-pointer transition-colors"
          >
            <i className="ri-close-line text-lg" />
          </button>
        </div>

        <div className="p-6 space-y-4">
          {loading ? (
            <div className="flex flex-col items-center gap-3 py-12 text-gray-500">
              <i className="ri-loader-4-line text-3xl text-teal-600 animate-spin" />
              <p className="text-sm">{t('order_plans.loading')}</p>
            </div>
          ) : error ? (
            <p className="text-sm text-rose-600 bg-rose-50 rounded-lg px-3 py-2">{error}</p>
          ) : detail ? (
            <>
              <div className="rounded-xl bg-gray-50 ring-1 ring-gray-100 p-4 space-y-3">
                <p className="text-xs font-semibold text-gray-500 uppercase tracking-wide">
                  {t('order_plans.detail.customer')}
                </p>
                <p className="text-lg font-bold text-gray-900">{detail.name}</p>
                <div className="grid gap-2 text-sm text-gray-700">
                  <p className="flex items-center gap-2">
                    <i className="ri-mail-line text-gray-400" />
                    <span className="break-all">{detail.email}</span>
                  </p>
                  <p className="flex items-center gap-2">
                    <i className="ri-phone-line text-gray-400" />
                    {detail.phone}
                  </p>
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3 text-sm">
                <div>
                  <p className="text-xs text-gray-400 font-medium">{t('order_plans.col.tenant_id')}</p>
                  <p className="text-gray-900 font-mono text-xs break-all">{detail.tenant_id}</p>
                </div>
                <div>
                  <p className="text-xs text-gray-400 font-medium">{t('order_plans.col.subscription_id')}</p>
                  <p className="text-gray-900 font-mono">{detail.subscription_id}</p>
                </div>
              </div>

              <div className="rounded-xl border border-gray-100 p-4">
                <p className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                  {t('order_plans.detail.plan_section')}
                </p>
                <div className="flex flex-wrap items-center gap-2">
                  <PlanBadge plan={detail.plan?.title} />
                  <span className="text-sm text-gray-600">
                    ${detail.plan?.price ?? '-'} · {detail.plan?.days ?? '-'} {t('plans.duration_days')}
                  </span>
                </div>
                <p className="text-sm text-gray-500 mt-2 leading-relaxed">{detail.plan?.description ?? ''}</p>
              </div>

              {(detail.payment_reference || detail.payment_proof_url) ? (
                <div className="rounded-xl border border-amber-100 bg-amber-50/50 p-4 space-y-3">
                  <p className="text-xs font-semibold text-amber-800 uppercase tracking-wide">
                    إثبات الدفع
                  </p>
                  {detail.payment_reference ? (
                    <p className="text-sm text-gray-800">
                      <span className="font-medium text-gray-500">رقم الدفع:</span>{' '}
                      <span className="font-mono" dir="ltr">{detail.payment_reference}</span>
                    </p>
                  ) : null}
                  {detail.payment_submitted_at ? (
                    <p className="text-xs text-gray-500">
                      تاريخ الإرسال: {detail.payment_submitted_at}
                    </p>
                  ) : null}
                  {detail.payment_proof_url ? (
                    <a
                      href={detail.payment_proof_url}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="inline-flex items-center gap-2 text-sm font-semibold text-teal-700 hover:text-teal-800"
                    >
                      <i className="ri-image-line" />
                      عرض صورة إيصال التحويل
                    </a>
                  ) : null}
                  {detail.payment_proof_url ? (
                    <img
                      src={detail.payment_proof_url}
                      alt="إيصال التحويل"
                      className="max-h-56 rounded-xl border border-gray-200 object-contain bg-white"
                    />
                  ) : null}
                  <p className="text-xs text-amber-900 leading-relaxed">
                    تأكد من استلام التحويل قبل الموافقة على الطلب وتفعيل الاشتراك.
                  </p>
                </div>
              ) : null}

              <div className="grid grid-cols-2 gap-3 text-sm">
                <div>
                  <p className="text-xs text-gray-400 font-medium">{t('order_plans.col.created_at')}</p>
                  <p className="text-gray-800">{detail.created_at}</p>
                </div>
                <div>
                  <p className="text-xs text-gray-400 font-medium">{t('order_plans.col.updated_at')}</p>
                  <p className="text-gray-800">{detail.updated_at}</p>
                </div>
              </div>

              <div className="border-t border-gray-100 pt-4">
                <p className="text-xs text-gray-400 font-medium mb-2">{t('order_plans.detail.status')}</p>
                <div className="flex flex-wrap items-center gap-3">
                  <StatusBadge status={detail.status} />
                  <select
                    value={statusDraft}
                    onChange={(e) => setStatusDraft(e.target.value as OrderPlanStatusValue)}
                    disabled={saving}
                    className="text-sm border border-gray-200 rounded-lg px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-teal-500/30"
                  >
                    {STATUS_OPTIONS.map((s) => (
                      <option key={s} value={s}>
                        {t(`status.${s}`)}
                      </option>
                    ))}
                  </select>
                  <button
                    type="button"
                    disabled={saving || statusDraft === detail.status}
                    onClick={() => void handleSaveStatus()}
                    className="text-sm px-3 py-2 rounded-lg bg-teal-600 text-white font-medium hover:bg-teal-700 disabled:opacity-40 cursor-pointer"
                  >
                    {saving ? t('order_plans.detail.saving') : t('order_plans.detail.save_status')}
                  </button>
                </div>
              </div>
            </>
          ) : null}
        </div>

        <div className="px-6 pb-5">
          <button
            type="button"
            onClick={onClose}
            className="w-full py-2.5 rounded-lg border border-gray-200 text-sm font-medium text-gray-700 hover:bg-gray-50 cursor-pointer"
          >
            {t('actions.close')}
          </button>
        </div>
      </div>
    </div>
  );
}
