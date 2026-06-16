import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router-dom';
import StatusBadge, { PlanBadge } from '../../../components/base/StatusBadge';
import { fetchSubscriptionsList } from '../../../api/subscriptions.api';
import type { AdminSubscription } from '../../../types/subscription.types';

export default function RecentSubscriptions() {
  const { t } = useTranslation();
  const [rows, setRows] = useState<AdminSubscription[]>([]);

  useEffect(() => {
    void fetchSubscriptionsList(1, 6).then((r) => {
      if (r.ok) setRows(r.list.data ?? []);
    });
  }, []);

  return (
    <div className="bg-white rounded-xl ring-1 ring-gray-100 overflow-hidden">
      <div className="px-5 py-4 border-b border-gray-50 flex items-center justify-between">
        <div>
          <h3 className="text-base font-bold text-gray-900">{t('recent.subscriptions.title')}</h3>
          <p className="text-xs text-gray-400 mt-0.5">{t('recent.subscriptions.subtitle')}</p>
        </div>
        <Link to="/subscriptions" className="text-xs font-medium text-teal-600 hover:text-teal-700 whitespace-nowrap">
          {t('recent.subscriptions.view_all')} →
        </Link>
      </div>
      <div className="divide-y divide-gray-50">
        {rows.length === 0 ? (
          <p className="text-sm text-gray-400 text-center py-8">لا توجد اشتراكات</p>
        ) : rows.map((sub) => (
          <div key={sub.id} className="flex items-center justify-between px-5 py-3 hover:bg-gray-50/50 transition-colors">
            <div className="flex items-center gap-3 min-w-0">
              <div className="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 text-gray-500 flex-shrink-0">
                <i className="ri-store-2-line text-sm" />
              </div>
              <div className="min-w-0">
                <p className="text-sm font-semibold text-gray-800 truncate">{sub.tenant?.name ?? sub.tenant_id}</p>
                <p className="text-xs text-gray-400">{sub.ends_at?.slice(0, 10) ?? '—'}</p>
              </div>
            </div>
            <div className="flex items-center gap-3 flex-shrink-0">
              <PlanBadge plan={sub.plan?.title ?? '—'} />
              <span className="text-sm font-bold text-gray-800">{sub.plan?.price ?? '—'}</span>
              <StatusBadge status={sub.status} size="sm" />
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
