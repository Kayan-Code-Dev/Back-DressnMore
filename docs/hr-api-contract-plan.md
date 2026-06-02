# HR Module — Backend API Contract Plan

**Status:** Planning only (no implementation)  
**Last updated:** 2026-06-02  
**Audience:** Backend, frontend, QA, product  
**Related:** `docs/api-contract.md`, `docs/tenant-isolation.md`, `docs/permissions-map.md`

---

## 1. Concept Overview

The **HR module** is a **tenant-scoped staff management system** for DressnMore atelier businesses. Each tenant manages its own employees, schedules, attendance, leave, compensation, and documents in an **isolated tenant database**. No HR data is shared across tenants.

The module supports the operational reality of ateliers: branch-based teams (sales, tailoring, fitting, reception, delivery, accounting), shift-based attendance, monthly payroll preparation, advances and deductions, performance bonuses, sales/rental/tailoring commissions, and compliance documents (contracts, IDs, insurance).

### Scope (tenant DB)

| Domain | Purpose |
|--------|---------|
| Employee profiles | Core HR record, branch/department/job assignment, salary profile |
| Departments & job titles | Lookups and org structure |
| Shifts & assignments | Working hours, grace/break rules, employee shift history |
| Attendance | Daily check-in/out, late/overtime, statuses |
| Leave | Types, balances, requests, approvals |
| Payroll | Monthly runs, per-employee items, payslips, approval/payment lifecycle |
| Advances & deductions | Employee loans and penalties linked to payroll |
| Bonuses & commissions | Discretionary rewards and invoice-linked commissions |
| Documents | Employee file storage metadata + expiry tracking |
| Notes | Internal HR / performance / warning notes |
| Settings | Tenant-configurable payroll/attendance rules (JSON in `hr_settings`) |
| Reports | Filtered tabular exports aligned with frontend HR reports hub |

### API access model

All HR endpoints live under **`/api/tenant/hr/...`** and require:

1. `Authorization: Bearer {sanctum_token}` (tenant-bound token; see `EnsureTenantTokenBinding`)
2. `X-Tenant: {tenant_slug}` (must match token tenant)
3. Tenant middleware stack: `identify.tenant` → `check.tenant.subscription` → `set.tenant.database` → `auth:sanctum` → `ensure.tenant.token`
4. Route-level `tenant.permission:hr.*` keys (see §6)
5. Optional future `plan.feature:hr.enabled` (not defined today; recommend adding with plan catalog)

Responses use the standard envelope from `App\Support\ApiResponse` (see `docs/api-contract.md`).

### Distinction from existing `/api/tenant/employees`

The codebase currently exposes a lightweight **`/api/tenant/employees`** group guarded by `users.manage`. That is **not** the HR module. The new HR API is namespaced under **`/api/tenant/hr`** with dedicated tables (`hr_*`), permissions (`hr.*`), and frontend routes (`/hr/*`). Legacy employee endpoints remain unchanged until an explicit migration/deprecation phase.

---

## 2. Database Model Plan

All tables use the **`tenant`** connection. Migrations live in `database/migrations/tenant/`.

### Naming & conventions

- Table prefix: `hr_`
- Primary keys: `id` (bigint unsigned)
- Money: `decimal(12,2)` unless noted
- Times: `time` for shift times; datetimes UTC in DB
- Soft deletes: **`hr_employees` only** (HR staff records); lookups use `status` enum instead
- Audit: `created_by`, `approved_by`, `uploaded_by` reference `users.id` (tenant `users` table)
- FK to `branches.id` where `branch_id` is used (existing tenant table)

### A) `hr_employees`

| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| employee_code | string(50) | **Unique per tenant** |
| full_name | string(190) | |
| avatar_path | string nullable | Tenant path e.g. `tenants/{tenant_id}/hr/employees/{id}/avatar/{uuid}.ext` |
| phone | string(30) nullable | |
| email | string(190) nullable | |
| national_id | string(50) nullable | **Unique per tenant when not null** |
| date_of_birth | date nullable | |
| gender | enum nullable | `male`, `female`, `other` |
| address | text nullable | |
| branch_id | FK nullable → `branches.id` | `nullOnDelete` or restrict per product decision |
| department_id | FK nullable → `hr_departments.id` | |
| job_title_id | FK nullable → `hr_job_titles.id` | |
| employment_type | enum | `full_time`, `part_time`, `contractor`, `temporary` |
| status | enum | `active`, `inactive`, `suspended`, `terminated` |
| joining_date | date | |
| leaving_date | date nullable | Required when `status = terminated` (business rule) |
| base_salary | decimal(12,2) | Default 0 |
| salary_type | enum | `monthly`, `daily`, `hourly` |
| working_hours_per_day | decimal(4,2) nullable | e.g. 8.00 |
| emergency_contact_name | string(120) nullable | |
| emergency_contact_phone | string(30) nullable | |
| notes | text nullable | |
| created_at, updated_at | timestamps | |
| deleted_at | timestamp nullable | Soft delete |

**Indexes:** `unique(employee_code)`, `unique(national_id)` partial where not null, `index(branch_id)`, `index(department_id)`, `index(status)`, `index(joining_date)`.

### B) `hr_departments`

| Column | Type |
|--------|------|
| id | bigint PK |
| name | string(120) **unique** |
| status | enum `active`, `inactive` |
| created_at, updated_at | timestamps |

### C) `hr_job_titles`

| Column | Type |
|--------|------|
| id | bigint PK |
| department_id | FK nullable → `hr_departments.id` |
| title | string(120) |
| status | enum `active`, `inactive` |
| created_at, updated_at | timestamps |

**Indexes:** `unique(department_id, title)` (nullable department treated as global title group).

