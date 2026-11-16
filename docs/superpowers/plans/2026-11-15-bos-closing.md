# BOS annual closing implementation plan

Goal: Adapt Bendahara BOS closing to the supplied five-stage reference with continuous Antislop audit.
Architecture: Add an annual BOS workflow service/controller/view, preserving existing committee code and legacy snapshots. Scope by school, source and declared BOS fiscal year. Persist immutable reports, metadata, notes and next opening without changing money. Revalidate fingerprint under school lock at confirmation.
Stack: Laravel / Blade / Alpine / Pest / existing EDUJA work components.

Design read: operational finance task UI for Bendahara; EDUJA teal and existing typography; ENERGY 1 / RHYTHM 2 / MOTION 1. Five stages orient the user; mobile stacks the active workflow and collapses secondary detail. Chart compares actual expenditure categories. No example screenshot figures or approval identities are copied.

- [x] Test source/year scoping, stale confirmation, locking, immutable reports, access and shared account ledger openings.
- [x] Implement real readiness checks and year/source snapshot; no automatic approval or guessed posting of historical transactions.
- [x] Implement year/source selection, eight checks, details, confirmation/note, success/reference, realization, physical balances, history and print reports.
- [x] Run finance regressions, full suite, build, browser desktop/mobile/theme and audit during implementation; obtain final code review.

Decisions: existing accounts and wallets lack per-source/year sub-balances. Physical balances are identified as shared BOS balances, distinct from selected-year inflow/outflow. Wallet allocations never increase cash totals. Balance equality checks validate ledger/reconciliation, not a requirement to spend every rupiah. No invented principal approval. Existing permission rules for reopen remain. Annual BOS closes are directly available as in the supplied reference; no new mandatory monthly prerequisite is introduced.

Validation: full suite: 334 tests / 2,183 assertions pass; final 56 finance tests / 655 assertions pass; Vite build and git diff --check pass. Browser 1920/390/320, confirmation guards, notes, report print invocation, light/dark and keyboard checked. Separate read-only review found no important bugs. Audit log: anti-slop/audit-002-2026-11-15-bos.md. No live BOS closing performed.
