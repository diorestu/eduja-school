# EDUJA Finance Foundations Design

## Goal

Implement the school-scoped finance foundation from the finance brief pages 5-7 and 14-37 while preserving the existing expense approval flow and permission middleware.

## Scope

- Reuse `school_accounts`, `income_types`, `expense_types`, `budget_years`, `budget_plans`, `payment_submissions`, `approval_requests`, `expenses`, `transactions`, and `book_closings`.
- Add a typed finance income record, fund allocation records, budget revision history, account linkage, approval metadata, and closing validation/audit metadata.
- Centralize finance writes and approved-only ledger/report reads in services.
- Keep existing route definitions and permission middleware unchanged. Existing finance and BOS routes will consume the new service behavior where already routed.
- Do not create provider integrations, WhatsApp credentials, or synthetic transactions.

## Data and invariants

- Every finance record created by the new services receives the active school ID explicitly; cross-school foreign keys are rejected.
- `approved` is the only status included in ledger/report totals. Existing `transactions` remain compatible by defaulting their new status to `approved`.
- Expenses continue to be created as `pending` with a generic `approval_requests` row and are only posted to an account when approved.
- Payment submissions remain pending until their existing approval endpoint transitions them; approved submissions become ledger income.
- Budget plans and revisions are pending until the existing approval endpoint transitions them; only approved plans are active for budget realization.
- Closing refuses periods with pending approvals, pending payment/expense records, negative account balances, or unbalanced BOS totals, and persists the validation result plus actor/time metadata.

## Components

- `FinanceService`: school-scoped account/master/allocation/BOS income/expense/budget/revision writes and approval posting helpers.
- `FinanceLedgerService`: approved-only ledger entries, date/source/account filters, and dashboard/report aggregates.
- `FinanceClosingService`: closing checks, immutable snapshot creation, and audit metadata.
- Existing finance controllers: use active-school queries and service results without changing route declarations or permission middleware.

## Testing

Focused Pest feature tests cover school isolation, account/master creation, BOS income/expense approval behavior, budget approval/revision, approved-only ledger filters, closing rejection/snapshot metadata, and existing expense approval regression. The first run must fail before production implementation is added.
