# Invoice Lifecycle

This document is the backend source of truth for tenant invoice state transitions.

## Public API creation/update rules

Generic invoice create/update endpoints may only start or keep invoices in safe operator states:

- `draft`
- `confirmed`
- `cancelled` only from an existing `draft` or `confirmed` invoice through allowed transitions

The generic invoice update endpoint must not be used to set financial or fulfillment states directly.

## System-owned states

These states are owned by dedicated backend workflows:

| State | Owner workflow |
| --- | --- |
| `partially_paid` | Invoice payment posting |
| `paid` | Invoice payment posting |
| `delivered` | Invoice delivery workflow |
| `returned` | Invoice return workflow |

## Allowed generic transitions

| Current | Allowed next states |
| --- | --- |
| `draft` | `draft`, `confirmed`, `cancelled` |
| `confirmed` | `confirmed`, `cancelled` |
| `partially_paid` | `partially_paid` only |
| `paid` | `paid` only |
| `delivered` | `delivered` only |
| `returned` | `returned` only |
| `cancelled` | `cancelled` only |

## Deletion rules

Only `draft` invoices can be deleted. Any invoice with payments, delivery records, or security-deposit activity is immutable through delete and must use the appropriate reversal/cancellation workflow.

## Rental availability

Confirmed rental invoices lock their dress rows and check overlapping rental periods inside the tenant database transaction. This protects the same dress from being booked in overlapping date ranges under normal database row-locking semantics.
