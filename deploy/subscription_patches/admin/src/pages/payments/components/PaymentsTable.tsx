import { useState, useMemo, useEffect, useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';
import {
  useReactTable, getCoreRowModel, getFilteredRowModel,
  getPaginationRowModel, getSortedRowModel, createColumnHelper,
  flexRender, type SortingState,
} from '@tanstack/react-table';
import StatusBadge from '../../../components/base/StatusBadge';
import { ActionIconButton } from '../../../components/base/StatusBadge';
import ConfirmModal from '../../../components/base/ConfirmModal';
import {
  fetchPaymentsList,
  markPaymentPaid,
  refundPayment,
  type AdminPayment,
} from '../../../api/payments.api';

type FilterType = 'all' | 'paid' | 'pending' | 'refunded' | 'failed';
const columnHelper = createColumnHelper<AdminPayment>();

const methodConfig: Record<string, { icon: string; color: string }> = {
  bank: { icon: 'ri-bank-line', color: 'text-gray-600' },
  vodafone_cash: { icon: 'ri-smartphone-line', color: 'text-teal-600' },
  instapay: { icon: 'ri-smartphone-line', color: 'text-emerald-600' },
  manual: { icon: 'ri-money-dollar-circle-line', color: 'text-amber-600' },
};

export default function PaymentsTable() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const [data, setData] = useState<AdminPayment[]>([]);
  const [loading, setLoading] = useState(true);
  const [listError, setListError] = useState('');
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [globalFilter, setGlobalFilter] = useState('');
  const [statusFilter, setStatusFilter] = useState<FilterType>('all');
  const [sorting, setSorting] = useState<SortingState>([]);
  const [confirmData, setConfirmData] = useState<{ type: 'refund' | 'mark_paid'; paymentId: number } | null>(null);

  const load = useCallback(async () => {
    setListError('');
    setLoading(true);
    try {
      const r = await fetchPaymentsList(page, 15, {
        status: statusFilter === 'all' ? undefined : statusFilter,
        search: globalFilter.trim() || undefined,
      });
      setLoading(false);
      if (r.ok === false) {
        if (r.unauthorized) {
          navigate('/admin/login', { replace: true });
          return;
        }
        setListError(r.message);
        setData([]);
        return;
      }
      setData(r.list.data);
      setLastPage(Math.max(1, r.list.last_page));
    } catch (err) {
      setLoading(false);
      setListError(err instanceof Error ? err.message : 'فشل تحميل المدفوعات');
      setData([]);
    }
  }, [navigate, page, statusFilter, globalFilter]);

  useEffect(() => {
    void load();
  }, [load]);

  const handleMarkPaid = async (paymentId: number) => {
    const r = await markPaymentPaid(paymentId);
    if (r.ok) await load();
    else setListError(r.message);
  };

  const handleRefund = async (paymentId: number) => {
    const r = await refundPayment(paymentId);
    if (r.ok) await load();
    else setListError(r.message);
  };

  const columns = useMemo(() => [
    columnHelper.accessor('order_reference', {
      header: () => 'المرجع',
      cell: (info) => (
        <span className="text-xs font-mono text-gray-700 bg-gray-100 px-2 py-1 rounded">
          {info.getValue() ?? `#${info.row.original.id}`}
        </span>
      ),
    }),
    columnHelper.accessor('tenant', {
      header: () => t('payments.col.atelier'),
      cell: (info) => (
        <div>
          <p className="text-sm font-semibold text-gray-900">{info.getValue()?.name ?? '—'}</p>
          <p className="text-xs text-gray-400">{info.getValue()?.slug ?? ''}</p>
        </div>
      ),
    }),
    columnHelper.accessor('amount', {
      header: () => t('payments.col.amount'),
      cell: (info) => (
        <div>
          <p className="text-sm font-bold text-gray-900">
            {info.getValue()} {info.row.original.currency_symbol}
          </p>
          <p className="text-xs text-gray-400">{info.row.original.plan?.title ?? ''}</p>
        </div>
      ),
    }),
    columnHelper.accessor('method', {
      header: () => t('payments.col.method'),
      cell: (info) => {
        const mc = methodConfig[info.getValue() ?? 'manual'] ?? methodConfig.manual;
        return (
          <div className="flex items-center gap-2">
            <i className={`${mc.icon} text-base ${mc.color}`} />
            <span className="text-sm text-gray-700">{info.row.original.payment_gateway?.name ?? info.getValue()}</span>
          </div>
        );
      },
    }),
    columnHelper.accessor('status', {
      header: () => t('payments.col.status'),
      cell: (info) => <StatusBadge status={info.getValue()} />,
    }),
    columnHelper.accessor('created_at', {
      header: () => t('payments.col.date'),
      cell: (info) => <span className="text-sm text-gray-500">{info.getValue()?.slice(0, 10) ?? '—'}</span>,
    }),
    columnHelper.display({
      id: 'actions',
      header: () => t('payments.col.actions'),
      cell: (info) => {
        const { status, id } = info.row.original;
        return (
          <div className="flex items-center gap-0.5">
            {status === 'paid' && (
              <ActionIconButton icon="ri-refund-2-line" label={t('payments.refund')} variant="warning"
                onClick={() => setConfirmData({ type: 'refund', paymentId: id })} />
            )}
            {(status === 'pending' || status === 'failed') && (
              <ActionIconButton icon="ri-checkbox-circle-line" label={t('payments.mark_paid')} variant="success"
                onClick={() => setConfirmData({ type: 'mark_paid', paymentId: id })} />
            )}
          </div>
        );
      },
    }),
  ], [t]);

  const table = useReactTable({
    data,
    columns,
    state: { sorting },
    onSortingChange: setSorting,
    getCoreRowModel: getCoreRowModel(),
    getFilteredRowModel: getFilteredRowModel(),
    getPaginationRowModel: getPaginationRowModel(),
    getSortedRowModel: getSortedRowModel(),
    initialState: { pagination: { pageSize: 15 } },
  });

  const totalRevenue = data.filter((p) => p.status === 'paid').reduce((a, p) => a + parseFloat(p.amount), 0);
  const totalPaid = data.filter((p) => p.status === 'paid').length;
  const totalPending = data.filter((p) => p.status === 'pending').length;
  const totalRefunded = data.filter((p) => p.status === 'refunded').length;

  return (
    <>
      <div className="flex flex-col gap-5">
        <div className="grid grid-cols-4 gap-4">
          {[
            { label: t('payments.summary.total_revenue'), value: `${totalRevenue.toLocaleString()} ج.م`, icon: 'ri-money-dollar-circle-line', color: 'bg-teal-50 text-teal-600' },
            { label: t('payments.summary.paid'), value: String(totalPaid), icon: 'ri-checkbox-circle-line', color: 'bg-emerald-50 text-emerald-600' },
            { label: t('payments.summary.pending'), value: String(totalPending), icon: 'ri-time-line', color: 'bg-amber-50 text-amber-600' },
            { label: t('payments.summary.refunded'), value: String(totalRefunded), icon: 'ri-refund-2-line', color: 'bg-rose-50 text-rose-600' },
          ].map((card) => (
            <div key={card.label} className="bg-white rounded-xl p-4 ring-1 ring-gray-100 flex items-center gap-3">
              <div className={`w-10 h-10 flex items-center justify-center rounded-xl ${card.color}`}>
                <i className={`${card.icon} text-lg`} />
              </div>
              <div>
                <p className="text-xs text-gray-400 font-medium">{card.label}</p>
                <p className="text-xl font-bold text-gray-900">{card.value}</p>
              </div>
            </div>
          ))}
        </div>

        <div className="bg-white rounded-xl ring-1 ring-gray-100 overflow-hidden">
          <div className="px-5 py-4 border-b border-gray-50 flex items-center justify-between gap-4 flex-wrap">
            <div className="flex items-center gap-2 flex-wrap">
              {(['all', 'paid', 'pending', 'refunded', 'failed'] as FilterType[]).map((f) => (
                <button key={f} type="button" onClick={() => { setPage(1); setStatusFilter(f); }}
                  className={`px-3 py-1.5 rounded-full text-xs font-medium cursor-pointer ${statusFilter === f ? 'bg-teal-600 text-white' : 'bg-gray-100 text-gray-600'}`}>
                  {t(`payments.filter.${f === 'all' ? 'all' : f}`)}
                </button>
              ))}
            </div>
            <input type="text" placeholder={t('payments.search_placeholder')} value={globalFilter}
              onChange={(e) => { setPage(1); setGlobalFilter(e.target.value); }}
              className="w-52 px-3 py-1.5 text-sm bg-gray-50 border border-gray-200 rounded-lg" />
          </div>

          {listError ? <p className="text-sm text-rose-600 px-5 py-3">{listError}</p> : null}
          {loading ? <p className="text-sm text-gray-400 text-center py-12">جاري التحميل...</p> : (
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead className="bg-gray-50/80">
                  {table.getHeaderGroups().map((hg) => (
                    <tr key={hg.id}>
                      {hg.headers.map((header) => (
                        <th key={header.id} className="px-4 py-3 text-start text-xs font-semibold text-gray-500 uppercase">
                          {flexRender(header.column.columnDef.header, header.getContext())}
                        </th>
                      ))}
                    </tr>
                  ))}
                </thead>
                <tbody className="divide-y divide-gray-50">
                  {data.length === 0 ? (
                    <tr><td colSpan={columns.length} className="px-4 py-16 text-center text-sm text-gray-400">{t('payments.no_payments')}</td></tr>
                  ) : table.getRowModel().rows.map((row) => (
                    <tr key={row.id} className="hover:bg-gray-50/60">
                      {row.getVisibleCells().map((cell) => (
                        <td key={cell.id} className="px-4 py-3">{flexRender(cell.column.columnDef.cell, cell.getContext())}</td>
                      ))}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}

          {lastPage > 1 ? (
            <div className="px-5 py-3 border-t border-gray-50 flex items-center justify-between">
              <button type="button" disabled={page <= 1} onClick={() => setPage((p) => p - 1)} className="text-sm disabled:opacity-40">السابق</button>
              <span className="text-xs text-gray-500">صفحة {page} من {lastPage}</span>
              <button type="button" disabled={page >= lastPage} onClick={() => setPage((p) => p + 1)} className="text-sm disabled:opacity-40">التالي</button>
            </div>
          ) : null}
        </div>
      </div>

      <ConfirmModal
        isOpen={!!confirmData}
        title={confirmData?.type === 'refund' ? t('payments.refund') : t('payments.mark_paid')}
        message={confirmData?.type === 'refund' ? t('payments.confirm_refund') : t('payments.confirm_mark_paid')}
        confirmLabel={confirmData?.type === 'refund' ? t('payments.refund') : t('payments.mark_paid')}
        confirmVariant={confirmData?.type === 'refund' ? 'warning' : 'success'}
        onConfirm={() => {
          if (!confirmData) return;
          if (confirmData.type === 'refund') void handleRefund(confirmData.paymentId);
          else void handleMarkPaid(confirmData.paymentId);
          setConfirmData(null);
        }}
        onClose={() => setConfirmData(null)}
      />
    </>
  );
}
