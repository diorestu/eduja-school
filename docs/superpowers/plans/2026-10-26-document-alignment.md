# Document Alignment Implementation Plan

> Execute the user's approved document adaptation with independent module workers, preserving existing dirty changes. Root integrates shared routes, menus, notifications and verification.

**Goal:** Align existing EDUJA behavior with all actionable rules in the 70-page PDF while recording external deployment prerequisites honestly.

**Architecture:** Extend existing scoped controllers/services; use additive migrations only. Share the active membership and school context boundary. Persist internal notifications transactionally; do not enable external providers implicitly.

**Tech Stack:** Laravel 12, Blade/Alpine/Tailwind, Pest/SQLite, Playwright.

**Spec:** docs/flow-erd-alignment.md and the user-provided PDF.

## Constraints and review focus

- No cross-school, child, class, document or finance data access outside active authorization.
- Preserve source history, old receipt/payment contracts, and prior in-progress changes.
- Closing and budget approvals must be enforced server-side, including corrections and replay.
- Alumni automatic identity/history fields must not be client-editable.
- Distinguish internal completion from real hardware, WhatsApp, or AI activation.

## Module tasks

- [ ] Root: tests first for Yayasan empty/pending scope; fix aggregate queries.
- [ ] Root: tests first for alumni statuses, derived job, region fields, validated manual education history and immutable official history; implement profile entry point.
- [ ] Root: persist internal notifications with recipient/school/idempotency boundaries and integrate feed/mark-read.
- [ ] Finance worker: period locks, closing, snapshots, budgets, BOS budget controls, ledger/report correctness and tests.
- [ ] Academic worker: lifecycle/graduation, conditional status metadata/documents, role appointments, academic CRUD/authorization and tests.
- [ ] Attendance worker: class authorization/enrollment, portal history/payments/teacher views, materials signed downloads, announcements, absence/transfer flows and tests.
- [ ] Root: integrate route/menu snippets, run all tests, build, scoped lint/format and browser QA.
- [ ] Root: scoped independent final review; apply additive migrations by explicit path only and document remaining deployment prerequisites.
