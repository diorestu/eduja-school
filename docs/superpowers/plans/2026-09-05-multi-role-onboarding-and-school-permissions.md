# Multi-Role Onboarding and School Permissions Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add role-specific onboarding, school-code membership, guardian-owned students, multi-school teacher selection, and superadmin-only school/permission administration.

**Architecture:** Extend the existing Laravel auth, `school_user_roles`, `PermissionService`, `SchoolContext`, and Blade patterns. Keep school operations school-scoped, make PIC a complete operational role, and isolate global superadmin checks in dedicated middleware/controller authorization.

**Tech Stack:** Laravel, Eloquent migrations/models, Blade, Pest feature tests, existing Tailwind/Vite frontend.

**Spec:** `docs/superpowers/specs/2026-09-04-multi-role-onboarding-and-school-permissions-design.md`

## Global Constraints

- Guardians are active immediately but require an approved school code before school-scoped student data is created.
- Teachers verify email and may have multiple active school memberships; school code is optional at registration and required to request membership.
- Schools require superadmin approval; PIC has all operational school permissions but no superadmin access.
- Only the single global `super_admin` may access school verification, user/role administration, and permission administration.
- Student access requires matching `school_id` and `guardian_user_id` for guardian routes.
- Preserve existing legacy roles and school-scoped permission behavior.

### Task 1: Data lifecycle and membership schema

**Files:**
- Create: `database/migrations/*_add_onboarding_fields_and_guardian_scope.php`
- Create: `database/migrations/*_add_membership_status_and_join_requests.php`
- Modify: `app/Models/User.php`, `app/Models/School.php`, `app/Models/Student.php`, `app/Models/SchoolUserRole.php`
- Create: `app/Models/SchoolJoinRequest.php`
- Test: `tests/Feature/OnboardingSchemaTest.php`

- [ ] Write failing schema and relationship tests for statuses, school code, membership status, join requests, and student owner/school foreign keys.
- [ ] Run `php artisan test tests/Feature/OnboardingSchemaTest.php` and confirm failure is caused by missing schema.
- [ ] Add migrations with safe nullable/backfill steps, unique active school codes, foreign keys where existing data allows, and membership statuses.
- [ ] Add model casts, fillable fields, relationships, and constants for onboarding/membership status.
- [ ] Re-run the focused schema suite and existing model/permission tests.

### Task 2: Authorization primitives and exactly-one superadmin guard

**Files:**
- Create: `app/Http/Middleware/EnsureSuperAdmin.php`
- Modify: `app/Services/PermissionService.php`, `app/Services/SchoolContext.php`, `app/Models/User.php`, `app/Helpers/MenuHelper.php`, `bootstrap/app.php` or existing middleware registration
- Test: `tests/Feature/OnboardingAuthorizationTest.php`

- [ ] Write failing tests proving PIC can access operational permission keys but receives 403 on superadmin routes, inactive memberships are rejected, and a second global superadmin cannot be created.
- [ ] Run the focused authorization tests and confirm expected failures.
- [ ] Implement `EnsureSuperAdmin`, PIC default operational permissions, active-membership checks, and an atomic service/model guard for the single global superadmin.
- [ ] Re-run focused tests plus `tests/Feature/SchoolRolePermissionTest.php`.

### Task 3: Registration and email-verification flow

**Files:**
- Modify: `app/Http/Controllers/AuthController.php`, `routes/web.php`, `app/Models/User.php`
- Create: `app/Http/Controllers/RegistrationController.php` if separation improves the current controller
- Modify/Create: `resources/views/pages/auth/signup.blade.php`, role-specific auth views, mail/notification classes if needed
- Test: `tests/Feature/MultiRoleRegistrationTest.php`

- [ ] Write failing feature tests for role choice, guardian immediate activation, teacher unverified status, school pending status with proposed PIC, duplicate email, and public superadmin rejection.
- [ ] Run the focused tests and confirm failures before implementation.
- [ ] Implement validated role-specific registration, transactional school/PIC creation, explicit statuses, and Laravel email verification routes/middleware for teachers.
- [ ] Add distinct success/pending/error screens and preserve existing sign-in/logout behavior.
- [ ] Re-run the focused registration suite.

### Task 4: School-code selection and teacher multi-school membership

**Files:**
- Create/Modify: `app/Http/Controllers/SchoolMembershipController.php`, `app/Http/Controllers/SchoolSelectionController.php`, `routes/web.php`
- Modify: `app/Services/SchoolContext.php`, `resources/views/pages/school/*`, `resources/views/pages/auth/*`
- Test: `tests/Feature/SchoolMembershipTest.php`

- [ ] Write failing tests for valid/invalid/revoked codes, guardian school selection, teacher join requests, PIC approval, multi-school selector, and permission re-evaluation after switching.
- [ ] Run the focused tests and confirm they fail for the missing membership flow.
- [ ] Implement transactional code validation, guardian membership creation, teacher join requests, PIC/superadmin approval, selector modal/page, and session-safe active-school switching.
- [ ] Ensure a user with multiple schools cannot enter school-scoped routes without an active school.
- [ ] Re-run membership tests and existing school-selection tests.

### Task 5: Guardian-owned student management

**Files:**
- Modify: `app/Http/Controllers/StudentController.php`, `app/Models/Student.php`, `routes/web.php`
- Create: `resources/views/pages/guardian/students.blade.php`
- Test: `tests/Feature/GuardianStudentTest.php`

- [ ] Write failing tests for creating a student under the active school and guardian, listing only owned students, and rejecting cross-guardian/cross-school reads and writes.
- [ ] Run the focused tests and confirm failure before changing production code.
- [ ] Implement guardian-only endpoints/views with explicit ownership and school predicates; keep staff school student management compatible.
- [ ] Re-run guardian tests and existing student/foundation tests.

### Task 6: Superadmin onboarding and permission administration UI

**Files:**
- Create/Modify: superadmin controller(s), routes, Blade views, request classes
- Modify: `app/Http/Controllers/SchoolRolePermissionController.php`, existing permission views
- Test: `tests/Feature/SuperAdminOnboardingTest.php`, `tests/Feature/SchoolRolePermissionTest.php`

- [ ] Write failing tests for pending school list, approve/reject, code rotation, membership/user-role administration, and PIC denial.
- [ ] Run focused tests and confirm expected authorization failures.
- [ ] Implement superadmin-only workflows with audit actor/timestamps and transactional approval/rejection.
- [ ] Add role/permission matrix handling for `pic_sekolah` without exposing superadmin permissions.
- [ ] Re-run both focused suites.

### Task 7: End-to-end verification and UI wiring

**Files:**
- Modify: affected sidebar/dashboard/auth views and route wiring only where tests identify gaps
- Test: full existing `tests/Feature` suite and relevant browser/build checks

- [ ] Run `php artisan route:list` filtered for onboarding, school, permission, and student routes.
- [ ] Run the focused onboarding/authorization/membership/guardian/superadmin suites.
- [ ] Run the complete project test command from `composer.json`/`package.json` and the available frontend build/lint command.
- [ ] Inspect changed files and confirm unrelated worktree changes remain untouched.
- [ ] Perform browser verification for registration, school selection, guardian student creation, and PIC/superadmin denial states; report email delivery and production behavior separately.
