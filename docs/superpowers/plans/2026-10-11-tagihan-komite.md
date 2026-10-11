# Tagihan Komite Implementation Plan

> **For agentic workers:** Use superpowers:executing-plans in this session. User explicitly requested implementation after approving the design.

**Goal:** Generate school-scoped committee invoices by period, preserve receivables, enforce payment rules, and prepare truthful parent reminders.
**Architecture:** BillingService owns targets and atomic generation; BillingPaymentService owns invoice snapshots and balances; BillingReminderService owns persistent deduplicated reminders and configured delivery.
**Tech Stack:** Laravel 12, Blade/Alpine, SQLite-compatible migrations, Pest.
**Spec:** docs/superpowers/specs/2026-10-11-tagihan-komite-design.md

## Global Constraints

- Reuse finance routes and invoice status enum; preserve legacy SPP.
- No inferred cohort, synthetic students, or fabricated successful WhatsApp deliveries.
- Period expiration must preserve invoices, payment eligibility, and monthly generation.
- Scope all referenced IDs and payments to the active school.

## Review Focus

- Short months clamp day 31 independently for start and due dates.
- Simultaneous generation is protected by unique period/student keys and locks.
- Paid invoices are skipped at delivery time even if reminders already exist.
- Existing unlinked invoices must never be linked solely by name automatically.
- Missing guardian phone/provider must be explicit and never fall back to a student's number.

## Task 1: Targets, periods and generation

Files: new migration, BillingPeriod model; BillingItem, Invoice, Student, BillingService; tests/Feature/CommitteeBillingTest.php.
Interfaces: targetStudents(BillingItem), create(int schoolId, array attributes), generate(BillingItem, CarbonInterface date), process(CarbonInterface date).
- [x] Write tests for target intersection, month clamping, one-off creation, idempotency, catch-up and expiration.
- [x] Run `php artisan test tests/Feature/CommitteeBillingTest.php` and observe missing implementation failures.
- [x] Add snapshots, foreign keys, unique period/student keys and generation service.
- [x] Re-run focused tests.

## Task 2: Validated form and real student data

Files: FinanceFoundationController, billing.blade.php, StudentController, siswa.blade.php.
Interfaces: Task 1 target query is shared by preview and generation; UI uses supplied school/year/class/student metadata.
- [x] Add route tests for school/category validation, empty targets and actual counts.
- [x] Replace controller generation with Task 1 service; implement chained dropdowns, cohort input and error recovery.
- [x] Re-run route tests and Blade compilation.

## Task 3: Payment rules and arrears

Files: BillingPaymentService; SppTransactionController, FinanceService, Invoice; focused payment tests.
Interfaces: refreshCharges(Invoice, CarbonInterface date), validatePayment(Invoice, amount), syncStatus(Invoice).
- [x] Write tests for partial/no installment, minimum final installment, capped fees and expired payable invoices.
- [x] Implement locked payment and approved submission integration using integer cents.
- [x] Re-run payment tests and legacy finance tests.

## Task 4: Reminders, scheduler and verification

Files: BillingReminder model/migration, BillingReminderService, delivery contract and HTTP adapter, command/config, route scheduler; reminder tests.
Interfaces: prepare(CarbonInterface date), deliverPending(); adapter explicitly confirms accepted dispatch.
- [x] Write tests for H-7/deadline/monthly arrears, deduplication, missing phone/provider, paid-at-delivery and failure.
- [x] Implement persistent reminder state, configured optional delivery and daily command.
- [x] Run focused/full suite, build, Blade compilation, Pint, diff checks and rendered browser checks.
- [x] Review final patch with a fresh reviewer as required by executing-plans; fix actionable findings.

## Execution ledger

Work remains in the existing checkout on branch `codex/tagihan-komite-flow` so the user can review and run it immediately; no separate worktree requested. User subsequently requested skipping provider integration: the default gateway is deferred, and no external messages are sent. The additive migration was applied to the configured local MySQL database at 127.0.0.1; browser QA uses a separate temporary SQLite database.

- Tasks 1–4 implemented. TDD exposed missing methods, request validation, fee/minimum rules, reminder dates/default state, legacy invoice ownership, payment error recovery, and automatic account posting; focused tests passed after fixes.
- Whole-branch independent review found and led to fixes for legacy one-off duplication, null-school legacy SPP payments, wrong error modal, and automatic account/ledger association.
- Full suite: 161 passed, 1079 assertions. Build passed with the existing large-chunk warning. Browser verified target intersection, exact count, invalid installment recovery, partial payment and mobile grid containment.
- Provider integration intentionally deferred by the user's instruction. Server cron activation remains deployment configuration, not an external change performed here.