### D) `hr_shifts`

| Column | Type |
|--------|------|
| id | bigint PK |
| name | string(120) |
| start_time | time |
| end_time | time |
| break_minutes | unsigned smallint default 0 |
| grace_minutes | unsigned smallint default 0 |
| working_days | json | Array of `sun`…`sat` |
| branch_id | FK nullable → `branches.id` |
| status | enum `active`, `inactive` |
| created_at, updated_at | timestamps |

**Indexes:** `index(branch_id)`, `index(status)`.

### E) `hr_employee_shifts`

| Column | Type |
|--------|------|
| id | bigint PK |
| employee_id | FK → `hr_employees.id` cascade |
| shift_id | FK → `hr_shifts.id` restrict |
| effective_from | date |
| effective_to | date nullable |
| created_at, updated_at | timestamps |

**Indexes:** `index(employee_id, effective_from)`, `index(shift_id)`.

### F) `hr_attendance`

| Column | Type |
|--------|------|
| id | bigint PK |
| employee_id | FK → `hr_employees.id` cascade |
| branch_id | FK nullable → `branches.id` |
| shift_id | FK nullable → `hr_shifts.id` |
| date | date |
| check_in | time nullable | |
| check_out | time nullable | |
| late_minutes | unsigned int default 0 | |
| overtime_minutes | unsigned int default 0 | |
| status | enum | `present`, `absent`, `late`, `half_day`, `day_off`, `leave`, `holiday` |
| notes | text nullable | |
| created_by | FK nullable → `users.id` |
| created_at, updated_at | timestamps |

**Indexes:** `unique(employee_id, date)`, `index(date)`, `index(branch_id)`, `index(status)`.

### G) `hr_leave_types`

| Column | Type |
|--------|------|
| id | bigint PK |
| name | string(120) |
| code | string(50) **unique** | e.g. `annual`, `sick` |
| paid | boolean default true | |
| annual_balance_days | decimal(6,2) nullable | Default entitlement template |
| status | enum `active`, `inactive` |
| created_at, updated_at | timestamps |

### H) `hr_leave_requests`

| Column | Type |
|--------|------|
| id | bigint PK |
| employee_id | FK → `hr_employees.id` cascade |
| leave_type_id | FK → `hr_leave_types.id` restrict |
| from_date | date |
| to_date | date |
| days_count | decimal(6,2) | Computed/stored |
| status | enum | `pending`, `approved`, `rejected`, `cancelled` |
| reason | text nullable | |
| manager_note | text nullable | |
| approved_by | FK nullable → `users.id` |
| approved_at | timestamp nullable | |
| created_at, updated_at | timestamps |

**Indexes:** `index(employee_id)`, `index(status)`, `index(from_date, to_date)`.

### I) `hr_leave_balances`

| Column | Type |
|--------|------|
| id | bigint PK |
| employee_id | FK → `hr_employees.id` cascade |
| leave_type_id | FK → `hr_leave_types.id` cascade |
| year | smallint unsigned | |
| opening_balance | decimal(6,2) default 0 | |
| accrued | decimal(6,2) default 0 | |
| used | decimal(6,2) default 0 | |
| remaining | decimal(6,2) default 0 | |
| created_at, updated_at | timestamps |

**Indexes:** `unique(employee_id, leave_type_id, year)`.

### J) `hr_payroll_runs`

| Column | Type |
|--------|------|
| id | bigint PK |
| month | tinyint unsigned | 1–12 |
| year | smallint unsigned | |
| branch_id | FK nullable → `branches.id` | null = all branches |
| status | enum | `draft`, `pending_review`, `approved`, `paid`, `cancelled` |
| gross_salaries | decimal(14,2) default 0 | Aggregated |
| total_advances | decimal(14,2) default 0 | |
| total_deductions | decimal(14,2) default 0 | |
| total_bonuses | decimal(14,2) default 0 | |
| total_commissions | decimal(14,2) default 0 | |
| net_payroll | decimal(14,2) default 0 | |
| generated_by | FK nullable → `users.id` |
| approved_by | FK nullable → `users.id` |
| paid_by | FK nullable → `users.id` |
| paid_at | timestamp nullable | |
| created_at, updated_at | timestamps |

**Indexes:** `unique(year, month, branch_id)` where status ≠ `cancelled` (enforce in app layer or partial unique index per DB support), `index(status)`.

### K) `hr_payroll_items`

| Column | Type |
|--------|------|
| id | bigint PK |
| payroll_run_id | FK → `hr_payroll_runs.id` cascade |
| employee_id | FK → `hr_employees.id` restrict |
| employee_code | string(50) | Snapshot at generation |
| base_salary | decimal(12,2) | |
| attendance_days | decimal(6,2) default 0 | |
| absent_days | decimal(6,2) default 0 | |
| late_minutes | unsigned int default 0 | |
| overtime_minutes | unsigned int default 0 | |
| overtime_amount | decimal(12,2) default 0 | |
| advances_amount | decimal(12,2) default 0 | |
| deductions_amount | decimal(12,2) default 0 | |
| bonuses_amount | decimal(12,2) default 0 | |
| commissions_amount | decimal(12,2) default 0 | |
| gross_salary | decimal(12,2) default 0 | |
| net_salary | decimal(12,2) default 0 | |
| status | enum | Same lifecycle as run item-level |
| notes | text nullable | |
| created_at, updated_at | timestamps |

**Indexes:** `unique(payroll_run_id, employee_id)`, `index(employee_id)`.

### L) `hr_advances`

