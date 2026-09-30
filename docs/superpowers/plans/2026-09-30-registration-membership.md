# Registration Membership Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver school-controlled registration for teachers/staff, students, and guardians while retaining compatible school onboarding and portal access.

**Architecture:** `school_user_roles` remains the school membership record and receives review metadata. A dedicated activation-token service owns opaque, purpose-bound redemption; a dedicated membership service owns request, decision, and revocation state changes. Controllers stay thin and apply existing school context and permission middleware.

**Tech Stack:** Laravel, PHP, Eloquent, Blade, Pest, database migrations.

**Spec:** `docs/superpowers/specs/2026-09-30-registration-membership-design.md`

## Global Constraints

- Preserve active-school scoping and existing login, portal, attendance, finance, and school-selection contracts.
- Keep latitude and longitude out of registration UI and persistence introduced by this feature.
- Do not delete users, students, memberships, or token records for normal review, expiry, or revocation operations.
- Do not reuse `approval_requests` for membership decisions.
- Never expose raw activation-token values after the creation response.
- Keep existing `schools.registration_code` compatibility-only; new sensitive paths use activation tokens.

## Review Focus

- Concurrent token redemption must not exceed the token maximum use count; cover transaction/locking behaviour in the activation-token task.
- A rejected or revoked membership must not appear in school selection or grant a permission; cover both in the membership-review task.
- A guardian token must not disclose the protected student's name to an unrelated account; cover rejected/mismatched redemption in the guardian-link task.
- A user already linked to a student or school must receive an idempotent result rather than duplicate records; cover duplicate redemption and duplicate request cases.
- Existing `guardian_user_id` records must still power portal ownership while `student_guardians` is introduced; cover legacy-only guardian access in the compatibility task.

---

### Task 1: Add reversible registration domain schema

**Files:**
- Create: `database/migrations/2026_09_30_000001_add_registration_membership_domain.php`
- Create: `app/Models/ActivationToken.php`
- Create: `app/Models/StudentGuardian.php`
- Modify: `app/Models/SchoolUserRole.php`
- Modify: `app/Models/Student.php`
- Modify: `app/Models/User.php`
- Test: `tests/Feature/RegistrationMembershipSchemaTest.php`

**Interfaces:**
- Consumes: existing `schools`, `students`, `users`, and `school_user_roles` tables.
- Produces: `ActivationToken`, `StudentGuardian`, reviewed membership columns, and the `Student::guardians()` / `User::studentGuardianLinks()` relationships.

- [ ] **Step 1: Write failing schema and relationship tests**

Assert the reviewed membership fields exist, `activation_tokens` stores only a unique token hash plus purpose, school, optional student, expiry/use/revocation/audit fields, and `student_guardians` is unique for a student/user pair with a constrained relationship value.

- [ ] **Step 2: Run the schema test to verify it fails**

Run: `php artisan test tests/Feature/RegistrationMembershipSchemaTest.php --compact`

Expected: FAIL because the tables, columns, and relationships do not exist.

- [ ] **Step 3: Add the migration and Eloquent models**

Create additive nullable review columns (`reviewed_by`, `reviewed_at`, `review_note`) on `school_user_roles`; create the two new tables with foreign keys and indexes for their scoped lookups. Define mass assignment and relationships only for fields the services need.

- [ ] **Step 4: Run the schema test to verify it passes**

Run: `php artisan test tests/Feature/RegistrationMembershipSchemaTest.php --compact`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add database/migrations app/Models tests/Feature/RegistrationMembershipSchemaTest.php
git commit -m "feat: add registration membership domain"
```

### Task 2: Implement activation-token issuance and redemption

**Files:**
- Create: `app/Services/ActivationTokenService.php`
- Test: `tests/Feature/ActivationTokenServiceTest.php`

**Interfaces:**
- Consumes: `ActivationToken`, `School`, `Student`, `User`, and the schema from Task 1.
- Produces: `issue(int $schoolId, User $issuer, string $purpose, ?int $studentId, CarbonInterface $expiresAt, int $maxUses = 1): array`, returning the model and one raw token; `redeem(string $rawToken, string $purpose, ?int $expectedStudentId = null): ActivationToken`.

- [ ] **Step 1: Write failing token service tests**

Test a student token issues a raw token only once, stores a non-raw hash, redeems exactly once, and atomically rejects an expired, revoked, exhausted, wrong-purpose, or wrong-student token.

- [ ] **Step 2: Run the token service test to verify it fails**

Run: `php artisan test tests/Feature/ActivationTokenServiceTest.php --compact`

Expected: FAIL because `ActivationTokenService` does not exist.

- [ ] **Step 3: Implement `ActivationTokenService`**

Generate cryptographically random URL-safe tokens; hash them before storage; use a transaction with a row lock during redemption; increment uses only after all validation succeeds. Use domain exceptions with safe Indonesian messages; do not serialize protected student details.

- [ ] **Step 4: Run the token service test to verify it passes**

Run: `php artisan test tests/Feature/ActivationTokenServiceTest.php --compact`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/ActivationTokenService.php tests/Feature/ActivationTokenServiceTest.php
git commit -m "feat: add scoped activation tokens"
```

