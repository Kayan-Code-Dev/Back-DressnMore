# Lookups Contract

Source endpoint: `GET /api/tenant/lookups`

Auth:
- required (`auth:sanctum`)
- tenant headers required (`X-Tenant`)

Lookup item shape:

```json
{
  "value": "string",
  "label": "string"
}
```

## Lookup groups

| Key | Values | Used by frontend screens | Notes |
|---|---|---|---|
| `customer_statuses` | `active`, `inactive` | Customers forms/filters | Core customer state |
| `customer_sources` | `walk_in`, `instagram`, `facebook`, `referral`, `other` | Customer create/edit and source filter | UI contract-added group |
| `dress_statuses` | `available`, `rented`, `sold`, `maintenance`, `unavailable` | Dresses list/forms, availability indicators | Shared with inventory/delivery flows |
| `category_statuses` | `active`, `inactive` | Dress categories/subcategories CRUD | Category status lookup |
| `invoice_types` | `rent`, `sell`, `tailoring` | Invoice create/filter tabs | Single invoice module supports all three |
| `invoice_statuses` | `draft`, `confirmed`, `partially_paid`, `paid`, `delivered`, `returned`, `cancelled` | Invoice list/status badges/workflow checks | Shared by delivery/return UI |
| `payment_methods` | `cash`, `instapay`, `vodafone_cash`, `bank_transfer` | Invoice/supplier payments, expenses, cash movements | Canonical payment method source |
| `payment_statuses` | `pending`, `paid`, `cancelled` | Standalone payments list/actions | UI contract-added group |
| `payment_types` | `invoice_payment`, `supplier_payment`, `security_deposit_deduction`, `manual_adjustment` | Standalone payments filtering | UI contract-added group |
| `security_deposit_statuses` | `none`, `partially_deducted`, `fully_deducted`, `refunded` | Invoice deposit status display | Used in rent invoice lifecycle |
| `inventory_movement_types` | `created`, `status_changed`, `maintenance`, `sold`, `rented`, `returned`, `manual_adjustment`, `branch_transfer` | Inventory movement history screen | Foundation inventory module |
| `delivery_record_types` | `delivered`, `returned`, `cancelled_delivery` | Delivery records timeline | Delivery/return module |
| `security_deposit_transaction_types` | `collected`, `deducted`, `refunded` | Security deposit transaction list | Deposit transaction type control |
| `expense_statuses` | `pending`, `approved`, `paid`, `cancelled` | Expense workflow forms/lists | Workflow status set |
| `expense_category_statuses` | `active`, `inactive` | Expense category management | Category status set |
| `branch_statuses` | `active`, `inactive` | Branch forms/list filters | UI contract-added group |
| `vat_types` | `fixed`, `percentage` | Branch VAT setup | UI contract-added group |
| `cashbox_statuses` | `active`, `inactive` | Cashbox forms/list filters | UI contract-added group |
| `supplier_statuses` | `active`, `inactive` | Suppliers forms/list filters | Supplier module |
| `purchase_order_statuses` | `draft`, `confirmed`, `partially_paid`, `paid`, `cancelled` | Purchase order lists/status workflow | Purchase orders module |
| `report_periods` | `daily`, `weekly`, `monthly`, `yearly`, `custom` | Future dashboard/report filter widgets | Added for contract alignment; reports module still deferred |
| `cash_movement_types` | `income`, `expense`, `invoice_payment`, `security_deposit_deduction`, `manual_adjustment`, `supplier_payment` | Cash movement filters and analytics cards | Includes supplier payment integration |
| `cash_movement_directions` | `in`, `out` | Cash movement filters/forms | Directional flow |
| `dress_status_after_return` | `available`, `maintenance` | Return invoice action form | Restricted post-return target statuses |

## Notes

- Lookups are tenant-auth protected and should be cached by frontend after login/workspace selection.
- Frontend should avoid hardcoding enum values when a lookup group exists.
- `report_periods` is available for contract readiness only; report APIs remain deferred.
