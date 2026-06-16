import { useState, useMemo, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import AdminLayout from '../../components/feature/AdminLayout';
import GatewaysGrid from './components/GatewaysGrid';
import GatewayFormModal from './components/GatewayFormModal';
import { paymentGatewaysData, PaymentGateway } from '../../mocks/paymentGateways';
import {
  createPaymentGateway,
  deletePaymentGateway,
  listPaymentGateways,
  updatePaymentGateway,
} from '../../api/paymentGateways.api';

type FilterStatus = 'all' | 'active' | 'inactive';

const UNIQUE_TYPES = (gateways: PaymentGateway[]) => {
  const types = new Set(gateways.map((g) => g.type));
  return types.size;
};

export default function PaymentGatewaysPage() {
  const { t } = useTranslation();

  const [gateways, setGateways] = useState<PaymentGateway[]>([]);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState<FilterStatus>('all');
  const [search, setSearch] = useState('');
  const [modalOpen, setModalOpen] = useState(false);
  const [editGateway, setEditGateway] = useState<PaymentGateway | null>(null);

  useEffect(() => {
    let cancelled = false;
    listPaymentGateways()
      .then((rows) => {
        if (!cancelled) setGateways(rows.length > 0 ? rows : paymentGatewaysData);
      })
      .catch(() => {
        if (!cancelled) setGateways(paymentGatewaysData);
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => { cancelled = true; };
  }, []);

  const stats = useMemo(() => ({
    total: gateways.length,
    active: gateways.filter((g) => g.isActive).length,
    inactive: gateways.filter((g) => !g.isActive).length,
    types: UNIQUE_TYPES(gateways),
  }), [gateways]);

  const filtered = useMemo(() => {
    let list = [...gateways].sort((a, b) => a.displayOrder - b.displayOrder);
    if (filter === 'active') list = list.filter((g) => g.isActive);
    if (filter === 'inactive') list = list.filter((g) => !g.isActive);
    if (search.trim()) {
      const q = search.toLowerCase();
      list = list.filter(
        (g) =>
          g.name.toLowerCase().includes(q) ||
          g.accountHolder.toLowerCase().includes(q) ||
          g.accountNumber.toLowerCase().includes(q) ||
          (g.bankName || '').toLowerCase().includes(q),
      );
    }
    return list;
  }, [gateways, filter, search]);

  const handleOpenAdd = () => {
    setEditGateway(null);
    setModalOpen(true);
  };

  const handleOpenEdit = (gw: PaymentGateway) => {
    setEditGateway(gw);
    setModalOpen(true);
  };

  const handleSave = async (data: Omit<PaymentGateway, 'id' | 'createdAt' | 'usageCount'>) => {
    try {
      if (editGateway) {
        const updated = await updatePaymentGateway(editGateway.id, data);
        setGateways((prev) => prev.map((g) => (g.id === editGateway.id ? updated : g)));
      } else {
        const created = await createPaymentGateway(data);
        setGateways((prev) => [...prev, created]);
      }
      setModalOpen(false);
    } catch {
      if (editGateway) {
        setGateways((prev) => prev.map((g) => (g.id === editGateway.id ? { ...g, ...data } : g)));
      } else {
        const newGw: PaymentGateway = {
          ...data,
          id: `pg-${Date.now()}`,
          createdAt: new Date().toISOString().split('T')[0],
          usageCount: 0,
        };
        setGateways((prev) => [...prev, newGw]);
      }
      setModalOpen(false);
    }
  };

  const handleToggle = async (id: string) => {
    const current = gateways.find((g) => g.id === id);
    if (!current) return;
    const next = { ...current, isActive: !current.isActive };
    setGateways((prev) => prev.map((g) => (g.id === id ? next : g)));
    try {
      await updatePaymentGateway(id, { isActive: next.isActive });
    } catch {
      setGateways((prev) => prev.map((g) => (g.id === id ? current : g)));
    }
  };

  const handleDelete = async (id: string) => {
    const previous = gateways;
    setGateways((prev) => prev.filter((g) => g.id !== id));
    try {
      await deletePaymentGateway(id);
    } catch {
      setGateways(previous);
    }
  };

  const statsCards: { key: keyof typeof stats; icon: string; color: string; bg: string }[] = [
    { key: 'total',    icon: 'ri-bank-card-2-line',      color: 'text-teal-600',   bg: 'bg-teal-50' },
    { key: 'active',   icon: 'ri-checkbox-circle-line',   color: 'text-emerald-600', bg: 'bg-emerald-50' },
    { key: 'inactive', icon: 'ri-pause-circle-line',      color: 'text-amber-600',  bg: 'bg-amber-50' },
    { key: 'types',    icon: 'ri-apps-2-line',            color: 'text-indigo-600', bg: 'bg-indigo-50' },
  ];

  const statLabels: Record<keyof typeof stats, string> = {
    total:    t('payment_gateways.summary.total'),
    active:   t('payment_gateways.summary.active'),
    inactive: t('payment_gateways.summary.inactive'),
    types:    t('payment_gateways.summary.types_count'),
  };

  return (
    <AdminLayout>
      <div className="space-y-6">
        {/* Page Header */}
        <div className="flex items-start justify-between">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">{t('page.payment_gateways.title')}</h1>
            <p className="text-sm text-gray-500 mt-1">{t('page.payment_gateways.subtitle')}</p>
          </div>
          <button
            type="button"
            onClick={handleOpenAdd}
            className="flex items-center gap-2 px-5 py-2.5 bg-teal-600 hover:bg-teal-700 text-white text-sm font-semibold rounded-xl transition-colors cursor-pointer whitespace-nowrap"
          >
            <div className="w-4 h-4 flex items-center justify-center">
              <i className="ri-add-line text-base" />
            </div>
            {t('payment_gateways.add_gateway')}
          </button>
        </div>

        {/* Stats */}
        <div className="grid grid-cols-4 gap-4">
          {statsCards.map(({ key, icon, color, bg }) => (
            <div key={key} className="bg-white rounded-xl p-5 ring-1 ring-gray-100 flex items-center gap-4">
              <div className={`w-11 h-11 flex items-center justify-center rounded-xl ${bg} flex-shrink-0`}>
                <i className={`${icon} text-xl ${color}`} />
              </div>
              <div>
                <p className="text-xs font-medium text-gray-500">{statLabels[key]}</p>
                <p className="text-2xl font-bold text-gray-900 mt-0.5">{stats[key]}</p>
              </div>
            </div>
          ))}
        </div>

        {/* Filters + Search */}
        <div className="bg-white rounded-xl ring-1 ring-gray-100 px-5 py-4">
          <div className="flex items-center justify-between gap-4">
            {/* Status Filter Pills */}
            <div className="flex items-center gap-1 bg-gray-100 rounded-xl p-1">
              {(['all', 'active', 'inactive'] as FilterStatus[]).map((f) => (
                <button
                  key={f}
                  type="button"
                  onClick={() => setFilter(f)}
                  className={`px-4 py-1.5 rounded-lg text-sm font-medium transition-all cursor-pointer whitespace-nowrap ${
                    filter === f
                      ? 'bg-white text-gray-900 ring-1 ring-gray-200'
                      : 'text-gray-500 hover:text-gray-700'
                  }`}
                >
                  {t(`payment_gateways.filter.${f}`)}
                </button>
              ))}
            </div>

            {/* Search */}
            <div className="relative flex-1 max-w-xs">
              <div className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 flex items-center justify-center pointer-events-none">
                <i className="ri-search-line text-gray-400 text-sm" />
              </div>
              <input
                type="text"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder={t('payment_gateways.search_placeholder')}
                className="w-full pl-9 pr-4 py-2 text-sm border border-gray-200 rounded-lg outline-none focus:border-teal-500 focus:ring-2 focus:ring-teal-500/10 transition-all"
              />
            </div>

            {/* Count */}
            <span className="text-sm text-gray-400 whitespace-nowrap">
              {filtered.length} {t('payment_gateways.gateways_count')}
            </span>
          </div>
        </div>

        {/* Gateways Grid */}
        {loading ? (
          <div className="bg-white rounded-xl ring-1 ring-gray-100 p-10 text-center text-gray-500">
            {t('common.loading', { defaultValue: 'Loading...' })}
          </div>
        ) : (
          <GatewaysGrid
            gateways={filtered}
            onEdit={handleOpenEdit}
            onToggle={handleToggle}
            onDelete={handleDelete}
          />
        )}
      </div>

      {/* Form Modal */}
      {modalOpen && (
        <GatewayFormModal
          gateway={editGateway}
          onClose={() => setModalOpen(false)}
          onSave={handleSave}
        />
      )}
    </AdminLayout>
  );
}