### Task 3: Implement school membership state transitions

**Files:**
- Create: `app/Services/MembershipService.php`
- Test: `tests/Feature/MembershipServiceTest.php`

**Interfaces:**
- Consumes: `SchoolUserRole`, `SchoolContext`, `PermissionService`, and Task 1 models.
- Produces: `requestJoin(User $user, School $school, string $role): SchoolUserRole`, `review(SchoolUserRole $membership, User $reviewer, string $decision, ?string $note = null): SchoolUserRole`, and `revoke(SchoolUserRole $membership, User $reviewer, ?string $note = null): SchoolUserRole`.

- [ ] **Step 1: Write failing membership service tests**

Cover multi-school teacher/staff pending requests, duplicate active/request idempotency, approval activation, rejection, revocation, self-review denial, cross-school reviewer denial, and rejection/revocation exclusion from `SchoolContext::availableSchools()`.

- [ ] **Step 2: Run the membership test to verify it fails**

Run: `php artisan test tests/Feature/MembershipServiceTest.php --compact`

Expected: FAIL because `MembershipService` does not exist.

- [ ] **Step 3: Implement `MembershipService`**

Ensure pending/rejected/revoked memberships set `is_active` false; approval is the only operation that activates a member. Check reviewer active membership and the appropriate existing school-scoped permission before modifying any row.

- [ ] **Step 4: Run the membership test to verify it passes**

Run: `php artisan test tests/Feature/MembershipServiceTest.php --compact`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/MembershipService.php tests/Feature/MembershipServiceTest.php
git commit -m "feat: add school membership review workflow"
```

### Task 4: Extend account registration safely

**Files:**
- Modify: `app/Http/Controllers/AuthController.php`
- Modify: `resources/views/pages/auth/signup.blade.php`
- Test: `tests/Feature/RegistrationFlowTest.php`

**Interfaces:**
- Consumes: `ActivationTokenService::redeem`, `MembershipService::requestJoin`, existing user creation and role redirect rules.
- Produces: supported signup types `guru_tendik`, `siswa`, `wali_murid`, and `sekolah`, with clear active/pending outcomes.

- [ ] **Step 1: Write failing registration flow tests**

Test school/PIC remains pending; new teacher/staff signup creates an account without active school access and can use the join flow later; student signup requires a valid student token and refuses a second active school; guardian signup creates an account without exposing child information. Also pin the compatibility path: an existing `guru` or `wali_murid` submission containing a valid legacy `school_code` retains its current active school membership behaviour, but never links a child.

- [ ] **Step 2: Run the registration flow test to verify it fails**

Run: `php artisan test tests/Feature/RegistrationFlowTest.php --compact`

Expected: FAIL because the current endpoint accepts only `guru`, `wali_murid`, and `sekolah` and activates code-based memberships immediately.

- [ ] **Step 3: Update `AuthController::signup(Request $request)` and the signup view**

Validate only identity and account fields needed for each type. Use purpose-bound student token redemption for student identity and create one active student membership with `students.user_id`. New teacher/staff and guardian journeys use the request/link screens; retain the existing fixed-school-code branch only for incoming legacy `guru` and `wali_murid` payloads until callers are migrated. Do not add sensitive profile fields or coordinate fields.

- [ ] **Step 4: Run the registration flow test to verify it passes**

Run: `php artisan test tests/Feature/RegistrationFlowTest.php --compact`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/AuthController.php resources/views/pages/auth/signup.blade.php tests/Feature/RegistrationFlowTest.php
git commit -m "feat: align signup with membership flow"
```

### Task 5: Add guardian child linking with legacy compatibility

**Files:**
- Create: `app/Services/GuardianLinkService.php`
- Create: `app/Http/Controllers/GuardianLinkController.php`
- Create: `resources/views/pages/portal/guardian-link.blade.php`
- Modify: `routes/web.php`
- Modify: `app/Models/Student.php`
- Test: `tests/Feature/GuardianLinkTest.php`