| Column | Type |
|--------|------|
| id | bigint PK |
| employee_id | FK → `hr_employees.id` cascade |
| amount | decimal(12,2) | |
| date | date | |
| repayment_type | enum | `one_time`, `installments` |
| installments_count | tinyint nullable | |
| remaining_amount | decimal(12,2) | |
| status | enum | `pending`, `approved`, `partially_paid`, `paid`, `cancelled` |
| notes | text nullable | |
| created_by | FK nullable → `users.id` |
| created_at, updated_at | timestamps |

**Indexes:** `index(employee_id)`, `index(status)`.

### M) `hr_deductions`

| Column | Type |
|--------|------|
| id | bigint PK |
| employee_id | FK → `hr_employees.id` cascade |
| type | string(80) | e.g. `late`, `absence`, `penalty` |
| amount | decimal(12,2) | |
| date | date | |
| reason | text nullable | |
| status | enum | `pending`, `approved`, `applied`, `cancelled` |
| notes | text nullable | |
| created_by | FK nullable → `users.id` |
| created_at, updated_at | timestamps |

### N) `hr_bonuses`

| Column | Type |
|--------|------|
| id | bigint PK |
| employee_id | FK → `hr_employees.id` cascade |
| amount | decimal(12,2) | |
| date | date | |
| reason | text nullable | |
| status | enum | `pending`, `approved`, `applied`, `cancelled` |
| notes | text nullable | |
| created_by | FK nullable → `users.id` |
| created_at, updated_at | timestamps |

### O) `hr_commissions`

| Column | Type |
|--------|------|
| id | bigint PK |
| employee_id | FK → `hr_employees.id` cascade |
| source_type | enum | `sale_invoice`, `rental_invoice`, `tailoring_invoice`, `manual` |
| source_id | bigint nullable | Polymorphic-ish reference to invoice id |
| invoice_reference | string(80) nullable | Human-readable ref |
| rate | decimal(5,2) nullable | Percent |
| amount | decimal(12,2) | |
| date | date | |
| status | enum | `pending`, `approved`, `applied`, `cancelled` |
| notes | text nullable | |
| created_at, updated_at | timestamps |

**Indexes:** `index(source_type, source_id)`, `index(employee_id, date)`.

### P) `hr_documents`

| Column | Type |
|--------|------|
| id | bigint PK |
| employee_id | FK → `hr_employees.id` cascade |
| document_type | enum | `national_id`, `contract`, `certificate`, `medical`, `insurance`, `other` |
| file_name | string(255) | Original filename |
| file_path | string(500) | Tenant storage path |
| issue_date | date nullable | |
| expiry_date | date nullable | |
| status | enum | `valid`, `expiring_soon`, `expired`, `missing` | Computed nightly or on read |
| notes | text nullable | |
| uploaded_by | FK nullable → `users.id` |
| created_at, updated_at | timestamps |

**Indexes:** `index(employee_id)`, `index(expiry_date)`, `index(status)`.

### Q) `hr_notes`

| Column | Type |
|--------|------|
| id | bigint PK |
| employee_id | FK → `hr_employees.id` cascade |
| type | enum | `general`, `performance`, `warning`, `contract`, `payroll` |
| title | string(190) | |
| body | text | |
| created_by | FK nullable → `users.id` |
| created_at, updated_at | timestamps |

### R) `hr_settings`

| Column | Type |
|--------|------|
| id | bigint PK |
| key | string(100) **unique** | e.g. `attendance_rules`, `payroll_rules` |
| value | json | |
| created_at, updated_at | timestamps |

**Suggested keys:** `attendance_rules`, `payroll_rules`, `contract_types`, `document_expiry_alert_days`.

---

## 3. API Endpoint Contract

**Base path:** `/api/tenant/hr`  
**Middleware group:** Same as other tenant authenticated routes (see §1).

### Common query parameters

| Param | Type | Notes |
|-------|------|-------|
| page | int | Default 1 |
| per_page | int | Default 15, max 100 |
| search | string | Module-specific text search |
| branch_id | int | Filter |
| export | string | Reports/payroll: `csv`, `xlsx`, `pdf` (phase 5) |

---

### A) HR Dashboard

#### `GET /api/tenant/hr/dashboard`
- **Permission:** `hr.dashboard.view`
- **Query:** `date` (optional, default today), `branch_id` (optional)
- **Response `data`:**
  - `kpis`: `{ total_employees, active_employees, on_leave_today, late_today, payroll_this_month, pending_requests }`
  - `attendance_snapshot`: `{ present, absent, late, day_off }`
  - `payroll_summary`: `{ gross_salaries, deductions, bonuses, net_payroll }` (current month)
  - `upcoming_events`: `[{ type, date, employee_id, employee_name, label }]`
  - `recent_activity`: `[{ at, title, description, actor }]`

---

### B) Employees

| Method | Path | Permission |
|--------|------|------------|
| GET | `/employees` | `hr.employees.view` |
| POST | `/employees` | `hr.employees.create` |
| GET | `/employees/{id}` | `hr.employees.view` |
| PUT | `/employees/{id}` | `hr.employees.update` |
| DELETE | `/employees/{id}` | `hr.employees.delete` |
| PATCH | `/employees/{id}/status` | `hr.employees.status` |
| GET | `/employees/{id}/summary` | `hr.employees.view` |
| GET | `/employees/{id}/attendance` | `hr.employees.view` |
| GET | `/employees/{id}/payroll` | `hr.employees.view` |
| GET | `/employees/{id}/leaves` | `hr.employees.view` |
| GET | `/employees/{id}/documents` | `hr.documents.view` |
| GET | `/employees/{id}/notes` | `hr.employees.view` |
| POST | `/employees/{id}/avatar` | `hr.employees.update` *(phase 6)* |

**GET `/employees` filters:** `search`, `branch_id`, `department_id`, `job_title_id`, `status`, `employment_type`, `page`, `per_page`.

