# تقرير الاختبار الشامل — أتيليه (جولة 2)

**التاريخ:** 2026-06-15 00:40
**Tenant:** alhatom-llazyaaa-2
**الحساب:** qa-staging@dressnmore.test

## الملخص

| PASS | FAIL | PARTIAL | المجموع |
|------|------|---------|---------|
| 57 | 0 | 0 | 57 |

**نسبة النجاح:** 100%

**المعرّفات:** `RUN=20260614214028 BMAIN=6 SUP=5 CAT=8 SUB=9 CUST=6 CBOX=5 PO=5 DRESS=3 SALE=8 RENT=9 TAIL=10 EMP=5`

## سير العمل

1. فرع → مورد → طلبية شراء → استلام → مخزون
2. فاتورة بيع → إيجار → تسليم → إرجاع
3. تفصيل → موظف → سلفة → كشف رواتب
4. قيود محاسبية → كشف معاملات → تقارير
5. تصدير PDF/Excel لكل الوحدات
6. البروفايل + الاشتراك + الإعدادات

## النتائج حسب المديول

| المديول | السيناريو | النتيجة | HTTP |
|---------|-----------|---------|------|
| Auth | Owner login | PASS | 200 |
| Branch | Create main branch | PASS | 201 |
| Supplier | Create supplier | PASS | 201 |
| Catalog | Create parent category | PASS | 201 |
| Catalog | Create subcategory parent_id | PASS | 201 |
| Customer | Create customer | PASS | 201 |
| Cashbox | Create cashbox | PASS | 201 |
| Purchase | Create PO | PASS | 201 |
| Purchase | Receive PO -> inventory | PASS | 200 |
| Inventory | List dresses after PO | PASS | 200 |
| Inventory | Set sale/rent prices | PASS | 200 |
| Sales | Create sale invoice | PASS | 201 |
| Rental | Create rental invoice | PASS | 201 |
| Rental | Deliver dress | PASS | 200 |
| Rental | Return dress | PASS | 200 |
| Tailoring | Create tailoring order | PASS | 201 |
| HR | GET roles | PASS | 200 |
| HR | Create employee | PASS | 201 |
| HR | Add salary advance | PASS | 201 |
| HR | Payroll sheet | PASS | 200 |
| HR | Employee payslip | PASS | 200 |
| Accounting | List accounts | PASS | 200 |
| Accounting | Manual journal entry | PASS | 201 |
| Accounting | Journal summary | PASS | 200 |
| Statement | Branch summaries | PASS | 200 |
| Statement | Ledger | PASS | 200 |
| Statement | Export PDF | PASS | 200 |
| Statement | Export Excel | PASS | 200 |
| Reports | PDF sales | PASS | 200 |
| Reports | Excel sales | PASS | 200 |
| Reports | PDF rental | PASS | 200 |
| Reports | Excel rental | PASS | 200 |
| Reports | PDF tailoring | PASS | 200 |
| Reports | Excel tailoring | PASS | 200 |
| Reports | PDF payments | PASS | 200 |
| Reports | Excel payments | PASS | 200 |
| Reports | PDF expenses | PASS | 200 |
| Reports | Excel expenses | PASS | 200 |
| Reports | PDF cash | PASS | 200 |
| Reports | Excel cash | PASS | 200 |
| Reports | PDF accounting | PASS | 200 |
| Reports | Excel accounting | PASS | 200 |
| Exports | Invoices PDF | PASS | 200 |
| Exports | Invoices Excel | PASS | 200 |
| Exports | Cashboxes PDF | PASS | 200 |
| Exports | Cashboxes Excel | PASS | 200 |
| Exports | Journal PDF | PASS | 200 |
| Exports | Journal Excel | PASS | 200 |
| Settings | GET profile | PASS | 200 |
| Settings | UPDATE profile name | PASS | 200 |
| Subscription | Overview | PASS | 200 |
| Subscription | Payment gateways | PASS | 200 |
| Lists | GET /dashboard/overview | PASS | 200 |
| Lists | GET /deliveries?per_page=3 | PASS | 200 |
| Lists | GET /returns?per_page=3 | PASS | 200 |
| Lists | GET /payments?per_page=3 | PASS | 200 |
| Lists | GET /expenses?per_page=3 | PASS | 200 |

## التوصية

جاهز للتشغيل الداخلي

*الأدلة:* `comprehensive-atelier-qa-results.json`