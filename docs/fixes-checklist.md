# تشيك ليست الإصلاحات — DressnMore

مرتّبة حسب الأولوية. مبنية على المراجعة والاختبار الفعلي (راجع `docs/client-readiness-report.md`).

---

## المستوى 0 — عوائق التكامل Front ↔ Back (الأهم، تمنع التسليم)

> الفرونت والباك مبنيّان على عقد API مختلف. يجب توحيدهما قبل أي شيء.

- [ ] **توحيد عقد API** بين الفريقين واعتماد `docs/frontend-backend-integration-contract.md` كمرجع مجمَّد.
- [ ] **بادئة المسار**: ضبط قاعدة الفرونت على `/api/tenant` بدل `/api/v1` (ملف `src/api/api-instance.ts` + متغيرات `VITE_*`).
- [ ] **حقن ترويسة `X-Tenant`** في كل طلبات الفرونت (interceptor في `src/api/api-contants.ts`). بدونها كل النقاط المصادَّقة ترجع 400 "Tenant context is required".
- [ ] **إيقاف `withCredentials: true`** في أكسيوس (المصادقة Bearer Token لا تحتاجه) — أو بديلاً تفعيل `supports_credentials => true` في `config/cors.php`. حالياً يسبب حظر CORS.
- [ ] **مواءمة أسماء الموارد** بين الواجهتين، أمثلة: `clients ↔ customers`، `transfers ↔ dresses/{id}/transfer`، وعدم وجود `cities/currencies/roles` كنقاط مستقلة في الباك (تأتي عبر `/lookups` و`/hr/access/roles`).
- [ ] **مواءمة شكل استجابة تسجيل الدخول**: الفرونت يتوقّع `endpoints{backend_api_url, reverb_public_url, frontend_app_url}` و`roles` و`account_type`؛ الباك يُرجع `token, user, tenant, permissions` فقط. توحيد الشكل من الطرفين.
- [ ] اختبار end-to-end لتدفّق: دخول → لوحة تحكم → عملية إيجار → تسليم → إرجاع، بعد المواءمة.

---

## المستوى 1 — الباك إند: عوائق حرجة قبل الإنتاج

- [ ] **إصلاح مجموعة الاختبارات الحمراء** (191/263 فاشل): التعارض بين `app/Http/Middleware/EnsureTenantTokenBinding.php` و`Sanctum::actingAs()` (يُنشئ Mock بـ `tenant_id=null` ⇒ 403). الحل: إمّا إصدار توكنات حقيقية مربوطة بالمستأجر في الاختبارات، أو تكييف الميدلوير لتجاهل توكن `actingAs` (مثلاً فحص `TransientToken`/Mock). الهدف: إعادة CI للأخضر.
- [ ] **Rate Limiting على تسجيل الدخول** (`/api/tenant/login`, `/api/platform/login`) والنقاط العامة (`/api/v1/order-plans`) عبر `throttle`.
- [ ] **انتهاء صلاحية توكنات Sanctum** (`config/sanctum.php` → `expiration`) + سياسة تدوير.
- [ ] **حصر مسارات المستأجر على مستخدمي المستأجر فقط** ومنع وصول توكن مدير المنصّة لمسارات مثل `settings/profile`, `logout`, `me` (ميدلوير `EnsureTenantUser` أو توسيع `EnsureTenantTokenBinding`).

## المستوى 1 — الفرونت إند: عوائق حرجة قبل الإنتاج

- [ ] **ربط وحدة الاشتراك بالـ API الحقيقي** بدل `src/mocks/subscription.ts` (`SubscriptionSettingsTab.tsx`).
- [ ] **إضافة زر تسجيل خروج (Logout)** ظاهر للمستخدم.
- [ ] **إضافة حارس صلاحيات** لمسارات `sales.routes.tsx` و`tailoring.routes.tsx`.
- [ ] **إصلاح `PermissionProtectedRoute`** ليعيد `null`/شاشة "ممنوع" بدل عرض `<Outlet />` أثناء التحويل.

---

## المستوى 2 — أمان وموثوقية متوسطة

- [ ] ربط وحدة HR بميزات الخطة (`plan.feature`) إن كانت تجارياً ضمن باقات (`routes/api/tenant.php` + `PlanFeatureCatalog`).
- [ ] إضافة مفتاح خارجي (FK) لـ `tenants.plan_id` و`invoices.created_by`.
- [ ] عدم كشف اسم قاعدة بيانات المستأجر في نقطة الـ health.
- [ ] إزالة مسار `seed` المكرّر في `routes/api/platform.php`.
- [ ] إعدادات الإنتاج: `APP_DEBUG=false`، ضبط `CORS_ALLOWED_ORIGINS` الحقيقية، كلمة مرور قوية لمدير المنصّة.
- [ ] تحديث الصلاحيات في الفرونت من نقطة حيّة بدل قراءتها من استجابة الدخول فقط.

---

## المستوى 3 — جودة وتلميع (Polish)

- [ ] إضافة اختبارات آلية للفرونت إند (صفر حالياً) + اختبارات أمان للباك (Rate limit، cross-guard، انتهاء التوكن).
- [ ] تقليل استخدام `any` في الفرونت وإعادة تفعيل قاعدة ESLint تدريجياً.
- [ ] إظهار الوحدات المخفيّة في القائمة الجانبية أو حذفها (الصلاحيات/الأدوار، HR الإداري، المخزون — معلّقة في `constants.ts`).
- [ ] معالجة 401 برسالة واضحة للمستخدم بدل خروج صامت.
- [ ] تحديث README بالباك إند ليعكس سطح الـ API الفعلي.
- [ ] توحيد رسائل الأخطاء (عربي/إنجليزي) في الطرفين.
- [ ] إكمال/إزالة الأجزاء المبدئية في الفرونت: مواعيد المبيعات (placeholder)، تقارير التفصيل، `TODO` التحقق من السعر في `UpdateClothesInOrder.tsx`.