**PATCH `/employees/{id}/status` body:** `{ "status": "active|inactive|suspended|terminated", "leaving_date": "YYYY-MM-DD?" }`

---

### C) Attendance

| Method | Path | Permission |
|--------|------|------------|
| GET | `/attendance` | `hr.attendance.view` |
| POST | `/attendance` | `hr.attendance.create` |
| PUT | `/attendance/{id}` | `hr.attendance.update` |
| POST | `/attendance/bulk` | `hr.attendance.bulk` |
| GET | `/attendance/summary` | `hr.attendance.view` |

**GET filters:** `date`, `date_from`, `date_to`, `branch_id`, `status`, `employee_id`, `page`, `per_page`.

**POST bulk body:** `{ "date", "branch_id?", "records": [{ "employee_id", "status", "check_in?", "check_out?", "notes?" }] }`

**GET summary:** aggregates for date/range + branch (present/absent/late/leave counts).

---

### D) Shifts

| Method | Path | Permission |
|--------|------|------------|
| GET | `/shifts` | `hr.shifts.view` |
| POST | `/shifts` | `hr.shifts.create` |
| GET | `/shifts/{id}` | `hr.shifts.view` |
| PUT | `/shifts/{id}` | `hr.shifts.update` |
| DELETE | `/shifts/{id}` | `hr.shifts.delete` |
| POST | `/shifts/{id}/assign-employees` | `hr.shifts.assign` |

**Assign body:** `{ "employee_ids": [1,2], "effective_from": "YYYY-MM-DD", "effective_to": null }`

---

### E) Leaves

| Method | Path | Permission |
|--------|------|------------|
| GET | `/leaves` | `hr.leaves.view` |
| POST | `/leaves` | `hr.leaves.create` |
| GET | `/leaves/{id}` | `hr.leaves.view` |
| PATCH | `/leaves/{id}/approve` | `hr.leaves.approve` |
| PATCH | `/leaves/{id}/reject` | `hr.leaves.reject` |
| PATCH | `/leaves/{id}/cancel` | `hr.leaves.cancel` |
| GET | `/leaves/balances` | `hr.leaves.view` |
| GET | `/leaves/pending` | `hr.leaves.view` |

**GET `/leaves` filters:** `status`, `employee_id`, `leave_type_id`, `date_from`, `date_to`, `page`, `per_page`.

---

### F) Payroll

| Method | Path | Permission |
|--------|------|------------|
| GET | `/payroll` | `hr.payroll.view` |
| POST | `/payroll/generate` | `hr.payroll.generate` |
| GET | `/payroll/{id}` | `hr.payroll.view` |
| PATCH | `/payroll/{id}/approve` | `hr.payroll.approve` |
| PATCH | `/payroll/{id}/mark-paid` | `hr.payroll.pay` |
| PATCH | `/payroll/{id}/cancel` | `hr.payroll.cancel` |
| GET | `/payroll/{id}/items` | `hr.payroll.view` |
| GET | `/payroll/{id}/items/{itemId}/payslip` | `hr.payroll.view` |
| GET | `/payroll/export` | `hr.payroll.export` |

**GET `/payroll` filters:** `month`, `year`, `branch_id`, `status`, `page`, `per_page`.

**POST generate body:** `{ "month": 6, "year": 2026, "branch_id": null, "recalculate": false }`

---

### G) Advances

| Method | Path | Permission |
|--------|------|------------|
| GET | `/advances` | `hr.advances.view` |
| POST | `/advances` | `hr.advances.create` |
| GET | `/advances/{id}` | `hr.advances.view` |
| PUT | `/advances/{id}` | `hr.advances.create` |
| PATCH | `/advances/{id}/approve` | `hr.advances.approve` |
| PATCH | `/advances/{id}/cancel` | `hr.advances.cancel` |

---

### H) Deductions

| Method | Path | Permission |
|--------|------|------------|
| GET | `/deductions` | `hr.deductions.view` |
| POST | `/deductions` | `hr.deductions.create` |
| GET | `/deductions/{id}` | `hr.deductions.view` |
| PUT | `/deductions/{id}` | `hr.deductions.create` |
| PATCH | `/deductions/{id}/approve` | `hr.deductions.approve` |
| PATCH | `/deductions/{id}/cancel` | `hr.deductions.cancel` |

---

### I) Bonuses

| Method | Path | Permission |
|--------|------|------------|
| GET | `/bonuses` | `hr.bonuses.view` |
| POST | `/bonuses` | `hr.bonuses.create` |
| GET | `/bonuses/{id}` | `hr.bonuses.view` |
| PUT | `/bonuses/{id}` | `hr.bonuses.create` |
| PATCH | `/bonuses/{id}/approve` | `hr.bonuses.approve` |
| PATCH | `/bonuses/{id}/cancel` | `hr.bonuses.cancel` |

---

### J) Commissions

| Method | Path | Permission |
|--------|------|------------|
| GET | `/commissions` | `hr.commissions.view` |
| POST | `/commissions` | `hr.commissions.create` |
| GET | `/commissions/{id}` | `hr.commissions.view` |
| PUT | `/commissions/{id}` | `hr.commissions.create` |
| PATCH | `/commissions/{id}/approve` | `hr.commissions.approve` |
| PATCH | `/commissions/{id}/cancel` | `hr.commissions.cancel` |

---

### K) Documents

| Method | Path | Permission |
|--------|------|------------|
| GET | `/documents` | `hr.documents.view` |
| POST | `/documents` | `hr.documents.upload` |
| GET | `/documents/{id}` | `hr.documents.view` |
| DELETE | `/documents/{id}` | `hr.documents.delete` |
| GET | `/documents/expiry-alerts` | `hr.documents.view` |

