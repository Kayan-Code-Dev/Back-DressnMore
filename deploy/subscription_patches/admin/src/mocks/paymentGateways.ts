export type GatewayType = 'bank' | 'vodafone_cash' | 'instapay' | 'orange_cash' | 'etisalat_cash' | 'fawry' | 'other';

export interface PaymentGateway {
  id: string;
  name: string;
  type: GatewayType;
  accountHolder: string;
  accountNumber: string;
  bankName?: string;
  iban?: string;
  instructions: string;
  isActive: boolean;
  displayOrder: number;
  createdAt: string;
  usageCount: number;
}

export const paymentGatewaysData: PaymentGateway[] = [
  {
    id: 'pg-001',
    name: 'البنك الأهلي السعودي',
    type: 'bank',
    accountHolder: 'شركة درسن مور للتقنية',
    accountNumber: '1234567890',
    bankName: 'البنك الأهلي السعودي (SNB)',
    iban: 'SA12 1000 0000 1234 5678 9012',
    instructions: 'يرجى تحويل المبلغ مع ذكر رقم الفاتورة في خانة ملاحظات التحويل، وإرسال صورة إيصال التحويل إلى support@dressnmore.sa',
    isActive: true,
    displayOrder: 1,
    createdAt: '2025-01-15',
    usageCount: 89,
  },
  {
    id: 'pg-002',
    name: 'فودافون كاش',
    type: 'vodafone_cash',
    accountHolder: 'أحمد محمد الراشدي',
    accountNumber: '01012345678',
    instructions: 'أرسل المبلغ على رقم فودافون كاش المذكور، ثم أرسل صورة الإيصال مع رقم الفاتورة عبر واتساب على نفس الرقم.',
    isActive: true,
    displayOrder: 2,
    createdAt: '2025-02-10',
    usageCount: 142,
  },
  {
    id: 'pg-003',
    name: 'انستاباي',
    type: 'instapay',
    accountHolder: 'سارة عبدالله الحربي',
    accountNumber: 'dressnmore@instapay',
    instructions: 'ابحث عن @dressnmore في تطبيق InstaPay وأرسل المبلغ مباشرة. أرسل لقطة شاشة للإيصال إلى support@dressnmore.sa مع رقم طلبك.',
    isActive: true,
    displayOrder: 3,
    createdAt: '2025-03-01',
    usageCount: 213,
  },
  {
    id: 'pg-004',
    name: 'مصرف الراجحي',
    type: 'bank',
    accountHolder: 'شركة درسن مور للتقنية',
    accountNumber: '0987654321',
    bankName: 'مصرف الراجحي',
    iban: 'SA98 8000 0000 0987 6543 2100',
    instructions: 'التحويل متاح 24/7 عبر تطبيق الراجحي أو من خلال أي بنك آخر. يرجى ذكر اسم الأتيليه ورقم الفاتورة في حقل الملاحظات.',
    isActive: true,
    displayOrder: 4,
    createdAt: '2025-01-20',
    usageCount: 67,
  },
  {
    id: 'pg-005',
    name: 'أورنج كاش',
    type: 'orange_cash',
    accountHolder: 'خالد إبراهيم المطيري',
    accountNumber: '01098765432',
    instructions: 'يمكنك الدفع عبر تطبيق أورنج كاش أو من خلال أي محفظة إلكترونية متوافقة. أرسل الإيصال مع رقم الطلب عبر البريد الإلكتروني.',
    isActive: false,
    displayOrder: 5,
    createdAt: '2025-04-05',
    usageCount: 18,
  },
  {
    id: 'pg-006',
    name: 'فوري',
    type: 'fawry',
    accountHolder: 'درسن مور',
    accountNumber: 'DNMR-2025',
    instructions: 'توجه لأقرب نقطة فوري مع رمز الدفع DNMR-2025. الدفع متاح في أكثر من 200,000 نقطة في مصر.',
    isActive: true,
    displayOrder: 6,
    createdAt: '2025-05-12',
    usageCount: 34,
  },
];
