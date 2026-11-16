# Committee closing implementation plan

Goal: Bendahara follows the supplied five-stage Non-BOS closing flow using actual selected-period data.
Architecture: Reuse existing closing, ledger and period guard. Add a dedicated committee workflow service and view; preserve existing BOS and history screens. Existing historical unscoped closings keep their original locking semantics.
Stack: Laravel, Blade, Alpine, Pest.

- [x] Test selected-period committee validation, BOS exclusion, historical snapshots, stale review rejection and school authorization.
- [x] Implement scoped preflight and immutable report package with next-period opening balances.
- [x] Implement period selection, check results, report preview/print and explicit confirmation; retain history.
- [x] Run focused finance tests, build, render checks and post-work Antislop audit.

Decisions: screenshot is layout/flow evidence, not sample financial data. Cash position reports explicitly describe physical school accounts, which may be shared across books. PDF is browser Print / Save as PDF. No new journal subsystem or fabricated checks.

Verification: 326 tests / 2,131 assertions pass; Vite build passes; browser monthly/yearly, eight report previews, print invocation, desktop/mobile and dark mode checked. Post-work audit: anti-slop/audit-001-2026-11-15.md. Separate code review findings about live ledger opening balances and shared BOS year status were fixed and covered by regression tests.
