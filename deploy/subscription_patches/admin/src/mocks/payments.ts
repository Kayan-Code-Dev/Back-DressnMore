export type PaymentStatus = 'paid' | 'pending' | 'refunded' | 'failed';
export type PaymentMethod = 'visa' | 'mada' | 'stc' | 'bank' | 'cash';

export interface Payment {
  id: string;
  invoiceId: string;
  atelier: string;
  atelierEmail: string;
  amount: number;
  method: PaymentMethod;
  status: PaymentStatus;
  date: string;
  dueDate: string;
  plan: string;
  billingCycle: 'monthly' | 'yearly';
}

export const paymentsData: Payment[] = [
  { id: 'p-001', invoiceId: 'INV-2026-0301', atelier: 'Elegance Studio',   atelierEmail: 'ahmed@elegance.com',      amount: 299,  method: 'visa',  status: 'paid',     date: '2026-03-18', dueDate: '2026-03-18', plan: 'Enterprise', billingCycle: 'monthly' },
  { id: 'p-002', invoiceId: 'INV-2026-0302', atelier: 'Couture House',     atelierEmail: 'sara@couturehouse.sa',    amount: 99,   method: 'mada',  status: 'paid',     date: '2026-03-17', dueDate: '2026-03-17', plan: 'Pro',        billingCycle: 'monthly' },
  { id: 'p-003', invoiceId: 'INV-2026-0303', atelier: 'La Belle Mode',     atelierEmail: 'lina@labellemode.com',   amount: 2990, method: 'bank',  status: 'paid',     date: '2026-03-17', dueDate: '2026-03-20', plan: 'Enterprise', billingCycle: 'yearly'  },
  { id: 'p-004', invoiceId: 'INV-2026-0304', atelier: 'Maison Chic',       atelierEmail: 'nadia@maisonchic.sa',    amount: 99,   method: 'visa',  status: 'pending',  date: '2026-03-16', dueDate: '2026-03-23', plan: 'Pro',        billingCycle: 'monthly' },
  { id: 'p-005', invoiceId: 'INV-2026-0305', atelier: 'Fashion Forward',   atelierEmail: 'omar@fashforward.sa',    amount: 29,   method: 'mada',  status: 'refunded', date: '2026-03-15', dueDate: '2026-03-15', plan: 'Starter',    billingCycle: 'monthly' },
  { id: 'p-006', invoiceId: 'INV-2026-0306', atelier: 'Royal Threads',     atelierEmail: 'khalid@royalthreads.sa', amount: 2990, method: 'bank',  status: 'paid',     date: '2026-03-15', dueDate: '2026-03-15', plan: 'Enterprise', billingCycle: 'yearly'  },
  { id: 'p-007', invoiceId: 'INV-2026-0307', atelier: 'Silk & Style',      atelierEmail: 'fatima@silkstyle.com',   amount: 990,  method: 'visa',  status: 'paid',     date: '2026-03-14', dueDate: '2026-03-14', plan: 'Pro',        billingCycle: 'yearly'  },
  { id: 'p-008', invoiceId: 'INV-2026-0308', atelier: 'Vogue Atelier',     atelierEmail: 'hassan@vogue.com',       amount: 99,   method: 'stc',   status: 'paid',     date: '2026-03-13', dueDate: '2026-03-13', plan: 'Pro',        billingCycle: 'monthly' },
  { id: 'p-009', invoiceId: 'INV-2026-0309', atelier: 'Chic Couture',      atelierEmail: 'dina@chiccouture.sa',    amount: 299,  method: 'visa',  status: 'paid',     date: '2026-03-12', dueDate: '2026-03-12', plan: 'Enterprise', billingCycle: 'monthly' },
  { id: 'p-010', invoiceId: 'INV-2026-0310', atelier: 'Modern Stitch',     atelierEmail: 'youssef@mstitch.sa',     amount: 29,   method: 'mada',  status: 'failed',   date: '2026-03-11', dueDate: '2026-03-11', plan: 'Starter',    billingCycle: 'monthly' },
  { id: 'p-011', invoiceId: 'INV-2026-0311', atelier: 'Luxury Loom',       atelierEmail: 'maya@luxuryloom.sa',     amount: 99,   method: 'stc',   status: 'paid',     date: '2026-03-10', dueDate: '2026-03-10', plan: 'Pro',        billingCycle: 'monthly' },
  { id: 'p-012', invoiceId: 'INV-2026-0312', atelier: 'Prestige Wear',     atelierEmail: 'amira@prestige.sa',      amount: 990,  method: 'bank',  status: 'pending',  date: '2026-03-09', dueDate: '2026-03-16', plan: 'Pro',        billingCycle: 'yearly'  },
  { id: 'p-013', invoiceId: 'INV-2026-0213', atelier: 'Elegance Studio',   atelierEmail: 'ahmed@elegance.com',     amount: 299,  method: 'visa',  status: 'paid',     date: '2026-02-18', dueDate: '2026-02-18', plan: 'Enterprise', billingCycle: 'monthly' },
  { id: 'p-014', invoiceId: 'INV-2026-0214', atelier: 'Royal Threads',     atelierEmail: 'khalid@royalthreads.sa', amount: 299,  method: 'bank',  status: 'refunded', date: '2026-02-15', dueDate: '2026-02-15', plan: 'Enterprise', billingCycle: 'monthly' },
  { id: 'p-015', invoiceId: 'INV-2026-0215', atelier: 'The Fabric Lab',    atelierEmail: 'kareem@fabriclab.sa',    amount: 299,  method: 'visa',  status: 'failed',   date: '2026-02-10', dueDate: '2026-02-10', plan: 'Enterprise', billingCycle: 'monthly' },
];
