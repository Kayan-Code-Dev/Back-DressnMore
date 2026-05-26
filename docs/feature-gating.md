# Feature Gating Plan (Deferred Modules)

## Current status

- `CheckPlanFeature` middleware is implemented and registered as `plan.feature`.
- It is intentionally **not applied** to currently completed modules (core tenancy, customers, dresses, invoices, delivery/return, expenses/cash movements).
- This avoids accidental behavior changes before product-level plan rules are finalized.

## Recommended future usage

Apply `plan.feature:<feature_key>` when the corresponding module/limit is implemented:

- `accounting.enabled`
  - Future accounting ledger and journal endpoints.
- `suppliers.enabled`
  - Future supplier CRUD and purchase flows.
- `payroll.enabled`
  - Future employee payroll endpoints.
- `advanced_reports.enabled`
  - Future analytics and reporting endpoints.
- `branches.max`
  - Branch creation/update flows; enforce numeric max branches per tenant.
- `employees.max`
  - Employee creation/update flows; enforce numeric max employees per tenant.
- `invoices.monthly_limit`
  - Invoice creation flow; enforce per-month cap.

## Rollout guidance

1. Add required plan features to central `plan_features`.
2. Add module-level tests for feature enabled/disabled and limit boundaries.
3. Apply middleware only on relevant endpoints, not globally.
4. Keep a fallback error contract: `403 Feature is not available`.