**POST:** multipart `file`, `employee_id`, `document_type`, `issue_date`, `expiry_date`, `notes`.

---

### L) Reports

All support: `date_from`, `date_to`, `branch_id`, `department_id`, `employee_id`, `export=csv|xlsx|pdf`.

| Method | Path | Permission |
|--------|------|------------|
| GET | `/reports/employees` | `hr.reports.view` |
| GET | `/reports/attendance` | `hr.reports.view` |
| GET | `/reports/late-absence` | `hr.reports.view` |
| GET | `/reports/payroll` | `hr.reports.view` |
| GET | `/reports/advances` | `hr.reports.view` |
| GET | `/reports/deductions` | `hr.reports.view` |
| GET | `/reports/leaves` | `hr.reports.view` |
| GET | `/reports/turnover` | `hr.reports.view` |

Export additionally requires `hr.reports.export`.

---

### M) Settings / Lookups

| Method | Path | Permission |
|--------|------|------------|
| GET | `/settings` | `hr.settings.view` |
| PUT | `/settings` | `hr.settings.update` |
| GET/POST/PUT/DELETE | `/departments`, `/departments/{id}` | `hr.settings.view` / `hr.settings.update` |
| GET/POST/PUT/DELETE | `/job-titles`, `/job-titles/{id}` | `hr.settings.view` / `hr.settings.update` |
| GET/POST/PUT/DELETE | `/leave-types`, `/leave-types/{id}` | `hr.settings.view` / `hr.settings.update` |

**PUT `/settings` body:** partial JSON keys matching `hr_settings` keys.

---

## 4. Request/Response Examples

### Create employee

**Request** `POST /api/tenant/hr/employees`

```json
{
  "employee_code": "EMP-010",
  "full_name": "ريم العتيبي",
  "phone": "+966501234567",
  "email": "reem@example.test",
  "national_id": "1023456789",
  "branch_id": 1,
  "department_id": 2,
  "job_title_id": 5,
  "employment_type": "full_time",
  "status": "active",
  "joining_date": "2024-06-01",
  "base_salary": 6500,
  "salary_type": "monthly",
  "working_hours_per_day": 8,
  "emergency_contact_name": "سارة",
  "emergency_contact_phone": "+966509876543"
}
```

**Response** `201`

```json
{
  "success": true,
  "message": "Employee created",
  "data": {
    "id": 10,
    "employee_code": "EMP-010",
    "full_name": "ريم العتيبي",
    "status": "active",
    "branch": { "id": 1, "name": "الفرع الرئيسي" },
    "department": { "id": 2, "name": "المبيعات" },
    "job_title": { "id": 5, "title": "موظفة مبيعات" },
    "base_salary": 6500,
    "salary_type": "monthly",
    "joining_date": "2024-06-01"
  },
  "meta": {}
}
```

### Employee list (paginated)

**Request** `GET /api/tenant/hr/employees?search=ريم&branch_id=1&status=active&page=1&per_page=15`

**Response** `200`

```json
{
  "success": true,
  "message": "Success",
  "data": [
    {
      "id": 2,
      "employee_code": "EMP-002",
      "full_name": "ريم العتيبي",
      "phone": "+966502223344",
      "branch_name": "الفرع الرئيسي",
      "department_name": "المبيعات",
      "job_title": "موظفة مبيعات",
      "employment_type": "full_time",
      "base_salary": 6500,
      "status": "active",
      "joining_date": "2022-06-15",
      "avatar_url": null
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 1,
    "last_page": 1
  }
}
```

### Employee details

**Request** `GET /api/tenant/hr/employees/2`

**Response `data`:** full employee resource + nested `summary` block:

```json
{
  "id": 2,
  "employee_code": "EMP-002",
  "full_name": "ريم العتيبي",
  "employment_type": "full_time",
  "status": "active",
  "base_salary": 6500,
  "salary_type": "monthly",
  "summary": {
    "present_days_this_month": 18,
    "late_days_this_month": 2,
    "approved_leaves_this_month": 1,
    "net_salary_estimate": 6200,
    "leave_balances": [
      { "leave_type_code": "annual", "remaining": 16 }
    ]
  }
}
```

### Create attendance

**Request** `POST /api/tenant/hr/attendance`

```json
{
  "employee_id": 2,
  "branch_id": 1,
  "shift_id": 1,
  "date": "2026-06-02",
  "check_in": "09:12:00",
  "check_out": "17:05:00",
  "status": "late",
  "notes": "ازدحام مرور"
}
```

**Response** `201` with computed `late_minutes`, `overtime_minutes`.

### Attendance summary

**Request** `GET /api/tenant/hr/attendance/summary?date=2026-06-02&branch_id=1`

```json
{
  "success": true,
  "message": "Success",
  "data": {
    "date": "2026-06-02",
    "present": 12,
    "absent": 1,
    "late": 3,
    "leave": 2,
    "day_off": 1
  },
  "meta": {}
}
```

### Create leave request

**Request** `POST /api/tenant/hr/leaves`

```json
{
  "employee_id": 2,
  "leave_type_id": 1,
  "from_date": "2026-06-10",
  "to_date": "2026-06-14",
  "reason": "إجازة عائلية"
}
```

### Approve leave

**Request** `PATCH /api/tenant/hr/leaves/15/approve`

```json
{
  "manager_note": "معتمد — تغطية من موظفة أخرى"
}
```

### Generate payroll

**Request** `POST /api/tenant/hr/payroll/generate`

```json
{
  "month": 6,
  "year": 2026,
  "branch_id": null
}
```

**Response** `201`

