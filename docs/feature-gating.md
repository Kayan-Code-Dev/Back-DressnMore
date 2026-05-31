# Feature Gating

## Status

- `CheckPlanFeature` middleware (`plan.feature:<key>`) is applied on tenant API route groups.
- Dress categories use `plan.dress_category` middleware (`categories.enabled` / `subcategories.enabled`).
- Tenant `/me` and login return `subscription.enabled_modules` for frontend UI gating.

## Feature keys

See `App\Support\PlanFeatureCatalog` for the canonical list (e.g. `customers.enabled`, `categories.enabled`, `reports.enabled`).

## Error contract

Disabled features return `403` with message `Feature is not available`.
