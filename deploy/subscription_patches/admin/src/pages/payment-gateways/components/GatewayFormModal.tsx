import { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { PaymentGateway, GatewayType } from '../../../mocks/paymentGateways';

interface GatewayFormModalProps {
  gateway: PaymentGateway | null;
  onClose: () => void;
  onSave: (data: Omit<PaymentGateway, 'id' | 'createdAt' | 'usageCount'>) => void;
}

const GATEWAY_TYPES: GatewayType[] = [
  'bank', 'vodafone_cash', 'instapay', 'orange_cash', 'etisalat_cash', 'fawry', 'other',
];

const TYPE_ICONS: Record<GatewayType, string> = {
  bank: 'ri-bank-line',
  vodafone_cash: 'ri-smartphone-line',
  instapay: 'ri-flashlight-line',
  orange_cash: 'ri-money-dollar-circle-line',
  etisalat_cash: 'ri-sim-card-line',
  fawry: 'ri-store-line',
  other: 'ri-wallet-3-line',
};

const TYPE_COLORS: Record<GatewayType, string> = {
  bank: 'bg-teal-100 text-teal-700',
  vodafone_cash: 'bg-rose-100 text-rose-700',
  instapay: 'bg-amber-100 text-amber-700',
  orange_cash: 'bg-orange-100 text-orange-700',
  etisalat_cash: 'bg-green-100 text-green-700',
  fawry: 'bg-indigo-100 text-indigo-700',
  other: 'bg-gray-100 text-gray-700',
};

export default function GatewayFormModal({ gateway, onClose, onSave }: GatewayFormModalProps) {
  const { t } = useTranslation();
  const isEdit = !!gateway;

  const [form, setForm] = useState({
    name: '',
    type: 'bank' as GatewayType,
    accountHolder: '',
    accountNumber: '',
    bankName: '',
    iban: '',
    instructions: '',
    isActive: true,
    displayOrder: 1,
  });

  const [errors, setErrors] = useState<Record<string, string>>({});

  useEffect(() => {
    if (gateway) {
      setForm({
        name: gateway.name,
        type: gateway.type,
        accountHolder: gateway.accountHolder,
        accountNumber: gateway.accountNumber,
        bankName: gateway.bankName || '',
        iban: gateway.iban || '',
        instructions: gateway.instructions,
        isActive: gateway.isActive,
        displayOrder: gateway.displayOrder,
      });
    }
  }, [gateway]);

  const handleChange = (field: string, value: string | boolean | number) => {
    setForm((prev) => ({ ...prev, [field]: value }));
    if (errors[field]) setErrors((prev) => ({ ...prev, [field]: '' }));
  };

  const validate = () => {
    const newErrors: Record<string, string> = {};
    if (!form.name.trim()) newErrors.name = 'هذا الحقل مطلوب';
    if (!form.accountHolder.trim()) newErrors.accountHolder = 'هذا الحقل مطلوب';
    if (!form.accountNumber.trim()) newErrors.accountNumber = 'هذا الحقل مطلوب';
    if (!form.instructions.trim()) newErrors.instructions = 'هذا الحقل مطلوب';
    if (form.type === 'bank' && !form.bankName.trim()) newErrors.bankName = 'اسم البنك مطلوب';
    return newErrors;
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    const newErrors = validate();
    if (Object.keys(newErrors).length > 0) {
      setErrors(newErrors);
      return;
    }
    onSave({
      name: form.name.trim(),
      type: form.type,
      accountHolder: form.accountHolder.trim(),
      accountNumber: form.accountNumber.trim(),
      bankName: form.type === 'bank' ? form.bankName.trim() : undefined,
      iban: form.iban.trim() || undefined,
      instructions: form.instructions.trim(),
      isActive: form.isActive,
      displayOrder: Number(form.displayOrder),
    });
  };

  const isBank = form.type === 'bank';

  return (
    <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-2xl w-full max-w-2xl max-h-[92vh] flex flex-col">
        {/* Header */}
        <div className="flex items-center justify-between px-6 py-5 border-b border-gray-100">
          <div>
            <h2 className="text-lg font-bold text-gray-900">
              {isEdit ? t('payment_gateways.form.title_edit') : t('payment_gateways.form.title_add')}
            </h2>
            <p className="text-xs text-gray-400 mt-0.5">
              {isEdit ? 'تعديل معلومات بوابة الدفع الموجودة' : 'أضف طريقة دفع يدوية جديدة للأتيليهات'}
            </p>
          </div>
          <button
            type="button"
            onClick={onClose}
            className="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 text-gray-400 hover:text-gray-600 transition-colors cursor-pointer"
          >
            <i className="ri-close-line text-lg" />
          </button>
        </div>

        {/* Form */}
        <form onSubmit={handleSubmit} className="flex-1 overflow-y-auto">
          <div className="px-6 py-5 space-y-5">

            {/* Gateway Type Selector */}
            <div>
              <label className="block text-sm font-semibold text-gray-700 mb-2">
                {t('payment_gateways.form.type')}
              </label>
              <div className="grid grid-cols-4 gap-2">
                {GATEWAY_TYPES.map((type) => (
                  <button
                    key={type}
                    type="button"
                    onClick={() => handleChange('type', type)}
                    className={`flex flex-col items-center gap-1.5 px-2 py-3 rounded-xl border-2 transition-all cursor-pointer ${
                      form.type === type
                        ? 'border-teal-500 bg-teal-50'
                        : 'border-gray-200 hover:border-gray-300 bg-white'
                    }`}
                  >
                    <div className={`w-8 h-8 flex items-center justify-center rounded-lg ${TYPE_COLORS[type]}`}>
                      <i className={`${TYPE_ICONS[type]} text-sm`} />
                    </div>
                    <span className="text-xs font-medium text-gray-700 text-center leading-tight">
                      {t(`payment_gateways.types.${type}`)}
                    </span>
                  </button>
                ))}
              </div>
            </div>

            {/* Gateway Name */}
            <div>
              <label className="block text-sm font-semibold text-gray-700 mb-1.5">
                {t('payment_gateways.form.name')}
              </label>
              <input
                type="text"
                value={form.name}
                onChange={(e) => handleChange('name', e.target.value)}
                placeholder={t('payment_gateways.form.name_placeholder')}
                className={`w-full px-3 py-2.5 text-sm border rounded-lg outline-none transition-all ${
                  errors.name ? 'border-rose-400 bg-rose-50' : 'border-gray-200 focus:border-teal-500 focus:ring-2 focus:ring-teal-500/10'
                }`}
              />
              {errors.name && <p className="text-xs text-rose-500 mt-1">{errors.name}</p>}
            </div>

            {/* Account Holder + Account Number */}
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-semibold text-gray-700 mb-1.5">
                  {t('payment_gateways.form.account_holder')}
                </label>
                <input
                  type="text"
                  value={form.accountHolder}
                  onChange={(e) => handleChange('accountHolder', e.target.value)}
                  placeholder={t('payment_gateways.form.account_holder_placeholder')}
                  className={`w-full px-3 py-2.5 text-sm border rounded-lg outline-none transition-all ${
                    errors.accountHolder ? 'border-rose-400 bg-rose-50' : 'border-gray-200 focus:border-teal-500 focus:ring-2 focus:ring-teal-500/10'
                  }`}
                />
                {errors.accountHolder && <p className="text-xs text-rose-500 mt-1">{errors.accountHolder}</p>}
              </div>
              <div>
                <label className="block text-sm font-semibold text-gray-700 mb-1.5">
                  {t('payment_gateways.form.account_number')}
                </label>
                <input
                  type="text"
                  value={form.accountNumber}
                  onChange={(e) => handleChange('accountNumber', e.target.value)}
                  placeholder={t('payment_gateways.form.account_number_placeholder')}
                  className={`w-full px-3 py-2.5 text-sm border rounded-lg outline-none transition-all font-mono ${
                    errors.accountNumber ? 'border-rose-400 bg-rose-50' : 'border-gray-200 focus:border-teal-500 focus:ring-2 focus:ring-teal-500/10'
                  }`}
                />
                {errors.accountNumber && <p className="text-xs text-rose-500 mt-1">{errors.accountNumber}</p>}
              </div>
            </div>

            {/* Bank fields - conditional */}
            {isBank && (
              <div className="grid grid-cols-2 gap-4 p-4 bg-teal-50/60 rounded-xl border border-teal-100">
                <div>
                  <label className="block text-sm font-semibold text-gray-700 mb-1.5">
                    {t('payment_gateways.form.bank_name')}
                  </label>
                  <input
                    type="text"
                    value={form.bankName}
                    onChange={(e) => handleChange('bankName', e.target.value)}
                    placeholder={t('payment_gateways.form.bank_name_placeholder')}
                    className={`w-full px-3 py-2.5 text-sm border rounded-lg outline-none transition-all bg-white ${
                      errors.bankName ? 'border-rose-400' : 'border-gray-200 focus:border-teal-500 focus:ring-2 focus:ring-teal-500/10'
                    }`}
                  />
                  {errors.bankName && <p className="text-xs text-rose-500 mt-1">{errors.bankName}</p>}
                </div>
                <div>
                  <label className="block text-sm font-semibold text-gray-700 mb-1.5">
                    {t('payment_gateways.form.iban')}
                  </label>
                  <input
                    type="text"
                    value={form.iban}
                    onChange={(e) => handleChange('iban', e.target.value)}
                    placeholder={t('payment_gateways.form.iban_placeholder')}
                    className="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-lg outline-none focus:border-teal-500 focus:ring-2 focus:ring-teal-500/10 transition-all font-mono bg-white"
                  />
                </div>
              </div>
            )}

            {/* Instructions */}
            <div>
              <label className="block text-sm font-semibold text-gray-700 mb-1.5">
                {t('payment_gateways.form.instructions')}
              </label>
              <textarea
                value={form.instructions}
                onChange={(e) => handleChange('instructions', e.target.value)}
                placeholder={t('payment_gateways.form.instructions_placeholder')}
                rows={4}
                maxLength={500}
                className={`w-full px-3 py-2.5 text-sm border rounded-lg outline-none transition-all resize-none ${
                  errors.instructions ? 'border-rose-400 bg-rose-50' : 'border-gray-200 focus:border-teal-500 focus:ring-2 focus:ring-teal-500/10'
                }`}
              />
              <div className="flex justify-between items-center mt-1">
                {errors.instructions
                  ? <p className="text-xs text-rose-500">{errors.instructions}</p>
                  : <span />
                }
                <span className="text-xs text-gray-400">{form.instructions.length}/500</span>
              </div>
            </div>

            {/* Display Order + Active toggle */}
            <div className="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
              <div className="flex items-center gap-4">
                <div>
                  <label className="block text-sm font-semibold text-gray-700 mb-1">
                    {t('payment_gateways.form.display_order')}
                  </label>
                  <input
                    type="number"
                    min={1}
                    max={99}
                    value={form.displayOrder}
                    onChange={(e) => handleChange('displayOrder', parseInt(e.target.value, 10) || 1)}
                    className="w-20 px-3 py-2 text-sm border border-gray-200 rounded-lg outline-none focus:border-teal-500 text-center"
                  />
                </div>
              </div>
              <div className="flex items-center gap-3">
                <span className="text-sm font-semibold text-gray-700">
                  {t('payment_gateways.form.active')}
                </span>
                <button
                  type="button"
                  onClick={() => handleChange('isActive', !form.isActive)}
                  className={`relative w-11 h-6 rounded-full transition-colors duration-200 cursor-pointer ${
                    form.isActive ? 'bg-teal-500' : 'bg-gray-300'
                  }`}
                >
                  <span
                    className={`absolute top-0.5 w-5 h-5 bg-white rounded-full shadow transition-all duration-200 ${
                      form.isActive ? 'left-5' : 'left-0.5'
                    }`}
                  />
                </button>
              </div>
            </div>
          </div>

          {/* Footer */}
          <div className="px-6 py-4 border-t border-gray-100 flex gap-3 justify-end">
            <button
              type="button"
              onClick={onClose}
              className="px-5 py-2.5 text-sm font-semibold text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors cursor-pointer whitespace-nowrap"
            >
              {t('payment_gateways.form.cancel')}
            </button>
            <button
              type="submit"
              className="px-6 py-2.5 text-sm font-semibold text-white bg-teal-600 hover:bg-teal-700 rounded-lg transition-colors cursor-pointer whitespace-nowrap"
            >
              {t('payment_gateways.form.save')}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
