# EDUJA Finance Foundations Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build school-scoped finance foundations with approved-only ledger/report data and auditable period closing.

**Architecture:** Use existing finance tables as the compatibility base, add only missing finance records/metadata, and route all new behavior through focused services. Existing approval routes continue to transition generic approval requests; finance services provide the posting and read-side invariants.

**Tech Stack:** Laravel 12, PHP 8.2, Eloquent, SQLite/MySQL migrations, Pest feature tests, Blade.

**Spec:** `docs/superpowers/specs/2026-09-11-finance-foundations-design.md`

## Global Constraints

- Enforce active school scope on every finance read and write.
- Include only `approved` records in ledger and report totals.
- Preserve existing expense approval behavior and permission middleware.
- Do not modify `routes/web.php`, `app/Helpers/MenuHelper.php`, auth files, academic files, or unrelated behavior.
- Do not invent provider credentials, external integrations, or fake transactions.
- Write Pest tests first and observe the expected RED failure before production code.

### Task 1: Finance behavior tests

**Files:**
- Create: `tests/Feature/FinanceFoundationsTest.php`

- [ ] **Step 1: Write failing tests** for account/master/allocation writes, BOS income and expense state, budget revisions, approved-only ledger filters, closing validation/snapshot metadata, and cross-school isolation.
- [ ] **Step 2: Run the focused file** with `php artisan test tests/Feature/FinanceFoundationsTest.php` and confirm it fails because the new finance service/tables are absent.

### Task 2: Finance schema and models

**Files:**
- Create: `database/migrations/2026_09_11_000002_add_finance_foundation_fields.php`
- Create: `app/Models/FinanceIncome.php`
- Create: `app/Models/FundAllocation.php`
- Create: `app/Models/BudgetPlanRevision.php`
- Modify: `app/Models/SchoolAccount.php`
- Modify: `app/Models/IncomeType.php`
- Modify: `app/Models/ExpenseType.php`
- Modify: `app/Models/BudgetPlan.php`
- Modify: `app/Models/PaymentSubmission.php`
- Modify: `app/Models/Transaction.php`
- Modify: `app/Models/BookClosing.php`

- [ ] **Step 1:** Add the new finance income, fund allocation, and budget revision tables; add account/status/linkage and closing validation fields to existing tables with indexes and school foreign keys.
- [ ] **Step 2:** Add casts, fillable/guarded boundaries, and relationships needed by the services.

### Task 3: Finance write and closing services

**Files:**
- Create: `app/Services/FinanceService.php`
- Create: `app/Services/FinanceClosingService.php`

- [ ] **Step 1:** Implement school-validated account/master/allocation/BOS income/expense/budget/revision writes and approval request creation.
- [ ] **Step 2:** Implement idempotent approval posting for approved payment/expense/income records and budget revision status.
- [ ] **Step 3:** Implement closing validation, snapshot balances, and audit metadata inside a transaction.

### Task 4: Ledger/report integration

**Files:**
- Modify: `app/Services/FinanceLedgerService.php`
- Modify: `app/Http/Controllers/FinanceFoundationController.php`
- Modify: `app/Http/Controllers/BosController.php`
- Modify: `app/Http/Controllers/ApprovalController.php`

- [ ] **Step 1:** Make ledger entries and summaries school-scoped and approved-only, with date/source/account filters.
- [ ] **Step 2:** Make existing finance foundation pages consume scoped service queries and closing data.
- [ ] **Step 3:** Make BKU use the service while retaining its existing view payload and tax sub-ledger behavior.
- [ ] **Step 4:** Extend existing approval transition only for finance posting side effects, preserving existing role checks and generic status updates.

### Task 5: Verification and commit

**Files:**
- No new files.

- [ ] **Step 1:** Run the focused finance tests and existing expense approval tests.
- [ ] **Step 2:** Run the full Pest suite and record unrelated failures, if any.
- [ ] **Step 3:** Inspect `git diff --check`, forbidden-file changes, and the final diff.
- [ ] **Step 4:** Commit the verified implementation with `git add` and `git commit`.
