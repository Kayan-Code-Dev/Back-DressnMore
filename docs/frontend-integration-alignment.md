# المستوى 0 — مواءمة التكامل Front ↔ Back (تقدّم)

هذا المستند يوثّق ما أُنجز فعلياً من المستوى 0 (عوائق التكامل) وما تبقّى.

## ما تم إنجازه واختباره end-to-end

### الباك إند (مرفوع في هذا المستودع/الـ PR)
- **حلّ المستأجر من التوكن**: `TenantResolver` يستنتج المستأجر من `tenant_id` المربوط بالتوكن عند غياب `X-Tenant`/`?tenant`/subdomain. النتيجة: كل النقاط المصادَّقة تعمل دون الحاجة لإرسال `X-Tenant`.
- **إثراء استجابة `login` و`me`**: إضافة `account_type` و`roles` وكائن `endpoints` (`backend_api_url`, `backend_api_origin`, `frontend_app_url`, `reverb_public_url`, `tenant_slug`) ليطابق عقد الفرونت.
- `config/app.php`: إضافة `frontend_url`.

### الفرونت إند (Patch مرفق — راجع `docs/frontend-integration-alignment.patch`)
> لم يُتح رفعه لمستودع الفرونت من هذا الوكيل (صلاحيات)، لذا أُرفق كـ patch للتطبيق.
- إيقاف `withCredentials` (المصادقة Bearer، لتفادي فشل CORS preflight).
- حقن ترويسة `X-Tenant` من `endpoints.tenant_slug` / `tenant.slug`.
- **محوّل فكّ الغلاف**: تحويل غلاف الباك `{success, message, data, meta}` إلى الشكل المتوقّع في الفرونت (قوائم: `{data, current_page, per_page, total, total_pages}` مع `meta.last_page → total_pages`؛ ومفرد: الكائن مباشرة).
- `auth.types`: إضافة `tenant` و`endpoints.tenant_slug`.
- خدمة العملاء: استخدام مسار `/customers` بدل `/clients` (نموذج لإعادة التسمية).

### نتيجة الاختبار (متصفح فعلي)
- تسجيل الدخول ينجح، والتطبيق ينتقل للوحة التحكم وتُحمّل **بالبيانات** دون خطأ "Tenant context is required".
- استجابات `/api/tenant/me` و`/api/tenant/dashboard/overview` = **200**، وترويسة `X-Tenant: test` ظاهرة.
- صفحة الموردين (`/suppliers`) تُحمّل ببيانات حقيقية من الباك.

## ما تبقّى من المستوى 0 (إعادة تسمية الموارد في الفرونت)

نفس نمط `clients→customers`، يلزم مواءمة المسارات التالية (frontend → backend):

| مسار الفرونت | مسار الباك الصحيح |
|---|---|
| `/categories`, `/subcategories` | `/dress-categories` (مع `parent_id`) |
| `/clothes` | `/dresses` |
| `/transfers` | `/dresses/{id}/transfer` + `/dresses/{id}/inventory-movements` |
| `/orders` (قوائم/CRUD) | `/orders/rental` (قراءة) + `/invoices` (إنشاء/تعديل/حذف) |
| `/tailoring-orders` | `/tailoring/orders` |
| `/supplier-orders` | `/purchase-orders` |
| `/employee-custodies` | `/employees/custodies` |
| `/departments` | `/hr/departments` |
| `/job-titles` | `/hr/job-titles` |
| `/roles` | `/hr/access/roles` (قراءة فقط في الباك) |
| `/transactions` | `/cashboxes/statement/ledger` |
| `/cities`, `/currencies` | غير متوفّرة كمورد مستأجر في الباك — تحتاج قراراً (إضافة نقاط بالباك أو إزالة الميزة) |
| `/workshops/{id}/update-cloth-status`, `/return-cloth` | غير موجودة (الورشة للقراءة فقط في الباك) — تحتاج نقاط جديدة بالباك |

> ملاحظة: قد تحتاج بعض الوحدات لمواءمة أسماء الحقول داخل الكائن (وليس المسار فقط)؛ يُنصح باختبار كل وحدة بعد إعادة التسمية.

## وحدات تعمل الآن دون إعادة تسمية (تطابق المسار)
لوحة التحكم، `me`/الحساب، العملاء (بعد التعديل)، الإشعارات، المصروفات، المدفوعات، الصناديق، الفروع، الموردون، المصانع، الموظفون.
