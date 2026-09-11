# EDUJA Fase 1 Identity, Multi-School, and Security Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the PDF's global login and multi-school foundation with explicit person identity links, safe role routing, and no cross-user fallback data.

**Architecture:** Extend the existing Laravel authentication and school membership boundary instead of introducing a second authorization system. A dedicated identity resolver will turn an authenticated user plus active school into a verified student or teacher record, while `SchoolContext` remains the source of active-school state and `PermissionService` remains the source of role permissions.

**Tech Stack:** Laravel, Eloquent migrations/models, Blade, Alpine.js, Pest feature tests, existing Tailwind/Vite assets.

**Spec:** `docs/superpowers/specs/2026-09-11-phase-1-identity-multischool-security-design.md`

## Global Constraints

- Every school-scoped query uses the active school context or an explicitly authorized school ID.
- Every portal query constrains the authenticated user to the linked student/teacher/guardian relationship.
- No controller may fall back to `Student::first()`, `Teacher::first()`, `id > 0`, or a synthetic person when the identity cannot be resolved.
- Dinas and Yayasan do not enter the normal school-selection middleware.
- `pic_sekolah` never satisfies the global superadmin middleware.
- Preserve current legacy roles and additive school-scoped permission behavior.
- All changed UI remains Indonesian, keyboard-accessible, responsive, and free of fabricated data.

### Task 1: Explicit identity and lifecycle schema

**Files:**
- Create: `database/migrations/2026_09_11_000001_add_phase1_identity_fields.php`
- Modify: `app/Models/User.php`, `app/Models/Student.php`, `app/Models/Teacher.php`, `app/Models/SchoolUserRole.php`, `app/Models/School.php`
- Create: `app/Services/IdentityResolver.php`
- Test: `tests/Feature/PhaseOneIdentitySchemaTest.php`

**Interfaces:**
- `IdentityResolver::studentFor(User $user, ?int $schoolId = null): ?Student`
- `IdentityResolver::teacherFor(User $user, ?int $schoolId = null): ?Teacher`
- `SchoolContext::availableSchools(User $user): Collection` returns only active memberships and active schools.

- [ ] Write a failing test that asserts `users` has a normalized phone and lifecycle fields, `students.user_id`, `teachers.user_id`, and active membership status fields exist, and model relationships resolve the linked person.
- [ ] Run `php artisan test tests/Feature/PhaseOneIdentitySchemaTest.php` and confirm the failure is caused by missing columns/relationships.
- [ ] Add nullable indexed identity columns and a normalized phone column without breaking existing rows; retain the existing `role` field and backfill new lifecycle fields to the current active behavior.
- [ ] Add model relationships, casts, fillable fields, and the `IdentityResolver` methods. The resolver must return `null` when no explicit link exists and must always constrain by school ID.
- [ ] Re-run the focused schema test and `php artisan migrate:fresh --force` in the test environment.

### Task 2: Authentication by email or phone with lifecycle checks

**Files:**
- Modify: `app/Http/Controllers/AuthController.php`, `routes/web.php`, `resources/views/pages/auth/signin.blade.php`
- Modify: `database/factories/UserFactory.php`
- Test: `tests/Feature/PhaseOneAuthenticationTest.php`

**Interfaces:**
- `AuthController::signin(Request $request)` accepts one `login` field and resolves either normalized email or normalized phone.
- `AuthController::redirectAfterLogin(Request $request, User $user): RedirectResponse` routes executive users separately and school-scoped users through membership selection.

- [ ] Write failing tests for email login, phone login, generic invalid credentials, pending/suspended/rejected denial, teacher email verification denial, and executive users bypassing normal school selection.
- [ ] Run `php artisan test tests/Feature/PhaseOneAuthenticationTest.php` and verify expected failures before changing authentication code.
- [ ] Implement normalization at input and lookup time, generic credential errors, explicit lifecycle handling, session regeneration, and role-aware redirect selection.
- [ ] Replace the login form's email-only field with a visible `Email atau nomor HP` field, preserve autocomplete, expose field-level errors, and retain the working support link.
- [ ] Re-run the focused authentication suite and existing auth-related feature tests.

### Task 3: Active membership and school selection context

**Files:**
- Modify: `app/Services/SchoolContext.php`, `app/Http/Controllers/SchoolSelectionController.php`, `app/Http/Middleware/EnsureSchoolIsSelected.php`, `routes/web.php`
- Modify: `resources/views/pages/school/select.blade.php`, `resources/views/layouts/app-header.blade.php`
- Test: `tests/Feature/PhaseOneSchoolContextTest.php`

**Interfaces:**
- `SchoolContext::availableSchools(User $user): Collection` filters `schools.status = active`, `schools.is_active = true`, `school_user_roles.is_active = true`, and `membership_status = active`.
- `SchoolContext::setActiveSchool(User $user, int $schoolId): School` validates membership before writing `active_school_id`.

