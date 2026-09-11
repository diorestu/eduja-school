# EDUJA Fase 1: Identity, Multi-School, and Security

## Status

Draft for user review. No implementation changes are included in this specification.

## Source and scope

Source of target behavior: `/Users/user/Downloads/Flow dan ERD belum AI (1).pdf`, especially the global login/multi-school flow on page 1 and role flows on pages 2-5, 8, 14, 38, 47, 50, and 54.

This phase establishes the identity and authorization substrate required by later academic, finance, attendance, portal, and communication phases. It does not implement every role feature from the PDF.

## Target flow

### Global school-scoped user

1. User submits email or phone plus password.
2. System authenticates credentials and verifies account lifecycle status.
3. System loads active memberships as `(user_id, school_id, role)`.
4. If the user has multiple active school memberships, show a dedicated school selector.
5. User selects the active school; the session stores `active_school_id` and the selected role context is re-evaluated.
6. If the user has exactly one active school, select it automatically.
7. Redirect to the role dashboard permitted by the active membership.
8. If there is no active membership, show a safe no-school state with an actionable next step. Never load a random school record.

### Executive users

`dinas` and `yayasan` remain global executive contexts and do not enter the normal school-selection middleware. They load only their own aggregate scope and may drill into an explicitly authorized school detail view without gaining operational school-menu access.

### Role-specific dashboard routing

- `super_admin`: global administration context; no executive dashboard access by default.
- `pic_sekolah`: school operational dashboard for the selected active school.
- `kepsek`: school dashboard for the selected active school.
- `wakasek`, `tu`, `staf_tu`, `bendahara`, `guru`, `wali_kelas`: school dashboard or role portal according to active-school permission.
- `siswa`: student portal only after a verified student identity is resolved.
- `orang_tua`: parent portal only after at least one verified guardian-to-student relationship is resolved.
- `dinas`: district aggregate dashboard.
- `yayasan`: foundation-scoped aggregate dashboard.

## Identity model

### User credentials

- Keep one `users` record per login identity.
- Normalize email and phone for uniqueness and lookup. Phone lookup must not expose whether an account exists.
- Account status must explicitly distinguish `pending`, `active`, `suspended`, and `rejected`.
- Teacher email verification remains required before teacher access is activated.
- School/PIC onboarding remains superadmin-approved.
- Guardian registration remains immediately active, subject to verified school membership and verified student relationships.
- A public registration path can never create `super_admin`.

### School membership

Treat `school_user_roles` as the authorization membership boundary:

- `user_id`, `school_id`, `role` identify the membership.
- `is_active` and `membership_status` must both be respected during authorization.
- A user may have multiple memberships in the same school only when the role model explicitly supports it; otherwise enforce a unique active role relationship.
- Switching school must clear or invalidate cached permission decisions and must validate membership again.
- The selected school is session context, never a substitute for authorization.

### Person identity links

Add explicit nullable links rather than matching names:

- `students.user_id` for a student login identity, when a student account exists.
- `students.guardian_user_id` remains the owner boundary for guardian-managed student data.
- `teachers.user_id` for a teacher/tendik login identity, when a staff account exists.
- The same person may have school-specific memberships, but data access always checks the active school and the linked person record.

The phase must include a safe migration/backfill policy for existing rows. Name matching may be used only as an explicit admin-assisted migration tool, never as a runtime authorization fallback.

## Authorization invariants

1. Every school-scoped query uses the active school context or an explicitly authorized school ID.
2. Every portal query constrains the authenticated user to the linked student/teacher/guardian relationship.
3. No controller may fall back to `Student::first()`, `Teacher::first()`, `id > 0`, or a synthetic person when the identity cannot be resolved.
4. Missing identity produces a safe empty state with a support/admin action, not a 200 response containing another person's data.
5. `super_admin` bypasses only global administration permissions. It does not bypass executive dashboard restrictions or person ownership rules.
6. `pic_sekolah` never satisfies the global superadmin middleware.
7. Menu visibility and direct routes must use the same permission source and role context.
8. A role outside the active school cannot access that school's data by changing a request parameter.

## School selector UX

- Dedicated page for users with multiple active memberships.
- Each school card shows school name, level, city/province, and role(s) for that membership.
- Maximum three columns on desktop, one column on mobile.
- Cards are keyboard reachable, have visible focus, and submit a real school switch action.
- Header includes a school switcher after selection for eligible multi-school users.
- The selector must not appear for Dinas/Yayasan aggregate contexts unless they are entering an explicitly scoped detail view.
- Empty state explains why no school is available and directs the user to contact an administrator or complete onboarding.

## Security and recovery

- Login failures use a generic message and do not reveal whether an email or phone is registered.
- Suspended, pending, and unverified accounts receive actionable but non-sensitive messaging.
- School switch POST remains CSRF-protected and validates the membership server-side.
- Session regeneration happens after successful authentication and after context changes where appropriate.
- Add audit records for login lifecycle changes, membership activation/deactivation, school switching, role changes, and identity-link changes.
- Do not add password reset UI without a working route and delivery mechanism. Until then, use a real support path rather than a dead link.

## UI contract

The Fase 1 interface uses the existing EDUJA visual language, with an accessible and utilitarian direction:

- Preserve the current cream/ink/teal identity.
- Use clear role labels and plain Indonesian copy.
- Use one primary action per screen.
- All forms have visible labels, field-level errors, loading/disabled submit state, and recovery guidance.
- All interactive elements have focus-visible states and at least 44px target size.
- Respect reduced motion; school-switch transitions are opacity-only and non-blocking.
- No fabricated user, school, student, or dashboard data in empty states.

## Tests required before Fase 1 completion

- Login by email and normalized phone.
- Generic invalid-credential response.
- Pending, suspended, rejected, and unverified account handling.
- One-school auto-selection.
- Multi-school selector and server-side membership validation.
- Context switch invalidates previous school access.
- Role-specific redirect behavior.
- Dinas/Yayasan bypass the normal school selector and remain scope-limited.
- Student portal rejects users without a linked student identity.
- Parent portal returns only guardian-owned students and rejects cross-guardian access.
- Teacher/tendik portal resolves only the linked school person record.
- No runtime fallback to another student's or teacher's record.
- Menu and direct-route parity for each Fase 1 role.
- Empty states, inline validation, loading states, and keyboard focus behavior.

## Explicitly deferred to later phases

- Full superadmin onboarding approval UI and audit viewer.
- Teacher join request approval UX.
- Academic lifecycle and role-history workflows.
- Finance wallets, budget revisions, journals, closing snapshots, and full payment approval.
- RFID/GPS local sync infrastructure and WhatsApp gateway delivery.
- Announcement targeting/read receipts and AI provider integration.
- Alumni self-service profile completion.
