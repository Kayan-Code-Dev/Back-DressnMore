import { useTranslation } from 'react-i18next';

interface StatsCardProps {
  statKey: string;
  label?: string;
  value: string;
  change: string;
  changeType: 'up' | 'down';
  icon: string;
  color: 'teal' | 'emerald' | 'amber' | 'rose';
}

const colorMap = {
  teal:    { icon: 'bg-teal-500' },
  emerald: { icon: 'bg-emerald-500' },
  amber:   { icon: 'bg-amber-500' },
  rose:    { icon: 'bg-rose-500' },
};

export default function StatsCard({ statKey, label, value, change, changeType, icon, color }: StatsCardProps) {
  const { t } = useTranslation();
  const colors = colorMap[color];
  const isUp = changeType === 'up';
  const title = label ?? t(`stats.${statKey}`);

  return (
    <div className="bg-white rounded-xl p-6 ring-1 ring-gray-100 flex flex-col gap-4">
      <div className="flex items-start justify-between">
        <div>
          <p className="text-sm font-medium text-gray-500">{title}</p>
          <p className="text-3xl font-bold text-gray-900 mt-1">{value}</p>
        </div>
        <div className={`w-12 h-12 flex items-center justify-center rounded-xl ${colors.icon}`}>
          <i className={`${icon} text-xl text-white`} />
        </div>
      </div>
      <div className="flex items-center gap-1.5">
        <span className={`flex items-center gap-0.5 text-xs font-semibold ${isUp ? 'text-emerald-600' : 'text-rose-600'}`}>
          <i className={`${isUp ? 'ri-arrow-up-s-line' : 'ri-arrow-down-s-line'} text-sm`} />
          {change}
        </span>
        <span className="text-xs text-gray-400">{t('stats.vs_last_month')}</span>
      </div>
    </div>
  );
}