- [ ] Write failing tests for one-school auto-selection, multi-school redirect, inactive school exclusion, inactive membership exclusion, invalid switch rejection, and permission context change after switching.
- [ ] Run the focused school-context suite and confirm failure on missing status filtering/validation.
- [ ] Implement `setActiveSchool`, clear the active context when it no longer belongs to the user, and ensure executive routes are outside normal selection middleware.
- [ ] Build the selector as responsive cards with maximum three desktop columns, role badges, school details, visible focus, and an actionable empty state. Add the header dropdown for eligible multi-school users.
- [ ] Re-run focused context tests and current `EdujaFoundationPlanTest` school selection tests.

### Task 4: Role routing and authorization parity

**Files:**
- Create: `app/Services/RoleRedirectService.php`
- Modify: `app/Services/PermissionService.php`, `app/Http/Middleware/EnsureUserHasPermission.php`, `app/Helpers/MenuHelper.php`, `routes/web.php`, `app/Http/Controllers/DashboardController.php`
- Test: `tests/Feature/PhaseOneRoleRoutingTest.php`

**Interfaces:**
- `RoleRedirectService::afterLogin(User $user): string` returns the named route for the authenticated global or selected-school context.
- `PermissionService::can(User $user, string $permission, array $defaultRoles = []): bool` remains the single permission decision path.

- [ ] Write failing tests for role redirects, global Dinas/Yayasan isolation, PIC operational access without superadmin access, and menu/direct-route parity.
- [ ] Run the focused role-routing suite and confirm failures for the current implicit redirects.
- [ ] Implement explicit redirect rules and ensure no role is granted access solely because a request contains a school ID.
- [ ] Preserve executive special handling requiring exact `dinas` or `yayasan` roles before global admin bypasses; keep executive menu entries hidden from school administrators and superadmin.
- [ ] Re-run focused role tests and `SchoolRolePermissionTest`.

### Task 5: Remove portal identity fallbacks and enforce ownership

**Files:**
- Modify: `app/Http/Controllers/PortalFoundationController.php`, `app/Http/Controllers/StudentController.php`, `app/Models/Student.php`
- Create: `resources/views/pages/portal/empty-identity.blade.php`
- Test: `tests/Feature/PhaseOnePortalSecurityTest.php`

**Interfaces:**
- Portal student actions use `IdentityResolver::studentFor()` and return the empty-identity view when it returns `null`.
- Parent children queries use `Student::where('guardian_user_id', $user->id)->where('school_id', $schoolId)` and never name matching.

- [ ] Write failing tests proving an unlinked student user cannot see any student, a parent sees only owned students, a cross-school parameter cannot switch the selected child, and an unlinked teacher cannot see a synthetic teacher.
- [ ] Run `php artisan test tests/Feature/PhaseOnePortalSecurityTest.php` and observe failures from the current `id > 0`, `first()`, and synthetic fallbacks.
- [ ] Replace all runtime fallback branches with explicit `null` handling and the honest empty-identity view; remove hard-coded person names, phone numbers, class locations, and GPS coordinates from runtime responses.
- [ ] Add server-side ownership predicates to student attendance, permission requests, invoices, savings, and teacher/tendik portal queries.
- [ ] Re-run the focused portal security suite and the existing portal-related feature suite.

### Task 6: Registration identity links and safe onboarding states

**Files:**
- Modify: `app/Http/Controllers/AuthController.php`, `resources/views/pages/auth/signup.blade.php`
- Modify/Create: `app/Http/Requests/*` only for registration validation if the existing controller needs extraction
- Test: `tests/Feature/PhaseOneRegistrationTest.php`

- [ ] Write failing tests for normalized phone uniqueness, guardian registration without fabricated child assignment, teacher registration awaiting email verification, school/PIC pending onboarding, and public superadmin rejection.
- [ ] Run the focused registration suite and confirm missing validation/state failures.
- [ ] Implement transactional registration data, preserve guardian school-code membership rules, store optional identity phone safely, and leave person links null until verified/admin-linked.
- [ ] Update signup copy and fields so each role explains its next step without claiming access before verification.
- [ ] Re-run focused registration tests and existing onboarding tests.

### Task 7: UI states, comment hygiene, and final verification

**Files:**
- Modify: affected Blade views and controllers only where prior tasks identify a state or copy gap
- Test: `tests/Feature/PhaseOneUiContractTest.php`

- [ ] Write failing view assertions for visible labels, inline errors, loading/disabled submit states, empty identity state, school selector focus affordance, and no fabricated person data.
- [ ] Run the focused UI contract test and confirm missing assertions before adding markup.
- [ ] Implement accessible UI states with one primary action per screen, 44px targets, keyboard focus, reduced-motion-safe transitions, and honest empty/error copy.
- [ ] Review comments touched during implementation using the antislop-code checklist; remove only decorative, obvious, or workflow-narrating comments and preserve business/security rationale.
- [ ] Run `php artisan view:cache`, `php artisan route:list`, the complete `php artisan test --testsuite=Feature`, `npm run build`, and `git diff --check`.
- [ ] Perform browser click-through at 320, 375, 414, and 768px for login, multi-school selection, school switching, empty identity, and role redirects. Record any runtime console or delivery limitations separately.
