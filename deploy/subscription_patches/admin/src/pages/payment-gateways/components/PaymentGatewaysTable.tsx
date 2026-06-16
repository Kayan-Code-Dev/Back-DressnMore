import { useCallback, useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import type { PaymentGateway } from '../../../mocks/paymentGateways';
import {
  createPaymentGateway,
  listPaymentGateways,
  togglePaymentGatewayStatus,
  updatePaymentGateway,
} from '../../../api/paymentGateways.api';
import GatewayFormModal from './GatewayFormModal';

export default function PaymentGatewaysTable() {
  const { t } = useTranslation();
  const [rows, setRows] = useState<PaymentGateway[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [editing, setEditing] = useState<PaymentGateway | null>(null);
  const [creating, setCreating] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      setRows(await listPaymentGateways());
    } catch (e) {
      setError(e instanceof Error ? e.message : 'فشل التحميل');
      setRows([]);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const handleToggle = async (id: string) => {
    try {
      await togglePaymentGatewayStatus(id);
      await load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'فشل التحديث');
    }
  };

  return (
    <div className="bg-white rounded-xl ring-1 ring-gray-100 overflow-hidden">
      <div className="px-5 py-4 border-b border-gray-50 flex items-center justify-between">
        <h3 className="text-base font-bold text-gray-900">بوابات الدفع</h3>
        <button type="button" onClick={() => setCreating(true)} className="px-3 py-2 text-sm font-semibold bg-teal-600 text-white rounded-lg">
          إضافة بوابة
        </button>
      </div>
      {error ? <p className="text-sm text-rose-600 px-5 py-3">{error}</p> : null}
      {loading ? (
        <p className="text-sm text-gray-400 text-center py-10">جاري التحميل...</p>
      ) : rows.length === 0 ? (
        <p className="text-sm text-gray-400 text-center py-10">لا توجد بوابات دفع</p>
      ) : (
        <div className="divide-y divide-gray-50">
          {rows.map((gw) => (
            <div key={gw.id} className="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
              <div>
                <p className="text-sm font-bold text-gray-900">{gw.name}</p>
                <p className="text-xs text-gray-500">{gw.type} · {gw.accountNumber}</p>
                <p className="text-xs text-gray-400 mt-1 line-clamp-2">{gw.instructions}</p>
              </div>
              <div className="flex items-center gap-2">
                <span className={`text-xs px-2 py-1 rounded-full ${gw.isActive ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500'}`}>
                  {gw.isActive ? 'نشط' : 'غير نشط'}
                </span>
                <button type="button" onClick={() => void handleToggle(gw.id)} className="text-xs px-2 py-1 border rounded-lg">
                  {gw.isActive ? 'تعطيل' : 'تفعيل'}
                </button>
                <button type="button" onClick={() => setEditing(gw)} className="text-xs px-2 py-1 border rounded-lg">
                  تعديل
                </button>
              </div>
            </div>
          ))}
        </div>
      )}

      {(creating || editing) && (
        <GatewayFormModal
          gateway={editing}
          onClose={() => {
            setCreating(false);
            setEditing(null);
          }}
          onSave={async (data) => {
            if (editing) await updatePaymentGateway(editing.id, data);
            else await createPaymentGateway(data);
            setCreating(false);
            setEditing(null);
            await load();
          }}
        />
      )}
    </div>
  );
}
