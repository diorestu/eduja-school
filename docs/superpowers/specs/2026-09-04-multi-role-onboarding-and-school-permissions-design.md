# EDUJA Multi-Role Onboarding and School Permissions

## Status

Approved in conversation for specification review; implementation has not started.

## Goal

Provide a public registration path for teachers, guardians, and schools while enforcing school-scoped access. Guardians can register immediately, select a school using a school-issued reference code, and maintain their own students. Teachers verify email and may belong to multiple schools. A school PIC manages school operations, while exactly one global superadmin controls school verification and permission administration.

## Roles and authority

| Role | Scope | Default authority |
| --- | --- | --- |
| `super_admin` | Global | Verify schools, manage onboarding, users, roles, and permission catalog/grants. Not publicly registerable. |
| `pic_sekolah` | One or more selected school contexts as assigned | All school operational features; never superadmin features. |
| `guru` | Each assigned school | Permission grants for the active school. |
| `wali_murid` | Selected school(s), with owned students | Parent portal and owned student records only. |
| Existing EDUJA roles | Existing school contexts | Preserve current behavior and compatibility. |

`pic_sekolah` is a school role, not a global bypass. Superadmin routes use a dedicated global-role check and cannot be reached by PIC permission grants.

## Account and school lifecycle

1. Public registration offers Guru, Wali Murid, and Sekolah.
2. A school registration creates a pending school and its proposed PIC account. The account/school cannot use school operations until the single global superadmin approves it.
3. Approval activates the school, assigns `pic_sekolah`, and generates or activates a unique school reference code.
4. A guardian registration is active immediately, but must select an approved school and provide its current reference code before creating school-scoped student data.
5. A teacher registration creates an account requiring email verification. School selection is optional at registration. After verification, the teacher can request membership using a school reference code. A school/PIC approval step is required before membership becomes active.
6. If a verified teacher belongs to multiple schools, login/session flow presents a school selector before dashboard access. The selected school is stored as `active_school_id`; switching schools re-evaluates all permissions.

The implementation must use explicit statuses rather than treating a missing role as approval. Suggested statuses are `pending`, `active`, `rejected`, and `suspended` for schools/memberships, with email verification represented by Laravel's existing `email_verified_at` contract.

## Data model changes

- Add explicit school onboarding status and reference-code fields to `schools`; reference codes are unique, non-secret identifiers that can be revoked/rotated.
- Add account onboarding status and registration-type metadata to `users`; preserve the existing legacy `role` column during migration.
- Extend school memberships (`school_user_roles`) to represent pending/active membership and support `pic_sekolah`, preserving school-scoped role and `is_active` compatibility.
- Add `school_id` and `guardian_user_id` to `students`. Student reads/writes must require both the active school and guardian ownership for guardian routes.
- Add a membership/request or equivalent audit record for teacher join requests and school approvals. Approval/rejection actor and timestamps must be retained.
- Enforce uniqueness and foreign keys where compatible with existing data; backfill existing records conservatively before making new columns mandatory.

## Routes and UI

- Add a public registration landing page with three clearly separated choices and role-specific forms.
- Add role-specific success/pending screens that do not imply access before approval or email verification.
- Add guardian school-code selection and an owned-student management page.
- Add teacher school membership request and a multi-school selector shown after authentication when needed.
- Add superadmin-only pages for pending school verification, membership review, user/role administration, and permission matrix management.
- Add PIC school-management pages for operational users and school data, excluding all superadmin pages.
- Keep existing sidebar and direct-route protection aligned through `PermissionService` and the same permission catalog.

## Authorization rules

- The one superadmin account is enforced by configuration/seeding and a database-level or service-level guard against creating a second global superadmin.
- Superadmin features require the global `super_admin` role; `pic_sekolah` and school-scoped grants cannot satisfy this check.
- PIC receives the complete operational school permission set by default, but never permission-management or school-verification permissions.
- Teacher, existing staff, and other school roles use additive school-scoped grants and active membership checks.
- A user with multiple schools must select an active school before school-scoped routes; switching invalidates cached permission decisions.
- Guardians can only access students whose `guardian_user_id` matches the authenticated user and whose `school_id` matches the active school.
- School code validation must require an approved/active school and an unrevoked code; codes must not grant superadmin or PIC authority by themselves.

## Error handling and safety

- Duplicate email, invalid/revoked school code, unverified email, pending membership, rejected school, inactive school, and missing active school each receive a distinct user-facing message.
- Registration and approval actions are transactional; partial school/PIC/membership records must not be left behind.
- Do not expose whether an email belongs to a privileged account in public responses.
- All approval, role, permission, code rotation, and student ownership changes are auditable.

## Testing strategy

Feature tests must cover:

- each registration type and its status/role outcome;
- guardian immediate access, school-code validation, student ownership, and cross-guardian/cross-school rejection;
- teacher email verification, join request, approval, multi-school selection, and permission re-evaluation after switching;
- school pending/approval/rejection and exactly-one-superadmin enforcement;
- PIC access to every operational module and rejection from every superadmin route;
- existing role/permission behavior, direct routes, sidebar visibility, and cross-school grant isolation;
- revoked/invalid codes, inactive memberships, validation failures, and transactional rollback.

Verification should include the focused feature suite, the existing permission suite, route inspection, and the application build/lint command available in this repository. Browser/device/production email delivery remain separate runtime verification boundaries.

## Scope boundaries

This phase does not define payroll, teacher scheduling, invitation delivery beyond the required email verification, student-to-school transfer workflows, or a public superadmin recovery process. Those require separate decisions and must not be inferred during implementation.