```json
{
  "success": true,
  "message": "Payroll run generated",
  "data": {
    "id": 4,
    "month": 6,
    "year": 2026,
    "status": "draft",
    "gross_salaries": 52000,
    "total_advances": 2500,
    "total_deductions": 850,
    "total_bonuses": 3000,
    "total_commissions": 1730,
    "net_payroll": 52380,
    "items_count": 9
  },
  "meta": {}
}
```

### Payslip item

**Request** `GET /api/tenant/hr/payroll/4/items/22/payslip`

```json
{
  "success": true,
  "message": "Success",
  "data": {
    "employee": { "id": 2, "employee_code": "EMP-002", "full_name": "ريم العتيبي" },
    "period": { "month": 6, "year": 2026 },
    "base_salary": 6500,
    "attendance_days": 22,
    "absent_days": 0,
    "overtime_minutes": 120,
    "overtime_amount": 350,
    "advances_amount": 500,
    "deductions_amount": 200,
    "bonuses_amount": 1000,
    "commissions_amount": 730,
    "gross_salary": 7880,
    "net_salary": 7180,
    "status": "draft"
  },
  "meta": {}
}
```

### Create advance

**Request** `POST /api/tenant/hr/advances`

```json
{
  "employee_id": 2,
  "amount": 3000,
  "date": "2026-06-01",
  "repayment_type": "installments",
  "installments_count": 3,
  "notes": "سلفة شخصية"
}
```

### Create deduction

**Request** `POST /api/tenant/hr/deductions`

```json
{
  "employee_id": 8,
  "type": "late",
  "amount": 150,
  "date": "2026-05-28",
  "reason": "تأخير متكرر"
}
```

### Upload document

**Request** `POST /api/tenant/hr/documents` (multipart)

Fields: `employee_id=2`, `document_type=contract`, `file=@contract.pdf`, `issue_date=2024-03-01`, `expiry_date=2026-08-01`

**Response** `201` includes `file_url` (signed/public URL per tenant storage policy).

### HR dashboard

**Request** `GET /api/tenant/hr/dashboard`

See §3.A structure; aligns with frontend mock `HrDashboardData`.

---

## 5. Validation Rules

### Employees

| Field | Rules |
|-------|-------|
| employee_code | required, string, max:50, unique:hr_employees |
| full_name | required, string, max:190 |
| email | nullable, email, max:190 |
| phone | nullable, string, max:30 |
| national_id | nullable, string, max:50, unique when present |
| branch_id | nullable, exists:tenant.branches,id |
| department_id | nullable, exists:tenant.hr_departments,id |
| job_title_id | nullable, exists:tenant.hr_job_titles,id |
| employment_type | required, in:full_time,part_time,contractor,temporary |
| status | required, in:active,inactive,suspended,terminated |
| joining_date | required, date |
| leaving_date | nullable, date, after_or_equal:joining_date; required if terminated |
| base_salary | required, numeric, min:0 |
| salary_type | required, in:monthly,daily,hourly |
| working_hours_per_day | nullable, numeric, min:0, max:24 |

### Attendance

| Field | Rules |
|-------|-------|
| employee_id | required, exists:hr_employees,id |
| date | required, date |
| status | required, in enum |
| check_in/check_out | nullable, date_format:H:i:s; check_out after check_in when both set |

**Business:** reject if employee `status` is `terminated` or `inactive`. One row per employee per date (update on conflict).

### Shifts

| Field | Rules |
|-------|-------|
| name | required, max:120 |
| start_time, end_time | required, time; end after start (or overnight flag in settings) |
| working_days | required, array, each in sun…sat |
| break_minutes, grace_minutes | integer, min:0 |

### Leaves

| Field | Rules |
|-------|-------|
| leave_type_id | required, exists |
| from_date, to_date | required; from ≤ to |
| days_count | auto-calculated excluding holidays per settings |

**Business:** cannot approve for inactive/terminated employee; cannot approve if remaining balance insufficient (paid types).

### Payroll generation

| Field | Rules |
|-------|-------|
| month | required, 1–12 |
| year | required, 2000–2100 |
| branch_id | nullable, exists |

**Business:**

- No second active run for same `(year, month, branch_id)` unless prior run is `cancelled`
- Paid runs are immutable
- Only `approved` items included from advances/deductions/bonuses/commissions unless configured otherwise

### Advances / deductions / bonuses / commissions

- `amount` > 0
- `employee_id` must be active or suspended (not terminated)
- Status transitions enforced (pending → approved → applied)

### Documents

- `file` required on upload; max size (e.g. 5MB); mime: pdf,jpg,png,webp
- `expiry_date` ≥ `issue_date` when both provided

### Settings

- JSON schema validation per key (document in FormRequest classes during implementation)

---

## 6. Permissions Plan

Add to `TenantRolePermissionSeeder` under module **HR**. Suggested default role mapping:

| Role | HR access |
|------|-----------|
| Owner / Admin | All `hr.*` |
| Branch manager | view/create/update employees, attendance, leaves approve, view payroll |
| Accountant | payroll.*, advances/deductions/bonuses view+approve, reports |
| Staff | `hr.leaves.create` (self-service future), `hr.documents.view` (self) |

### Permission keys

