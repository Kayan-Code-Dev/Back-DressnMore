import AdminLayout from '../../components/feature/AdminLayout';
import SubscriptionStatsRow from './components/SubscriptionStatsRow';
import RevenueChart from './components/RevenueChart';
import GrowthChart from './components/GrowthChart';
import RecentSubscriptions from './components/RecentSubscriptions';
import RecentActivities from './components/RecentActivities';

export default function DashboardPage() {
  return (
    <AdminLayout>
      <div className="flex flex-col gap-6">
        <SubscriptionStatsRow />

        <div className="grid grid-cols-5 gap-4">
          <div className="col-span-3"><RevenueChart /></div>
          <div className="col-span-2"><GrowthChart /></div>
        </div>

        <div className="grid grid-cols-2 gap-4">
          <RecentSubscriptions />
          <RecentActivities />
        </div>
      </div>
    </AdminLayout>
  );
}
