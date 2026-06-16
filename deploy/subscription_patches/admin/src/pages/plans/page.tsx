import { useState, useEffect, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import AdminLayout from '../../components/feature/AdminLayout';
import PlanCard from './components/PlanCard';
import type { PlanCardColorScheme } from './components/PlanCard';
import PlanFormModal from './components/PlanFormModal';
import { createPlan, deletePlan, fetchPlansList, updatePlan } from '../../api/plans.api';
import type { AdminPlan } from '../../types/plan.types';

const CARD_SCHEMES: PlanCardColorScheme[] = ['teal', 'slate', 'amber'];

export default function PlansPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const [plans, setPlans] = useState<AdminPlan[]>([]);
  const [loading, setLoading] = useState(true);
  const [listError, setListError] = useState('');
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);

  const [formOpen, setFormOpen] = useState(false);
  const [editingPlan, setEditingPlan] = useState<AdminPlan | null>(null);
  const [formSaving, setFormSaving] = useState(false);
  const [formError, setFormError] = useState('');

  const [togglingId, setTogglingId] = useState<number | null>(null);

  const loadPlans = useCallback(async () => {
    setListError('');
    setLoading(true);
    const r = await fetchPlansList(page, 100);
    setLoading(false);
    if (r.ok === false) {
      if (r.unauthorized) {
        navigate('/admin/login', { replace: true });
        return;
      }
      setListError(r.message);
      return;
    }
    setPlans(r.list.data ?? []);
    setLastPage(Math.max(1, r.list.last_page));
    setTotal(r.list.total);
  }, [navigate, page]);

  useEffect(() => {
    void loadPlans();
  }, [loadPlans]);

  const activePlans = plans.filter((p) => p.is_active).length;
  const inactivePlans = plans.filter((p) => !p.is_active).length;

  const handleEdit = (plan: AdminPlan) => {
    setFormError('');
    setEditingPlan(plan);
    setFormOpen(true);
  };

  const handleAddNew = () => {
    setFormError('');
    setEditingPlan(null);
    setFormOpen(true);
  };

  const handleDelete = async (planId: number) => {
    const r = await deletePlan(planId);
    if (r.ok === false) {
      if (r.unauthorized) {
        navigate('/admin/login', { replace: true });
        return;
      }
      setListError(r.message);
      return;
    }
    await loadPlans();
  };

  const handleToggleActive = async (plan: AdminPlan) => {
    setTogglingId(plan.id);
    const r = await updatePlan(plan.id, {
      title: plan.title,
      description: plan.description,
      days: String(plan.days),
      price: String(plan.price),
      is_active: !plan.is_active,
      billing_cycle: plan.billing_cycle ?? 'both',
      monthly_price: plan.monthly_price ?? undefined,
      yearly_price: plan.yearly_price ?? undefined,
      yearly_discount_percent: plan.yearly_discount_percent ?? undefined,
      currency: plan.currency ?? 'SAR',
      max_branches: plan.max_branches ?? undefined,
      max_employees: plan.max_employees ?? undefined,
      max_invoices: plan.max_invoices ?? undefined,
    });
    setTogglingId(null);
    if (r.ok === false) {
      if (r.unauthorized) {
        navigate('/admin/login', { replace: true });
        return;
      }
      setListError(r.message);
      return;
    }
    await loadPlans();
  };

  const handleCreate = async (payload: Parameters<typeof createPlan>[0]) => {
    setFormError('');
    setFormSaving(true);
    const r = await createPlan(payload);
    setFormSaving(false);
    if (r.ok === false) {
      if (r.unauthorized) {
        navigate('/admin/login', { replace: true });
        return;
      }
      setFormError(r.message);
      return;
    }
    setFormOpen(false);
    await loadPlans();
  };

  const handleUpdate = async (
    id: number,
    payload: Parameters<typeof updatePlan>[1],
  ) => {
    setFormError('');
    setFormSaving(true);
    const r = await updatePlan(id, payload);
    setFormSaving(false);
    if (r.ok === false) {
      if (r.unauthorized) {
        navigate('/admin/login', { replace: true });
        return;
      }
      setFormError(r.message);
      return;
    }
    setFormOpen(false);
    await loadPlans();
  };

  const closeForm = () => {
    setFormOpen(false);
    setFormError('');
  };

  return (
    <AdminLayout>
      <div className="flex flex-col gap-6">
        <div className="flex items-center justify-between gap-4 flex-wrap">
          <div className="flex items-center gap-4 flex-wrap">
            <div className="bg-white rounded-xl px-5 py-3 ring-1 ring-gray-100 flex items-center gap-3">
              <div className="w-9 h-9 flex items-center justify-center rounded-lg bg-gray-100">
                <i className="ri-price-tag-3-line text-gray-600" />
              </div>
              <div>
                <p className="text-xs text-gray-400">{t('plans.summary.total')}</p>
                <p className="text-xl font-bold text-gray-900">{loading ? '—' : total}</p>
              </div>
            </div>
            <div className="bg-white rounded-xl px-5 py-3 ring-1 ring-gray-100 flex items-center gap-3">
              <div className="w-9 h-9 flex items-center justify-center rounded-lg bg-emerald-50">
                <i className="ri-checkbox-circle-line text-emerald-600" />
              </div>
              <div>
                <p className="text-xs text-gray-400">{t('plans.summary.active')}</p>
                <p className="text-xl font-bold text-emerald-600">{loading ? '—' : activePlans}</p>
              </div>
            </div>
            <div className="bg-white rounded-xl px-5 py-3 ring-1 ring-gray-100 flex items-center gap-3">
              <div className="w-9 h-9 flex items-center justify-center rounded-lg bg-gray-100">
                <i className="ri-pause-circle-line text-gray-600" />
              </div>
              <div>
                <p className="text-xs text-gray-400">{t('plans.summary.inactive')}</p>
                <p className="text-xl font-bold text-gray-700">{loading ? '—' : inactivePlans}</p>
              </div>
            </div>
          </div>
          <button
            type="button"
            onClick={handleAddNew}
            className="flex items-center gap-2 px-5 py-2.5 bg-teal-600 text-white text-sm font-semibold rounded-xl hover:bg-teal-700 transition-colors cursor-pointer whitespace-nowrap"
          >
            <i className="ri-add-line" />
            {t('plans.add_plan')}
          </button>
        </div>

        {listError ? (
          <div className="rounded-xl bg-rose-50 text-rose-800 text-sm px-4 py-3 ring-1 ring-rose-100">
            {listError}
          </div>
        ) : null}

        {loading ? (
          <div className="flex flex-col items-center justify-center bg-white rounded-2xl ring-1 ring-gray-100 py-24 gap-3">
            <i className="ri-loader-4-line text-3xl text-teal-600 animate-spin" />
            <p className="text-gray-500 text-sm">{t('plans.loading')}</p>
          </div>
        ) : plans.length === 0 ? (
          <div className="flex flex-col items-center justify-center bg-white rounded-2xl ring-1 ring-gray-100 py-24 gap-4">
            <i className="ri-price-tag-3-line text-5xl text-gray-300" />
            <p className="text-gray-500 font-medium">{t('plans.no_plans')}</p>
            <button
              type="button"
              onClick={handleAddNew}
              className="px-4 py-2 bg-teal-600 text-white text-sm font-medium rounded-lg hover:bg-teal-700 cursor-pointer whitespace-nowrap transition-colors"
            >
              {t('plans.add_plan')}
            </button>
          </div>
        ) : (
          <>
            <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
              {plans.map((plan, i) => (
                <PlanCard
                  key={plan.id}
                  plan={plan}
                  colorScheme={CARD_SCHEMES[i % CARD_SCHEMES.length]}
                  onEdit={handleEdit}
                  onDelete={handleDelete}
                  onToggleActive={handleToggleActive}
                  toggleBusy={togglingId === plan.id}
                />
              ))}
            </div>

            {lastPage > 1 ? (
              <div className="flex items-center justify-center gap-4 text-sm text-gray-600">
                <button
                  type="button"
                  disabled={page <= 1}
                  onClick={() => setPage((p) => Math.max(1, p - 1))}
                  className="px-3 py-1.5 rounded-lg border border-gray-200 hover:bg-gray-50 disabled:opacity-40 cursor-pointer"
                >
                  {t('plans.prev_page')}
                </button>
                <span>
                  {t('table.showing')} {page} / {lastPage}
                </span>
                <button
                  type="button"
                  disabled={page >= lastPage}
                  onClick={() => setPage((p) => p + 1)}
                  className="px-3 py-1.5 rounded-lg border border-gray-200 hover:bg-gray-50 disabled:opacity-40 cursor-pointer"
                >
                  {t('plans.next_page')}
                </button>
              </div>
            ) : null}
          </>
        )}
      </div>

      <PlanFormModal
        isOpen={formOpen}
        editingPlan={editingPlan}
        saving={formSaving}
        submitError={formError}
        onClose={closeForm}
        onCreate={handleCreate}
        onUpdate={handleUpdate}
      />
    </AdminLayout>
  );
}