```
hr.view                          # umbrella (optional gate)

hr.dashboard.view

hr.employees.view
hr.employees.create
hr.employees.update
hr.employees.delete
hr.employees.status

hr.attendance.view
hr.attendance.create
hr.attendance.update
hr.attendance.bulk

hr.shifts.view
hr.shifts.create
hr.shifts.update
hr.shifts.delete
hr.shifts.assign

hr.leaves.view
hr.leaves.create
hr.leaves.approve
hr.leaves.reject
hr.leaves.cancel

hr.payroll.view
hr.payroll.generate
hr.payroll.approve
hr.payroll.pay
hr.payroll.cancel
hr.payroll.export

hr.advances.view
hr.advances.create
hr.advances.approve
hr.advances.cancel

hr.deductions.view
hr.deductions.create
hr.deductions.approve
hr.deductions.cancel

hr.bonuses.view
hr.bonuses.create
hr.bonuses.approve
hr.bonuses.cancel

hr.commissions.view
hr.commissions.create
hr.commissions.approve
hr.commissions.cancel

hr.documents.view
hr.documents.upload
hr.documents.delete

hr.reports.view
hr.reports.export

hr.settings.view
hr.settings.update
```

Route middleware pattern: `tenant.permission:hr.employees.view` (consistent with existing modules).

---

## 7. Sidebar Permission Mapping (Frontend)

| Sidebar item | Route | Minimum permission |
|--------------|-------|-------------------|
| HR Dashboard | `/hr` | `hr.dashboard.view` |
| Employees | `/hr/employees` | `hr.employees.view` |
| Attendance | `/hr/attendance` | `hr.attendance.view` |
| Shifts | `/hr/shifts` | `hr.shifts.view` |
| Leaves | `/hr/leaves` | `hr.leaves.view` |
| Payroll | `/hr/payroll` | `hr.payroll.view` |
| Advances & Deductions | `/hr/advances-deductions` | `hr.advances.view` **OR** `hr.deductions.view` |
| Bonuses & Commissions | `/hr/bonuses-commissions` | `hr.bonuses.view` **OR** `hr.commissions.view` |
| Documents | `/hr/documents` | `hr.documents.view` |
| HR Reports | `/hr/reports` | `hr.reports.view` |
| HR Settings | `/hr/settings` | `hr.settings.view` |

Parent **الموارد البشرية** visible if user has **`hr.view`** OR any child permission.

---

## 8. Payroll Calculation Logic

Payroll rules are **tenant-configurable** via `hr_settings.payroll_rules` JSON. Below is the **default proposed algorithm** for implementation reference.

### Configuration keys (example)

```json
{
  "working_days_in_month": "calendar_exclude_friday",
  "late_deduction_per_minute": 0,
  "overtime_rate_multiplier": 1.5,
  "unpaid_leave_deducts_daily_rate": true,
  "absence_deducts_daily_rate": true,
  "include_approved_commissions": true,
  "include_approved_bonuses": true,
  "advance_recovery_mode": "approved_unapplied"
}
```

### Rate derivation

**Monthly salary:**

```
daily_rate = base_salary / working_days_in_month
hourly_rate = daily_rate / working_hours_per_day
```

**Daily salary:**

```
daily_rate = base_salary
hourly_rate = daily_rate / working_hours_per_day
```

**Hourly salary:**

```
hourly_rate = base_salary
daily_rate = hourly_rate * working_hours_per_day
```

### Inputs (per employee, per period)

| Input | Source |
|-------|--------|
| base_salary | `hr_employees` |
| attendance_days | `hr_attendance` (present, late, half_day weighted) |
| absent_days | `hr_attendance` status absent |
| unpaid_leave_days | approved leave where type.paid = false |
| late_minutes | sum from attendance |
| overtime_minutes | sum from attendance |
| approved advances | `hr_advances` status approved/partially_paid, remaining > 0 |
| approved deductions | `hr_deductions` status approved, not yet applied |
| approved bonuses | `hr_bonuses` status approved |
| approved commissions | `hr_commissions` status approved |

### Calculations

```
overtime_amount = (overtime_minutes / 60) * hourly_rate * overtime_rate_multiplier

late_deduction = late_minutes * late_deduction_per_minute

absence_deduction = absent_days * daily_rate
unpaid_leave_deduction = unpaid_leave_days * daily_rate

gross_salary = base_salary + overtime_amount + bonuses + commissions

total_deductions = late_deduction + absence_deduction + unpaid_leave_deduction
                 + advance_recovery + penalty_deductions

net_salary = gross_salary - total_deductions
```

For **daily/hourly** employees, replace `base_salary` proration with `attendance_days * daily_rate` or hours worked.

### Lifecycle

1. **Generate** → creates `hr_payroll_runs` + `hr_payroll_items` (status `draft`)
2. **Review** → `pending_review`
3. **Approve** → locks calculations; marks linked advances/deductions/bonuses/commissions as `applied`
4. **Mark paid** → status `paid`; immutable; triggers future cashbox hook (phase 6)
5. **Cancel** → only from draft/pending_review; releases applied flags

---

## 9. Integration With Existing Modules

| Integration | Description | Phase |
|-------------|-------------|-------|
| **Branches** | `hr_employees.branch_id`, filters, payroll by branch | 1 |
| **Invoices / Sales / Rental / Tailoring** | Commission `source_type` + `source_id` references tenant invoices | 3 |
| **Reports module** | Reuse `ReportExporter` patterns (CSV/XLSX/PDF) for HR reports | 5 |
| **Tenant storage** | Employee avatars & documents under `tenants/{tenant_id}/hr/...` (mirror user avatar pattern) | 1 metadata, 6 upload |
| **Accounting / cashboxes** | Payroll `mark-paid` posts expense/journal entry | 6 |
| **Notifications** | Leave approval, document expiry, payroll approved | 6 |
| **Lookups API** | Optional expose HR lookups via `/api/tenant/lookups` extension | 2 |
| **Plan features** | `hr.enabled` in plan catalog (like `reports.enabled`) | Before production |

**No integration is implemented in this planning phase.**

---

## 10. API Implementation Phases

