import { useState, useMemo, useEffect, useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';
import CredentialsPopup from '../base/CredentialsPopup';

import {
  useReactTable,
  getCoreRowModel,
  getFilteredRowModel,
  getSortedRowModel,
  createColumnHelper,
  flexRender,
  type SortingState,
} from '@tanstack/react-table';
import StatusBadge, { PlanBadge, ActionIconButton } from '../../../components/base/StatusBadge';
import OrderPlanDetailModal from './OrderPlanDetailModal';
import { fetchOrderPlansList, patchOrderPlanStatus } from '../../../api/orderPlans.api';
import type { AdminOrderPlan, OrderPlanStatusValue } from '../../../types/orderPlan.types';

const PER_PAGE = 15;
const STATUS_OPTIONS: OrderPlanStatusValue[] = ['payment_submitted', 'pending', 'approved', 'rejected'];

type FilterType = 'all' | OrderPlanStatusValue;
const columnHelper = createColumnHelper<AdminOrderPlan>();

export default function OrderPlansTable() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const [rows, setRows] = useState<AdminOrderPlan[]>([]);
  const [loading, setLoading] = useState(true);
  const [listError, setListError] = useState('');
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);

  const [globalFilter, setGlobalFilter] = useState('');
  const [statusFilter, setStatusFilter] = useState<FilterType>('all');
  const [sorting, setSorting] = useState<SortingState>([]);
  const [detailId, setDetailId] = useState<number | null>(null);
  const [patchingId, setPatchingId] = useState<number | null>(null);

  const [credentialsPopup, setCredentialsPopup] = useState<{
    isOpen: boolean;
    email: string;
    password: string;
    tenantName?: string;
  }>({
    isOpen: false,
    email: '',
    password: '',
    tenantName: '',
  });

  const loadList = useCallback(async () => {
    setListError('');
    setLoading(true);
    try {
      const r = await fetchOrderPlansList(page, PER_PAGE, {
        status: statusFilter === 'all' ? undefined : statusFilter,
      });
      setLoading(false);
      if (r.ok === false) {
        if (r.unauthorized) {
          navigate('/admin/login', { replace: true });
          return;
        }
        setListError(r.message);
        setRows([]);
        return;
      }
      let data = r.list.data ?? [];
      if (statusFilter !== 'all') {
        data = data.filter((row) => row.status === statusFilter);
      }
      setRows(data);
      setLastPage(Math.max(1, r.list.last_page));
      setTotal(r.list.total);
    } catch (err) {
      setLoading(false);
      setListError(err instanceof Error ? err.message : 'Failed to load data');
      setRows([]);
    }
  }, [navigate, page, statusFilter]);

  useEffect(() => {
    void loadList();
  }, [loadList]);

  const filteredRows = useMemo(() => {
    const q = globalFilter.trim().toLowerCase();
    if (!q) return rows;
    return rows.filter(
      (r) =>
        String(r.id).includes(q) ||
        String(r.name ?? '').toLowerCase().includes(q) ||
        String(r.email ?? '').toLowerCase().includes(q) ||
        String(r.phone ?? '').toLowerCase().includes(q) ||
        String(r.tenant_id ?? '').toLowerCase().includes(q) ||
        String(r.plan?.title ?? '').toLowerCase().includes(q),
    );
  }, [rows, globalFilter]);

  const handlePatchStatus = useCallback(
    async (id: number, status: string) => {
      setPatchingId(id);
      try {
        const r = await patchOrderPlanStatus(id, { status });
        setPatchingId(null);

        if (r.ok === false) {
          if (r.unauthorized) {
            navigate('/admin/login', { replace: true });
            return;
          }
          setListError(r.message);
          return;
        }

        if (status === 'approved' && r.data.admin) {
          setCredentialsPopup({
            isOpen: true,
            email: r.data.admin.email,
            password: r.data.admin.password,
            tenantName: r.data.tenant?.name || r.data.hostname_label,
          });
        } else {
          await loadList();
        }
      } catch (err) {
        setPatchingId(null);
        setListError(err instanceof Error ? err.message : 'Failed to update status');
      }
    },
    [navigate, loadList],
  );

  const handleCredentialsPopupClose = useCallback(() => {
    setCredentialsPopup(prev => ({ ...prev, isOpen: false }));
    void loadList();
  }, [loadList]);

  const columns = useMemo(
    () => [
      columnHelper.accessor('id', {
        header: () => t('order_plans.col.id'),
        cell: (info) => (
          <span className="text-xs font-mono text-gray-500 bg-gray-100 px-2 py-1 rounded">{info.getValue()}</span>
        ),
      }),
      columnHelper.accessor('request_type', {
        header: () => 'نوع الطلب',
        cell: (info) => {
          const labels: Record<string, string> = {
            signup: 'جديد',
            upgrade: 'ترقية',
            downgrade: 'تخفيض',
            renew: 'تجديد',
          };
          return <span className="text-xs">{labels[info.getValue() ?? 'signup'] ?? info.getValue()}</span>;
        },
      }),
      columnHelper.accessor((row) => row.source_tenant?.name ?? row.name ?? '-', {
        id: 'tenant_name',
        header: () => 'المستأجر',
        cell: (info) => (
          <div>
            <p className="text-sm font-semibold text-gray-900">{info.getValue()}</p>
            <p className="text-xs text-gray-400 truncate max-w-[200px]">{info.row.original.email ?? '-'}</p>
          </div>
        ),
      }),
      columnHelper.accessor((row) => row.old_plan?.title ?? '—', {
        id: 'old_plan',
        header: () => 'الباقة السابقة',
        cell: (info) => <span className="text-xs text-gray-600">{info.getValue()}</span>,
      }),
      columnHelper.accessor((row) => row.payment_status ?? '—', {
        id: 'payment_status',
        header: () => 'حالة الدفع',
        cell: (info) => <StatusBadge status={info.getValue() === '—' ? 'pending' : info.getValue()} />,
      }),
      columnHelper.accessor((row) => row.phone ?? '-', {
        id: 'phone',
        header: () => t('order_plans.col.phone'),
        cell: (info) => <span className="text-sm text-gray-700 font-mono">{info.getValue()}</span>,
      }),
      columnHelper.accessor((row) => row.plan?.title ?? '', {
        id: 'planTitle',
        header: () => t('order_plans.col.plan'),
        cell: (info) => <PlanBadge plan={info.getValue()} />,
      }),
      columnHelper.accessor((row) => row.plan?.price ?? '', {
        id: 'planPrice',
        header: () => t('order_plans.col.price'),
        cell: (info) => {
          const row = info.row.original;
          const price = row.plan?.price;
          const days = row.plan?.days;
          return (
            <div>
              <p className="text-sm font-bold text-gray-900">{price ? `$${price}` : '-'}</p>
              <p className="text-xs text-gray-400">
                {days ? `${days} ${t('plans.duration_days')}` : '-'}
              </p>
            </div>
          );
        },
      }),
      columnHelper.accessor((row) => row.tenant_id ?? '-', {
        id: 'tenant_id',
        header: () => t('order_plans.col.tenant_id'),
        cell: (info) => (
          <span className="text-xs font-mono text-gray-600 break-all max-w-[140px] inline-block">
            {info.getValue()}
          </span>
        ),
      }),
      columnHelper.accessor((row) => row.subscription_id ?? '-', {
        id: 'subscription_id',
        header: () => t('order_plans.col.subscription_id'),
        cell: (info) => <span className="text-sm font-mono text-gray-700">{info.getValue()}</span>,
      }),
      columnHelper.accessor((row) => row.status ?? 'pending', {
        id: 'status',
        header: () => t('order_plans.col.status'),
        cell: (info) => {
          const row = info.row.original;
          const status = row.status ?? 'pending';
          return (
            <div className="flex flex-col items-start gap-2 sm:flex-row sm:items-center">
              <StatusBadge status={status} />
              <select
                value={
                  STATUS_OPTIONS.includes(status as OrderPlanStatusValue)
                    ? (status as OrderPlanStatusValue)
                    : 'approved'
                }
                disabled={patchingId === row.id}
                onChange={(e) => void handlePatchStatus(row.id, e.target.value)}
                className="text-xs border border-gray-200 rounded-lg px-2 py-1.5 bg-white text-gray-800 max-w-[140px] focus:outline-none focus:ring-2 focus:ring-teal-500/30"
              >
                {STATUS_OPTIONS.map((s) => (
                  <option key={s} value={s}>
                    {t(`status.${s}`)}
                  </option>
                ))}
              </select>
            </div>
          );
        },
      }),
      columnHelper.accessor((row) => row.created_at ?? '-', {
        id: 'created_at',
        header: () => t('order_plans.col.created_at'),
        cell: (info) => (
          <div className="flex items-center gap-1.5 text-sm text-gray-600">
            <i className="ri-calendar-line text-gray-400 text-xs" />
            {info.getValue()}
          </div>
        ),
      }),
      columnHelper.display({
        id: 'actions',
        header: () => t('order_plans.col.actions'),
        cell: (info) => (
          <ActionIconButton
            icon="ri-eye-line"
            label={t('actions.view_details')}
            onClick={() => setDetailId(info.row.original.id)}
          />
        ),
      }),
    ],
    [t, patchingId, handlePatchStatus],
  );

  const table = useReactTable({
    data: filteredRows,
    columns,
    state: { globalFilter, sorting },
    onGlobalFilterChange: setGlobalFilter,
    onSortingChange: setSorting,
    getCoreRowModel: getCoreRowModel(),
    getFilteredRowModel: getFilteredRowModel(),
    getSortedRowModel: getSortedRowModel(),
  });

  const filters: { value: FilterType; labelKey: string }[] = [
    { value: 'all', labelKey: 'order_plans.filter.all' },
    { value: 'approved', labelKey: 'order_plans.filter.approved' },
    { value: 'rejected', labelKey: 'order_plans.filter.rejected' },
  ];

  const approvedOnPage = rows.filter((r) => r.status === 'approved').length;
  const rejectedOnPage = rows.filter((r) => r.status === 'rejected').length;
  const pageScoped = lastPage > 1;

  const hasSearch = globalFilter.trim().length > 0;
  const displayFrom = rows.length === 0 ? 0 : (page - 1) * PER_PAGE + 1;
  const displayTo = rows.length === 0 ? 0 : (page - 1) * PER_PAGE + rows.length;

  return (
    <div className="flex flex-col gap-4">
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div className="bg-white rounded-xl p-4 ring-1 ring-gray-100">
          <p className="text-xs text-gray-500 font-medium">{t('order_plans.summary.total')}</p>
          <p className="text-2xl font-bold text-gray-900 mt-1">{loading ? '—' : total}</p>
        </div>
        <div className="bg-white rounded-xl p-4 ring-1 ring-gray-100">
          <p className="text-xs text-gray-500 font-medium">
            {t('order_plans.summary.approved')}
            {pageScoped ? (
              <span className="text-gray-400 font-normal ms-1">({t('order_plans.summary.page_scope')})</span>
            ) : null}
          </p>
          <p className="text-2xl font-bold text-emerald-600 mt-1">{loading ? '—' : approvedOnPage}</p>
        </div>
        <div className="bg-white rounded-xl p-4 ring-1 ring-gray-100">
          <p className="text-xs text-gray-500 font-medium">
            {t('order_plans.summary.rejected')}
            {pageScoped ? (
              <span className="text-gray-400 font-normal ms-1">({t('order_plans.summary.page_scope')})</span>
            ) : null}
          </p>
          <p className="text-2xl font-bold text-rose-600 mt-1">{loading ? '—' : rejectedOnPage}</p>
        </div>
      </div>

      {listError ? (
        <div className="rounded-xl bg-rose-50 text-rose-800 text-sm px-4 py-3 ring-1 ring-rose-100">{listError}</div>
      ) : null}

      <div className="bg-white rounded-xl ring-1 ring-gray-100 overflow-hidden">
        <div className="px-5 py-4 border-b border-gray-50 flex items-center justify-between gap-4 flex-wrap">
          <div className="flex items-center gap-2 flex-wrap">
            {filters.map((f) => (
              <button
                key={f.value}
                type="button"
                onClick={() => {
                  setStatusFilter(f.value);
                  setPage(1);
                }}
                className={`px-3 py-1.5 rounded-full text-xs font-medium transition-all cursor-pointer whitespace-nowrap ${statusFilter === f.value ? 'bg-teal-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'}`}
              >
                {t(f.labelKey)}
              </button>
            ))}
          </div>
          <div className="relative flex-shrink-0">
            <div className="w-4 h-4 flex items-center justify-center absolute start-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none">
              <i className="ri-search-line text-xs" />
            </div>
            <input
              type="text"
              placeholder={t('order_plans.search_placeholder')}
              value={globalFilter}
              onChange={(e) => setGlobalFilter(e.target.value)}
              className="w-52 ps-8 pe-3 py-1.5 text-sm bg-gray-50 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500/30 focus:border-teal-400 transition-all"
            />
          </div>
        </div>

        <div className="overflow-x-auto">
          {loading ? (
            <div className="flex flex-col items-center justify-center py-20 gap-3 text-gray-500">
              <i className="ri-loader-4-line text-3xl text-teal-600 animate-spin" />
              <p className="text-sm">{t('order_plans.loading')}</p>
            </div>
          ) : (
            <table className="w-full min-w-[960px]">
              <thead className="bg-gray-50/80">
                {table.getHeaderGroups().map((hg) => (
                  <tr key={hg.id}>
                    {hg.headers.map((header) => (
                      <th
                        key={header.id}
                        className="px-4 py-3 text-start text-xs font-semibold text-gray-500 uppercase tracking-wide cursor-pointer whitespace-nowrap"
                        onClick={header.column.getToggleSortingHandler()}
                      >
                        <div className="flex items-center gap-1">
                          {flexRender(header.column.columnDef.header, header.getContext())}
                          {header.column.getIsSorted() && (
                            <i
                              className={`ri-arrow-${header.column.getIsSorted() === 'asc' ? 'up' : 'down'}-s-line text-teal-500`}
                            />
                          )}
                        </div>
                      </th>
                    ))}
                  </tr>
                ))}
              </thead>
              <tbody className="divide-y divide-gray-50">
                {table.getRowModel().rows.length === 0 ? (
                  <tr>
                    <td colSpan={columns.length} className="px-4 py-16 text-center">
                      <div className="flex flex-col items-center gap-3 text-gray-400">
                        <i className="ri-shopping-bag-3-line text-4xl" />
                        <p className="text-sm font-medium">{t('table.no_order_plans')}</p>
                      </div>
                    </td>
                  </tr>
                ) : (
                  table.getRowModel().rows.map((row) => (
                    <tr key={row.id} className="hover:bg-gray-50/60 transition-colors">
                      {row.getVisibleCells().map((cell) => (
                        <td key={cell.id} className="px-4 py-3 align-top">
                          {flexRender(cell.column.columnDef.cell, cell.getContext())}
                        </td>
                      ))}
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          )}
        </div>

        <div className="px-5 py-3 border-t border-gray-50 flex items-center justify-between flex-wrap gap-2">
          <p className="text-sm text-gray-500">
            {hasSearch
              ? t('order_plans.search_on_page', { count: filteredRows.length })
              : `${t('table.showing')} ${displayFrom}–${displayTo} ${t('table.of')} ${total} ${t('table.order_plans_count')}`}
          </p>
          <div className="flex items-center gap-1">
            <button
              type="button"
              onClick={() => setPage((p) => Math.max(1, p - 1))}
              disabled={page <= 1 || loading}
              className="w-8 h-8 flex items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 disabled:opacity-30 disabled:cursor-not-allowed cursor-pointer transition-colors"
            >
              <i className="ri-arrow-right-s-line" />
            </button>
            <span className="text-sm text-gray-600 px-2 min-w-[4rem] text-center">
              {page} / {lastPage}
            </span>
            <button
              type="button"
              onClick={() => setPage((p) => p + 1)}
              disabled={page >= lastPage || loading}
              className="w-8 h-8 flex items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 disabled:opacity-30 disabled:cursor-not-allowed cursor-pointer transition-colors"
            >
              <i className="ri-arrow-left-s-line" />
            </button>
          </div>
        </div>
      </div>

      <OrderPlanDetailModal
        orderPlanId={detailId}
        onClose={() => setDetailId(null)}
        onUpdated={() => void loadList()}
        onShowCredentials={(email, password, tenantName) => {
          setCredentialsPopup({
            isOpen: true,
            email,
            password,
            tenantName,
          });
        }}
      />

      <CredentialsPopup
        isOpen={credentialsPopup.isOpen}
        onClose={() => setCredentialsPopup(prev => ({ ...prev, isOpen: false }))}
        email={credentialsPopup.email}
        password={credentialsPopup.password}
        tenantName={credentialsPopup.tenantName}
      />
    </div>
  );
}
