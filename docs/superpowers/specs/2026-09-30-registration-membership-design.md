# Registration and Membership Design

## Purpose

Make EDUJA registration match the useful parts of the supplied flow: a school can control who joins it, students and guardians are linked safely, and every membership state is understandable to the person waiting for it.

## Scope

This design changes registration and school membership only. It preserves existing attendance and finance approvals, current school context behaviour, and the existing login envelope.

### Included user journeys

1. A school PIC submits a school registration and waits for superadmin activation.
2. A teacher or staff member creates an account, searches for schools, and sends membership requests to one or more schools.
3. A student creates an account with a school-issued student activation token and joins exactly one school.
4. A guardian creates an account and redeems a child-specific guardian activation token. A guardian may link multiple children and specifies father, mother, or guardian for each link.
5. A school operator reviews membership requests, approves or rejects them, and can revoke an already active link when necessary.
6. A school operator issues and revokes student and guardian activation tokens.

### Explicitly excluded

- Self-service registration for district offices, foundations, or their administrators. These require organisation models and governance rules that are not present in the project.
- Latitude and longitude inputs or displays. School location remains server-managed configuration for attendance policy.
- Mandatory collection of religion, income, RT/RW, dusun, legal-document details, or a full identity profile during account creation.
- Separate databases for teachers and staff. EDUJA keeps one school membership system with a role.
- Reusing `approval_requests` for membership decisions. Attendance and finance approval semantics remain independent.

## User experience decisions

Registration stays short: name, email or mobile number where supported, password, and the context required for that account type. Longer personal or institutional profiles are completed only after the account has a valid school context.

The registration page presents four choices: teacher/staff, student, guardian, and school/PIC. The teacher/staff choice is one account type with a subsequent role selection; this avoids duplicate registration paths.

The product never tells a person that they are registered merely because a request was submitted. It distinguishes pending, approved, rejected, expired, revoked, and already-linked states with direct Indonesian copy.

## Data model

### School memberships

Extend `school_user_roles` as the canonical membership table. A row represents one user, one school, and one role. It has a membership status of `pending`, `active`, `rejected`, or `revoked`; `is_active` is true only for `active` membership.

Membership requests need requester and reviewer evidence. Add nullable `reviewed_by`, `reviewed_at`, and `review_note` fields. The request creator is the existing `user_id`; no duplicated request table is needed for a first version.

Teacher and staff roles use existing role names. A user may have memberships in multiple schools. A student account can have only one active school membership; the database and service enforce this.

### Activation tokens

Add an `activation_tokens` table owned by a school. A token has an opaque random value stored as a hash, a purpose (`student_registration` or `guardian_link`), an optional `student_id` for guardian tokens, expiry timestamp, maximum uses, use count, issuer, revocation metadata, and timestamps.

The raw token is shown once at creation. Token validation is purpose-specific, school-scoped, atomic, and rejects expired, revoked, exhausted, or mismatched tokens. Its expiry is configurable at issuance; the UI offers a sensible default rather than a fixed five-minute rule.

### Student and guardian links

Keep `students.guardian_user_id` for the existing primary guardian behaviour. Add a `student_guardians` table so a student can have multiple guardians and a guardian can have multiple children. It stores `student_id`, `user_id`, `relationship` (`ayah`, `ibu`, `wali`), `status`, and timestamps, unique per student/user.

Student account registration links `students.user_id` only after the student token proves the school context. Guardian linking uses `student_guardians`; it updates the legacy `guardian_user_id` only when the new link is the first active guardian.

## Services and authorisation

`MembershipService` owns join requests, review, revocation, and school-scoped authorization. It must reject cross-school reviewers, self-review, duplicate active memberships, and an attempt to grant a student a second school.

`ActivationTokenService` owns token issuance, redemption, revocation, and race-safe consumption. It never returns stored token hashes and records only safe audit values.

Only a school-scoped operator role with the relevant existing permission can issue tokens and review memberships. Superadmin activates or rejects schools. These actions use the existing active-school context and permission system rather than global role checks alone.

## Routes and screens

The existing `/signup` page becomes the account entry point for the four supported choices. It should progressively reveal only relevant fields and avoid claims that an account is active before its condition is met.

Authenticated teachers/staff get a school search and join-request screen. Operators get a membership queue and activation-token screen in the school context. Guardians get a child-link screen after account creation. Students redeem their token during signup only.

All mutation routes require CSRF-protected authenticated sessions where applicable, validation, active school scope, and permission middleware.

## Error handling and copy

Validation distinguishes invalid input from duplicate identity and from an unavailable school. A token error explains whether it is invalid, expired, revoked, already used, or intended for a different action without revealing student data.

Rejected or revoked memberships prevent school access but preserve an auditable state. The flow does not delete users, students, memberships, or token records to resolve ordinary operational mistakes.

## Compatibility and migration

Existing active `school_user_roles` rows remain active. Existing guardians continue to work through `students.guardian_user_id`; backfill to `student_guardians` is additive and idempotent. Existing fixed `schools.registration_code` is retained for compatibility until a deliberate migration strategy replaces its callers; it is not used for new sensitive registration paths.

No current portal, attendance, finance, or school selection route changes its response shape as part of this work.

## Verification

Feature tests cover the four sign-up paths, token expiry/revocation/exhaustion, multi-school teacher requests, single-school student protection, guardian multi-child links, role-specific approval, cross-school denial, review idempotency, and legacy guardian compatibility.

Run the focused registration tests, related portal and permission tests, then the full PHPUnit suite, route listing, view compilation, frontend build, and `git diff --check` before considering the feature complete.
