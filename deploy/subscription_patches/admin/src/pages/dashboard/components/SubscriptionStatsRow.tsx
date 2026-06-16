import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import StatsCard from '../../../components/base/StatsCard';
import { fetchSubscriptionDashboardStats } from '../../../api/dashboard.api';

export default function SubscriptionStatsRow() {
  const { t } = useTranslation();
  const [stats, setStats] = useState<Record<string, string | number> | null>(null);
  const [error, setError] = useState('');

  useEffect(() => {
    void fetchSubscriptionDashboardStats().then((r) => {
      if (r.ok === false) {
        setError(r.message);
        return;
      }
      setStats(r.stats as unknown as Record<string, string | number>);
    });
  }, []);

  if (error) {
    return <p className="text-sm text-rose-600">{error}</p>;
  }

  const cards = [
    { id: 'total_subscription_revenue', value: stats ? `${stats.total_subscription_revenue} ج.م` : '…', icon: 'ri-money-dollar-circle-line', color: 'teal' as const },
    { id: 'active_subscriptions', value: stats ? String(stats.active_subscriptions) : '…', icon: 'ri-checkbox-circle-line', color: 'emerald' as const },
    { id: 'pending_plan_requests', value: stats ? String(stats.pending_plan_requests) : '…', icon: 'ri-file-list-3-line', color: 'amber' as const },
    { id: 'pending_payments', value: stats ? String(stats.pending_payments) : '…', icon: 'ri-time-line', color: 'rose' as const },
  ];

  return (
    <div className="grid grid-cols-4 gap-4">
      {cards.map((stat) => (
        <StatsCard
          key={stat.id}
          statKey={stat.id}
          value={stat.value}
          change=""
          changeType="up"
          icon={stat.icon}
          color={stat.color}
        />
      ))}
    </div>
  );
}
