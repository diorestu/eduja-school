# Dinas Monitoring Implementation Plan

> Execute inline using superpowers:executing-plans. Preserve the current checkout and unrelated changes.

**Goal:** Complete Dinas monitoring, school filtering, consent-based finance visibility, and verified own-profile updates.

**Architecture:** Add schools.dinas_finance_allowed (default false). DistrictDashboardService provides only scoped authorized dashboard data. A dedicated kepala sekolah setting controls the flag; existing profile actions remain self-scoped.

**Tech Stack:** Laravel 12, Blade/Tailwind, ApexCharts, Pest/SQLite.

**Spec:** docs/superpowers/specs/2026-10-26-dinas-monitoring-design.md

## Constraints and review focus

- Monitoring must never confer school operational access or change active_school_id.
- No unauthorized finance amounts in rendered JSON, even in aggregates.
- Pending/revoked/foreign membership cannot grant consent.
- All chart/population queries honor active selected-school scope, including empty scopes and BOS subtypes.
- Profile writes must ignore supplied target ID/role/consent fields.

## Task 1: Consent and authorization

- [x] Add schema regression; run red; add migration and School boolean cast.
- [x] Add consent tests; run red; implement DistrictFinanceAccessController, role-guarded settings routes, existing-style form and menu item.
- [x] Verify kepala sekolah grant/revoke, tenant scope, denied Dinas/other roles, and persistence.

## Task 2: Dashboard filter and finance scope

- [x] Add dashboard/filter/finance leak tests and run red.
- [x] Implement DistrictDashboardService and dinas(Request, service), preserving districtDashboard view contract.
- [x] Add labeled school selector, reset link, consent coverage/denied states, and guarded BOS chart serialization.
- [x] Verify own-profile updates and attempted cross-user/role injection with existing controller.

## Task 3: Verification

- [x] Run focused and full suites, scoped formatting, production asset build, and diff whitespace checks.
- [x] Review scoped changes independently and resolve actionable findings.
- [x] Verify rendered filter, grant/revoke, desktop/mobile, and console health with browser automation if available.

## Record

- User confirmed kepala sekolah as grantor and own-profile update only; continue within that approved scope.
- Consent applies to the Dinas role for the school; no new per-account grant management.
- Preserve the current BOS expense chart purpose; do not introduce a new financial report.

- Baseline: 247 tests passed (1718 assertions). New focused suite: 16 tests passed (55 assertions). Full suite: 263 tests passed (1773 assertions).
- Scoped Pint and git diff --check passed; npm run build succeeded with the existing large-bundle warning.
- Independent read-only review found no actionable issues.
- Local migration applied using only the new migration path; no unrelated pending migration was run.
- Playwright browser QA: isolated SQLite fixtures, Dinas filter/reset, unauthorized finance absent from DOM/serialized data, principal grant/revoke and immediate Dinas refresh effect; desktop 1440x1100 and mobile 390x844, no horizontal overflow, console errors/warnings zero.
- QA setup ruling: Laravel serve strips custom DB environment overrides unless --no-reload is used; restarted the QA server with --no-reload before browser login succeeded. No operational fixtures created.

- Browser QA caught initial chart overflow after sidebar layout settled; renderChart now observes host width and resizes without animation. Verified both charts fit their hosts, sidebar toggle, mobile filter/reset, and missing BOS key in denied serialized dashboard payload. Re-review found no issue in the resize helper.
- Final full suite after UI repair: 263 passed, 1773 assertions.