**Interfaces:**
- Consumes: `ActivationTokenService::redeem`, `StudentGuardian`, and legacy `Student::guardian_user_id`.
- Produces: `link(User $guardian, string $rawToken, string $relationship): StudentGuardian` and authenticated guardian routes for form display and submission.

- [ ] **Step 1: Write failing guardian-link tests**

Cover a guardian linking multiple children through distinct guardian tokens, valid `ayah`/`ibu`/`wali` validation, duplicate link idempotency, first-link legacy `guardian_user_id` preservation, and a legacy-only guardian retaining portal ownership.

- [ ] **Step 2: Run the guardian-link test to verify it fails**

Run: `php artisan test tests/Feature/GuardianLinkTest.php --compact`

Expected: FAIL because the guardian-link service and routes do not exist.

- [ ] **Step 3: Implement the service, controller, routes, and focused form**

The form accepts only an activation token and relationship. Resolve the student only after the token succeeds; never put a student identifier in the request or error response. Add the link transactionally and do not overwrite an existing primary guardian.

- [ ] **Step 4: Run the guardian-link test to verify it passes**

Run: `php artisan test tests/Feature/GuardianLinkTest.php --compact`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/GuardianLinkService.php app/Http/Controllers/GuardianLinkController.php resources/views/pages/portal/guardian-link.blade.php routes/web.php app/Models/Student.php tests/Feature/GuardianLinkTest.php
git commit -m "feat: add guardian child linking"
```

### Task 6: Add operator token and membership controls

**Files:**
- Create: `app/Http/Controllers/MembershipController.php`
- Create: `resources/views/pages/settings/memberships.blade.php`
- Modify: `routes/web.php`
- Modify: `app/Helpers/MenuHelper.php`
- Test: `tests/Feature/MembershipManagementRouteTest.php`

**Interfaces:**
- Consumes: `ActivationTokenService::issue`, `MembershipService::review`, `MembershipService::revoke`, active school context, and existing permission middleware.
- Produces: school-scoped views and POST routes for token issuance/revocation and membership approval/rejection/revocation.

- [ ] **Step 1: Write failing operator route tests**

Assert an authorized operator can issue a purpose-specific token and review only its school's membership; assert unauthorised, cross-school, and inactive members receive forbidden responses; assert raw token is present in the immediate issuer response and absent from later listings.

- [ ] **Step 2: Run the operator route test to verify it fails**

Run: `php artisan test tests/Feature/MembershipManagementRouteTest.php --compact`

Expected: FAIL because management routes and controller do not exist.

- [ ] **Step 3: Implement the management controller and operational screen**

Use the existing permission middleware convention and direct, state-specific copy. Keep request queues and token controls separate from attendance/finance approvals. Do not show raw tokens in history or list views.

- [ ] **Step 4: Run the operator route test to verify it passes**

Run: `php artisan test tests/Feature/MembershipManagementRouteTest.php --compact`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/MembershipController.php resources/views/pages/settings/memberships.blade.php routes/web.php app/Helpers/MenuHelper.php tests/Feature/MembershipManagementRouteTest.php
git commit -m "feat: add membership operator controls"
```

### Task 7: Verify compatibility and complete integration checks

**Files:**
- Modify: `tests/Feature/MultiRoleOnboardingTest.php`
- Modify: `tests/Feature/PhaseOneSchoolContextTest.php` only if assertions need compatible status coverage

**Interfaces:**
- Consumes: all preceding registration services and routes.
- Produces: regression evidence that existing onboarding and school context behaviour remain intact.

- [ ] **Step 1: Add compatibility regression tests**

Assert existing active membership rows remain selectable, legacy `guru` and `wali_murid` `registration_code` signup callers retain their supported membership behaviour without linking a student, and legacy `guardian_user_id` portal access remains scoped to its school.

- [ ] **Step 2: Run focused compatibility tests**

Run: `php artisan test tests/Feature/MultiRoleOnboardingTest.php tests/Feature/PhaseOneSchoolContextTest.php tests/Feature/PortalIntegrityTest.php --compact`

Expected: PASS.

- [ ] **Step 3: Run the complete verification suite**

Run: `php artisan test && php artisan route:list && php artisan view:cache && npm run build && git diff --check`

Expected: tests, route compilation, view cache, frontend build, and whitespace check PASS. Report unrelated pre-existing failures separately if any occur.

- [ ] **Step 4: Commit test updates**

```bash
git add tests/Feature/MultiRoleOnboardingTest.php tests/Feature/PhaseOneSchoolContextTest.php
git commit -m "test: cover registration compatibility"
```