### Phase 1 — Foundation
- Migrations: departments, job titles, employees, documents (metadata), notes, settings
- Permissions seeder + routes skeleton
- Employees CRUD + status + summary
- Settings/lookups CRUD
- Feature tests: tenant isolation, permissions, employee uniqueness

### Phase 2 — Time & leave
- Shifts, employee_shifts, attendance (+ bulk, summary)
- Leave types, balances, requests, approve/reject/cancel
- Employee sub-resources (attendance, leaves)
- Dashboard v1 (attendance KPIs)

### Phase 3 — Compensation entries
- Advances, deductions, bonuses, commissions
- Approval workflows; link amounts to employee profile tabs
- Commission manual entry; stub for invoice linkage

### Phase 4 — Payroll
- Payroll runs generate/recalculate
- Items + payslip endpoint
- Approve / mark paid / cancel rules
- Dashboard payroll summary

### Phase 5 — Reports & export
- HR report endpoints + export
- Align with frontend report cards
- Performance indexes review

### Phase 6 — Hardening & integrations
- Real file upload (documents, avatars)
- Cashbox/accounting on payroll pay
- Notifications (expiry, leave)
- Optional self-service employee portal
- Deprecate or bridge legacy `/api/tenant/employees` if needed

---

## 11. Testing Plan

All tests in `tests/Feature/Tenant/` using sqlite tenant DB pattern (PHP 8.3+).

| Test class | Coverage |
|------------|----------|
| `TenantHrIsolationTest` | Tenant A cannot read/update Tenant B HR records |
| `TenantHrPermissionsTest` | Each route denies 403 without permission |
| `TenantHrEmployeeTest` | CRUD, unique code/national_id, soft delete, status transitions |
| `TenantHrAttendanceTest` | Create/update, unique per day, terminated employee blocked |
| `TenantHrLeaveTest` | Request, approve, reject, balance decrement, date validation |
| `TenantHrPayrollTest` | Generate once per month, cancel allows regenerate, paid immutable |
| `TenantHrAdvanceTest` | Remaining amount never negative; approve workflow |
| `TenantHrDocumentTest` | Metadata CRUD; expiry status computation |
| `TenantHrReportTest` | Filters, pagination, export content-type |
| `TenantHrDashboardTest` | KPI shape matches contract |

Use factories for `HrEmployee`, `HrAttendance`, etc. Seed default leave types in test setup.

---

## 12. Final Readiness Matrix

| Area | Required | Frontend mock exists? | Backend contract ready? | Priority | Risk | Notes |
|------|----------|----------------------|---------------------------|----------|------|-------|
| HR Dashboard | Yes | Yes | Yes (this doc) | P2 | Low | After attendance + payroll summaries |
| Employees CRUD | Yes | Yes | Yes | **P1** | Medium | Core dependency for all modules |
| Departments / job titles | Yes | Yes (settings) | Yes | **P1** | Low | Lookup tables first |
| Shifts | Yes | Yes | Yes | P2 | Medium | Assignment overlap rules TBD |
| Attendance | Yes | Yes | Yes | P2 | Medium | Timezone: store local date + times |
| Leaves | Yes | Yes | Yes | P2 | Medium | Balance accrual policy TBD |
| Advances | Yes | Yes | Yes | P3 | Medium | Recovery in payroll |
| Deductions | Yes | Yes | Yes | P3 | Low | |
| Bonuses | Yes | Yes | Yes | P3 | Low | |
| Commissions | Yes | Yes | Yes | P3 | High | Invoice linkage needs clear FK strategy |
| Payroll | Yes | Yes | Yes | **P4** | **High** | Calc rules + immutability |
| Documents | Yes | Yes | Yes | P1 metadata / P6 files | Medium | Upload deferred |
| Reports | Yes | Yes | Yes | P5 | Low | Reuse ReportExporter |
| Settings | Yes | Yes | Yes | P1 | Low | JSON schema validation needed |
| Permissions | Yes | Partial (no keys yet) | Yes | **P1** | Low | Add to seeder before routes |
| Plan feature gate | Optional | No | Open | P5 | Low | `hr.enabled` |
| Legacy `/employees` | No | Yes (old module) | N/A | Defer | Low | Keep separate from `/hr` |

---

## Open Decisions (before implementation)

1. **Plan feature key:** Add `hr.enabled` to plan catalog or ship HR with base plan?
2. **Branch FK on delete:** Restrict vs nullify when branch deleted?
3. **Payroll uniqueness:** Partial unique index vs application-level lock for `(year, month, branch_id)`.
4. **Commission linkage:** Single `source_id` + `source_type` vs polymorphic `morphs`.
5. **Self-service:** Can employees submit own leave requests without `hr.leaves.create` on admin only?
6. **Legacy module:** Migrate `/api/tenant/employees` to HR or maintain parallel indefinitely?
7. **Overtime overnight shifts:** How to compute when `end_time < start_time`.
8. **Currency:** Assume single currency per tenant (SAR) — confirm no multi-currency HR.

---

## Assumptions

- HR data lives only in **tenant DB**; no central HR tables except optional cross-tenant analytics later.
- Authentication/tenancy remain as documented in `docs/tenant-isolation.md` (email login + post-login `X-Tenant`).
- API envelopes match `ApiResponse` (`meta` as object when empty).
- Frontend mock types in `Front-DressnMore/src/features/hr/types/hr.types.ts` inform field naming but backend may use snake_case consistently.
- Existing `users` table represents **system login users**, not HR employees; HR employees are separate records (may link later via optional `user_id` — **not in v1** unless product requests).
- Soft delete only on `hr_employees`; financial records are never hard-deleted (cancel/status instead).

---

*End of HR API contract plan — documentation only, no runtime changes.*
