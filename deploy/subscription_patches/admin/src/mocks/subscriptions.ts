export type SubscriptionStatus = 'active' | 'cancelled' | 'pending' | 'expired' | 'trial';

export interface Subscription {
  id: string;
  atelierName: string;
  atelierOwner: string;
  plan: string;
  price: number;
  billingCycle: 'monthly' | 'yearly';
  status: SubscriptionStatus;
  startDate: string;
  renewalDate: string;
  lastPayment: string;
}

export const subscriptionsData: Subscription[] = [
  { id: 'SUB-001', atelierName: 'Elegance Studio', atelierOwner: 'Ahmed Al-Rashidi', plan: 'Enterprise', price: 299, billingCycle: 'monthly', status: 'active', startDate: '2024-06-12', renewalDate: '2026-04-12', lastPayment: '2026-03-12' },
  { id: 'SUB-002', atelierName: 'Couture House', atelierOwner: 'Sara Khalil', plan: 'Pro', price: 99, billingCycle: 'monthly', status: 'active', startDate: '2024-09-03', renewalDate: '2026-04-03', lastPayment: '2026-03-03' },
  { id: 'SUB-003', atelierName: 'La Belle Mode', atelierOwner: 'Lina Moussa', plan: 'Enterprise', price: 2990, billingCycle: 'yearly', status: 'active', startDate: '2024-03-20', renewalDate: '2027-03-20', lastPayment: '2026-03-20' },
  { id: 'SUB-004', atelierName: 'Maison Chic', atelierOwner: 'Nadia Hassan', plan: 'Pro', price: 99, billingCycle: 'monthly', status: 'pending', startDate: '2026-02-14', renewalDate: '2026-04-14', lastPayment: '2026-02-14' },
  { id: 'SUB-005', atelierName: 'Fashion Forward', atelierOwner: 'Omar Farouk', plan: 'Starter', price: 29, billingCycle: 'monthly', status: 'cancelled', startDate: '2025-07-08', renewalDate: '2026-07-08', lastPayment: '2026-02-08' },
  { id: 'SUB-006', atelierName: 'Royal Threads', atelierOwner: 'Khalid Sami', plan: 'Enterprise', price: 2990, billingCycle: 'yearly', status: 'active', startDate: '2024-01-15', renewalDate: '2027-01-15', lastPayment: '2026-01-15' },
  { id: 'SUB-007', atelierName: 'Silk & Style', atelierOwner: 'Fatima Al-Zahra', plan: 'Pro', price: 990, billingCycle: 'yearly', status: 'active', startDate: '2025-04-22', renewalDate: '2027-04-22', lastPayment: '2026-04-22' },
  { id: 'SUB-008', atelierName: "Tailor's Touch", atelierOwner: 'Rania Adel', plan: 'Starter', price: 0, billingCycle: 'monthly', status: 'trial', startDate: '2026-03-10', renewalDate: '2026-04-10', lastPayment: '-' },
  { id: 'SUB-009', atelierName: 'Vogue Atelier', atelierOwner: 'Hassan Tamer', plan: 'Pro', price: 99, billingCycle: 'monthly', status: 'active', startDate: '2024-11-30', renewalDate: '2026-04-30', lastPayment: '2026-03-30' },
  { id: 'SUB-010', atelierName: 'Chic Couture', atelierOwner: 'Dina Qassem', plan: 'Enterprise', price: 299, billingCycle: 'monthly', status: 'active', startDate: '2024-05-18', renewalDate: '2026-04-18', lastPayment: '2026-03-18' },
  { id: 'SUB-011', atelierName: 'Modern Stitch', atelierOwner: 'Youssef Mansour', plan: 'Starter', price: 29, billingCycle: 'monthly', status: 'active', startDate: '2025-12-05', renewalDate: '2026-04-05', lastPayment: '2026-03-05' },
  { id: 'SUB-012', atelierName: 'Luxury Loom', atelierOwner: 'Maya Suleiman', plan: 'Pro', price: 99, billingCycle: 'monthly', status: 'active', startDate: '2025-08-14', renewalDate: '2026-04-14', lastPayment: '2026-03-14' },
  { id: 'SUB-013', atelierName: 'The Fabric Lab', atelierOwner: 'Kareem Nabil', plan: 'Enterprise', price: 299, billingCycle: 'monthly', status: 'expired', startDate: '2023-12-01', renewalDate: '2026-02-01', lastPayment: '2026-01-01' },
  { id: 'SUB-014', atelierName: 'Prestige Wear', atelierOwner: 'Amira Fawzy', plan: 'Pro', price: 990, billingCycle: 'yearly', status: 'active', startDate: '2025-06-10', renewalDate: '2027-06-10', lastPayment: '2026-06-10' },
];
