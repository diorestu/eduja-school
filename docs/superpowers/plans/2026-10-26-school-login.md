# School Login Implementation Plan

> **For agentic workers:** Use superpowers:executing-plans in the current session. Preserve unrelated changes in this checkout.

**Goal:** Complete the approved basic login and active-school flow.

**Architecture:** Reuse AuthController, SchoolSelectionController, SchoolContext, and PermissionService. Keep current role dashboard routing and combine active membership roles.

**Tech Stack:** Laravel 12, Blade, Pest, SQLite test database.

**Spec:** docs/superpowers/specs/2026-10-26-school-login-design.md

## Global Constraints

- Dinas/Yayasan retain their separate dashboards.
- Count unique active schools with active memberships.
- No school access without a valid active membership; preserve unrelated workspace changes.
- No new role-selection screen or dashboard redesign.

## Review Focus

- Stored intended URL and stale school session must not skip school selection.
- Revoked/pending memberships and suspended schools must not grant legacy privileges.
- Two roles in one school must not trigger multi-school selection.
- Permission grants from other schools must not leak after switching.
- Executive users remain independent of school membership and selected-school state.

## Task 1: Login and school context

**Files:** AuthController.php, SchoolSelectionController.php, SchoolContext.php, EnsureSchoolIsSelected.php, routes/web.php, resources/views/pages/school/select.blade.php, tests/Feature/SchoolLoginFlowTest.php.

**Interfaces:** Preserve existing login, school.select, school.switch, dashboard route names and SchoolContext method signatures.

- [x] Add tests for email/HP login, invalid credentials, one/multiple/no schools, pending affiliations, stored destinations, unauthorized switching, revoked/suspended school context, and executive dashboards.
- [x] Run `php artisan test --compact tests/Feature/SchoolLoginFlowTest.php` and verify missing behaviors fail.
- [x] Reset context on login; require valid school selection on school requests; direct executives to their dashboards; show pending affiliations only for the signed-in user without active access.
- [x] Run focused tests and verify they pass.

## Task 2: Membership permissions and verification

**Files:** app/Services/PermissionService.php, app/Models/User.php, tests/Feature/SchoolLoginFlowTest.php.

**Interfaces:** Preserve rolesForActiveSchool(User): array and User::hasRole(string|array): bool.

- [x] Test combined active-school roles, cross-school switching, revoked-role fallback denial, and executive permissions.
- [x] Resolve school roles exclusively through active memberships of active schools; retain global executive roles.
- [x] Run focused tests, `php artisan test --compact`, scoped Pint checks, and diff whitespace checks.
- [x] Review final patch against spec; report results without committing unrelated work.

## Execution record

- Baseline: 221 tests passed, 1595 assertions.
- Ruling: Execute in the existing checkout to preserve the user's in-progress state; no branch/commit changes.
- Ruling: The user's “ok” authorizes the bounded changes; preserve existing dashboard priority for combined roles.

- Ruling: Redirect GET/HEAD requests without valid school context to school selection; reject write requests with HTTP 403 so no mutation is performed.
- Regression run before implementation: 12 failed and 7 passed, demonstrating stale-context, intended-URL, and role-fallback gaps.
- Verification: all 247 tests passed (1718 assertions); six scoped PHP files passed Pint; git diff --check passed.
- Updated legacy test fixtures to establish active membership/selection before testing permissions and menus; the no-school income test now expects the approved safe redirect.
- Independent scoped review: no actionable regressions reported. Browser visual verification was not performed.
