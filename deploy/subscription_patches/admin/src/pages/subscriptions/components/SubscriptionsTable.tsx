import { useState, useMemo, useEffect, useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';
import {
  useReactTable,
  getCoreRowModel,
  getFilteredRowModel,
  getSortedRowModel,
  createColumnHelper,
  flexRender,
  type SortingState,
} from '@tanstack/react-table';
import StatusBadge, { PlanBadge } from '../../../components/base/StatusBadge';
import SubscriptionDetailModal from './SubscriptionDetailModal';
import { fetchSubscriptionsList, patchSubscriptionStatus } from '../../../api/subscriptions.api';
import type { AdminSubscription, SubscriptionStatusValue } from '../../../types/subscription.types';

const PER_PAGE = 15;
const STATUS_OPTIONS: SubscriptionStatusValue[] = ['pending', 'active', 'rejected', 'cancelled'];

type FilterType = 'all' | SubscriptionStatusValue;
const columnHelper = createColumnHelper<AdminSubscription>();

export default function SubscriptionsTable() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const [rows, setRows] = useState<AdminSubscription[]>([]);
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

  const loadSubscriptions = useCallback(async () => {
    setListError('');
    setLoading(true);
    try {
      const r = await fetchSubscriptionsList(page, PER_PAGE, {
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
        setLastPage(1);
        setTotal(0);
        return;
      }
      const data = r.list.data ?? [];
      setRows(data);
      setLastPage(Math.max(1, r.list.last_page));
      setTotal(r.list.total);
    } catch (err) {
      setLoading(false);
      setListError(err instanceof Error ? err.message : 'Failed to load subscriptions');
      setRows([]);
      setLastPage(1);
      setTotal(0);
    }
  }, [navigate, page, statusFilter]);

  useEffect(() => {
    void loadSubscriptions();
  }, [loadSubscriptions]);

  const filteredRows = useMemo(() => {
    const q = globalFilter.trim().toLowerCase();
    if (!q) return rows;
    return rows.filter(
      (s) =>
        String(s.id).includes(q) ||
        String(s.tenant_id ?? '').toLowerCase().includes(q) ||
        String(s.plan?.title ?? '').toLowerCase().includes(q) ||
        String(s.status ?? '').toLowerCase().includes(q),
    );
  }, [rows, globalFilter]);

  const handlePatchStatus = useCallback(
    async (id: number, status: string) => {
      setPatchingId(id);
      try {
        const r = await patchSubscriptionStatus(id, { status });
        setPatchingId(null);
        if (r.ok === false) {
          if (r.unauthorized) {
            navigate('/admin/login', { replace: true });
            return;
          }
          setListError(r.message);
          return;
        }
        await loadSubscriptions();
      } catch (err) {
        setPatchingId(null);
        setListError(err instanceof Error ? err.message : 'Failed to update status');
      }
    },
    [navigate, loadSubscriptions],
  );

  const columns = useMemo(
    () => [
      columnHelper.accessor('id', {
        header: () => t('subscriptions.col.id'),
        cell: (info) => (
          <span className="text-xs font-mono text-gray-500 bg-gray-100 px-2 py-1 rounded">
            {info.getValue()}
          </span>
        ),
      }),
      columnHelper.accessor((row) => row.tenant_id ?? '-', {
        id: 'tenant_id',
        header: () => t('subscriptions.col.tenant'),
        cell: (info) => (
          <span className="text-sm font-semibold text-gray-900 break-all max-w-[160px] inline-block">
            {info.getValue()}
          </span>
        ),
      }),
      columnHelper.accessor((row) => row.plan?.title ?? '', {
        id: 'planTitle',
        header: () => t('subscriptions.col.plan'),
        cell: (info) => <PlanBadge plan={info.getValue()} />,
      }),
      columnHelper.accessor((row) => row.plan?.price ?? '', {
        id: 'planPrice',
        header: () => t('subscriptions.col.price'),
        cell: (info) => {
          const row = info.row.original;
          const price = row.plan?.price;
          const days = row.plan?.days;
          const currency = row.plan?.currency;
          const symbol = currency === 'SAR' ? 'ر.س' : currency === 'EGP' ? 'ج.م' : '$';
          return (
            <div>
              <p className="text-sm font-bold text-gray-900">
                {price ? `${symbol}${price}` : '-'}
              </p>
              <p className="text-xs text-gray-400">
                {days ? `${days} ${t('plans.duration_days')}` : '-'}
              </p>
            </div>
          );
        },
      }),
      columnHelper.accessor((row) => row.status ?? 'pending', {
        id: 'status',
        header: () => t('subscriptions.col.status'),
        cell: (info) => {
          const sub = info.row.original;
          const status = sub.status ?? 'pending';
          return (
            <div className="flex flex-col items-start gap-2 sm:flex-row sm:items-center">
              <StatusBadge status={status} />
              <select
                value={
                  STATUS_OPTIONS.includes(status as SubscriptionStatusValue)
                    ? (status as SubscriptionStatusValue)
                    : 'pending'
                }
                disabled={patchingId === sub.id}
                onChange={(e) => void handlePatchStatus(sub.id, e.target.value)}
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
      columnHelper.accessor((row) => row.starts_at ?? '-', {
        id: 'starts_at',
        header: () => t('subscriptions.col.starts_at'),
        cell: (info) => (
          <div className="flex items-center gap-1.5 text-sm text-gray-600">
            <i className="ri-calendar-line text-gray-400 text-xs" />
            {info.getValue()}
          </div>
        ),
      }),
      columnHelper.accessor((row) => row.ends_at ?? '-', {
        id: 'ends_at',
        header: () => t('subscriptions.col.ends_at'),
        cell: (info) => {
          const row = info.row.original;
          const endsAt = row.ends_at ?? '-';
          const isExpired = endsAt !== '-' && new Date(endsAt) < new Date();
          return (
            <div className="flex items-center gap-1.5 text-sm">
              <i className={`ri-calendar-line text-xs ${isExpired ? 'text-rose-400' : 'text-gray-400'}`} />
              <span className={isExpired ? 'text-rose-600 font-medium' : 'text-gray-600'}>
                {endsAt}
              </span>
              {isExpired && (
                <span className="text-[10px] bg-rose-50 text-rose-600 px-1.5 py-0.5 rounded-full font-medium">
                  {t('subscriptions.expired')}
                </span>
              )}
            </div>
          );
        },
      }),
      columnHelper.display({
        id: 'actions',
        header: () => t('subscriptions.col.actions'),
        cell: (info) => (
          <span className="inline-flex items-center gap-0.5">
            <button
              type="button"
              onClick={() => setDetailId(info.row.original.id)}
              className="w-8 h-8 flex items-center justify-center rounded-lg text-gray-500 hover:text-teal-600 hover:bg-teal-50 transition-colors cursor-pointer"
              title={t('actions.view_details')}
            >
              <i className="ri-eye-line text-base" />
            </button>
          </span>
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
    { value: 'all', labelKey: 'subscriptions.filter.all' },
    { value: 'active', labelKey: 'subscriptions.filter.active' },
    { value: 'pending', labelKey: 'subscriptions.filter.pending' },
    { value: 'rejected', labelKey: 'subscriptions.filter.rejected' },
    { value: 'cancelled', labelKey: 'subscriptions.filter.cancelled' },
  ];

  const safeRows = rows ?? [];
  const totalActive = safeRows.filter((s) => s.status === 'active').length;
  const totalPending = safeRows.filter((s) => s.status === 'pending').length;
  const totalExpired = safeRows.filter((s) => {
    const endsAt = s.ends_at;
    return endsAt && new Date(endsAt) < new Date();
  }).length;
  const totalMRR = safeRows
    .filter((s) => s.status === 'active')
    .reduce((acc, s) => acc + Number.parseFloat(String(s.plan?.price ?? 0)), 0);

  const pageScoped = lastPage > 1;
  const hasSearch = globalFilter.trim().length > 0;
  const displayFrom = safeRows.length === 0 ? 0 : (page - 1) * PER_PAGE + 1;
  const displayTo = safeRows.length === 0 ? 0 : (page - 1) * PER_PAGE + safeRows.length;

  return (
    <div className="flex flex-col gap-4">
      {/* Stats Cards */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div className="bg-white rounded-xl p-4 ring-1 ring-gray-100">
          <p className="text-xs text-gray-500 font-medium">{t('subscriptions.summary.total')}</p>
          <p className="text-2xl font-bold text-gray-900 mt-1">{loading ? '—' : total}</p>
        </div>
        <div className="bg-white rounded-xl p-4 ring-1 ring-gray-100">
          <p className="text-xs text-gray-500 font-medium">
            {t('subscriptions.summary.active')}
            {pageScoped ? (
              <span className="text-gray-400 font-normal ms-1">({t('subscriptions.summary.page_scope')})</span>
            ) : null}
          </p>
          <p className="text-2xl font-bold text-emerald-600 mt-1">{loading ? '—' : totalActive}</p>
        </div>
        <div className="bg-white rounded-xl p-4 ring-1 ring-gray-100">
          <p className="text-xs text-gray-500 font-medium">
            {t('subscriptions.summary.pending')}
          </p>
          <p className="text-2xl font-bold text-amber-600 mt-1">{loading ? '—' : totalPending}</p>
        </div>
        <div className="bg-white rounded-xl p-4 ring-1 ring-gray-100">
          <p className="text-xs text-gray-500 font-medium">
            {t('subscriptions.summary.expired')}
          </p>
          <p className="text-2xl font-bold text-rose-600 mt-1">{loading ? '—' : totalExpired}</p>
        </div>
      </div>

      {/* MRR Card */}
      <div className="bg-gradient-to-r from-teal-600 to-teal-700 rounded-xl p-4 text-white">
        <div className="flex items-center justify-between">
          <div>
            <p className="text-xs text-teal-100 font-medium">{t('subscriptions.summary.mrr')}</p>
            <p className="text-3xl font-bold mt-1">
              {loading ? '—' : `$${totalMRR.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 })}`}
            </p>
          </div>
          <div className="w-12 h-12 flex items-center justify-center rounded-xl bg-white/20">
            <i className="ri-money-dollar-circle-line text-2xl text-white" />
          </div>
        </div>
      </div>

      {listError ? (
        <div className="rounded-xl bg-rose-50 text-rose-800 text-sm px-4 py-3 ring-1 ring-rose-100">
          {listError}
        </div>
      ) : null}

      <div className="bg-white rounded-xl ring-1 ring-gray-100 overflow-hidden">
        {/* Filters */}
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
                className={`px-3 py-1.5 rounded-full text-xs font-medium transition-all cursor-pointer whitespace-nowrap ${
                  statusFilter === f.value
                    ? 'bg-teal-600 text-white'
                    : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                }`}
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
              placeholder={t('subscriptions.search_placeholder')}
              value={globalFilter}
              onChange={(e) => setGlobalFilter(e.target.value)}
              className="w-52 ps-8 pe-3 py-1.5 text-sm bg-gray-50 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500/30 focus:border-teal-400 transition-all"
            />
          </div>
        </div>

        {/* Table */}
        <div className="overflow-x-auto">
          {loading ? (
            <div className="flex flex-col items-center justify-center py-20 gap-3 text-gray-500">
              <i className="ri-loader-4-line text-3xl text-teal-600 animate-spin" />
              <p className="text-sm">{t('subscriptions.loading')}</p>
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
                        <i className="ri-checkbox-circle-line text-4xl" />
                        <p className="text-sm font-medium">{t('table.no_subscriptions')}</p>
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

        {/* Pagination */}
        <div className="px-5 py-3 border-t border-gray-50 flex items-center justify-between flex-wrap gap-2">
          <p className="text-sm text-gray-500">
            {hasSearch
              ? t('subscriptions.search_on_page', { count: filteredRows.length })
              : `${t('table.showing')} ${displayFrom}–${displayTo} ${t('table.of')} ${total} ${t('table.subscriptions_count')}`}
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

      <SubscriptionDetailModal
        subscriptionId={detailId}
        onClose={() => setDetailId(null)}
        onUpdated={() => void loadSubscriptions()}
      />
    </div>
  );
}
