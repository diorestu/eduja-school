# EDUJA Fase 4 Attendance and Approval Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the PDF's school-scoped attendance and absence-approval core with safe identity ownership, deterministic adapter boundaries, and complete UI states.

**Architecture:** Reuse the existing `StudentAttendance`, `TeacherAttendance`, `AttendanceRequest`, `ApprovalRequest`, `SchoolContext`, and `PermissionService` boundaries. Add typed services for attendance commands, absence requests, approval policy, notification events, RFID resolution, and GPS validation so external delivery is isolated from core business rules.

**Tech Stack:** Laravel, Eloquent migrations/models, Blade, Alpine.js, Pest feature tests, existing Tailwind/Vite assets.

**Spec:** `docs/superpowers/specs/2026-09-14-phase-4-attendance-approval-design.md`

## Global Constraints

- Every attendance and request query is constrained by the active school and the linked person relationship.
- No hard-coded coordinates, synthetic identities, or implied WhatsApp delivery are permitted.
- Attendance and approval state changes are atomic and auditable.
- Pending requests do not update final attendance until approved.
- Existing finance expense approval remains separate from attendance approval.
- All UI uses visible labels, keyboard focus, 44px targets, actionable empty/error states, and reduced-motion-safe transitions.

### Task 1: Attendance and request schema

**Files:**
- Create: `database/migrations/2026_09_14_000001_add_phase4_attendance_fields.php`
- Modify: `app/Models/StudentAttendance.php`, `app/Models/TeacherAttendance.php`, `app/Models/AttendanceRequest.php`, `app/Models/ApprovalRequest.php`
- Create: `app/Models/AttendanceApprovalPolicy.php` only if a persisted policy is required by the existing schema
- Test: `tests/Feature/PhaseFourAttendanceSchemaTest.php`

- [ ] Write failing schema and relationship tests for source/location/photo metadata, dispensasi/cuti statuses, request ownership, approval reviewer metadata, and school/person relationships.
- [ ] Run `php artisan test tests/Feature/PhaseFourAttendanceSchemaTest.php` and confirm missing schema failures.
- [ ] Add only the missing nullable/indexed fields, preserving existing rows and unique daily constraints.
- [ ] Add model casts, relationships, status constants, and typed accessors without changing existing route contracts.
- [ ] Run the focused schema suite and `php artisan migrate:fresh --force`.

### Task 2: Attendance command service and school/person scope

**Files:**
- Create: `app/Services/AttendanceCommandService.php`
- Modify: `app/Services/AttendanceService.php`, `app/Http/Controllers/AttendanceController.php`
- Test: `tests/Feature/PhaseFourAttendanceCommandTest.php`

**Interfaces:**
- `AttendanceCommandService::recordStudent(int $schoolId, int $studentId, array $attributes): StudentAttendance`
- `AttendanceCommandService::recordTeacher(int $schoolId, int $teacherId, array $attributes): TeacherAttendance`
- `AttendanceService::summary(int $schoolId, array $filters = []): array`

- [ ] Write failing tests for school/person scope, duplicate daily records, valid statuses, explicit source preservation, and rejection of inactive or cross-school people.
- [ ] Run the focused command suite and verify expected failures.
- [ ] Implement transactional upsert commands with school and identity predicates; use configured source metadata and never invent location values.
- [ ] Refactor existing manual attendance controller writes through the service and validate submitted class/person IDs against the active school.
- [ ] Implement summary filters that separate students, guru, and tendik and return empty series safely.
- [ ] Re-run focused command tests and existing attendance/foundation tests.

### Task 3: Absence request service and ownership

**Files:**
- Create: `app/Services/AbsenceRequestService.php`
- Create/Modify: `app/Http/Controllers/AttendanceRequestController.php`, `routes/web.php`
- Modify: `app/Models/AttendanceRequest.php`
- Test: `tests/Feature/PhaseFourAbsenceRequestTest.php`

**Interfaces:**
- `AbsenceRequestService::submitStudent(int $schoolId, User $requester, array $attributes): AttendanceRequest`
- `AbsenceRequestService::submitTeacher(int $schoolId, User $requester, array $attributes): AttendanceRequest`
- `AbsenceRequestService::validateDates(int $schoolId, string $subjectType, int $subjectId, string $start, string $end): void`

- [ ] Write failing tests for linked student/guardian ownership, linked teacher/tendik identity, non-past dates, date ordering, overlap prevention, required fields, and pending approval creation.
- [ ] Run the focused request suite and confirm failures before implementation.
- [ ] Implement request submission with explicit subject type/id, school scope, optional document metadata, and pending `ApprovalRequest` creation.
- [ ] Add authenticated routes and role permission middleware for student/guardian and teacher/tendik submissions.
- [ ] Ensure rejected requests remain resubmittable and do not change attendance.
- [ ] Re-run focused request tests and portal security tests.

### Task 4: Approval policy and atomic transitions

**Files:**
- Create: `app/Services/AttendanceApprovalService.php`
- Modify: `app/Http/Controllers/ApprovalController.php`, `app/Services/FinanceService.php` only if shared approval dispatch needs a narrow adapter
- Modify: `routes/web.php`, `app/Helpers/MenuHelper.php`
- Test: `tests/Feature/PhaseFourApprovalPolicyTest.php`

**Interfaces:**
- `AttendanceApprovalService::approve(ApprovalRequest $approval, User $reviewer, ?string $note = null): void`
- `AttendanceApprovalService::reject(ApprovalRequest $approval, User $reviewer, ?string $note = null): void`
- `AttendanceApprovalService::canReview(ApprovalRequest $approval, User $reviewer): bool`

- [ ] Write failing tests for approver matrix: student requests to wali kelas/wakasek/TU, teacher/tendik leave to kepsek/wakasek/TU, self-approval denial, cross-school rejection, duplicate decision handling, and atomic rollback.
- [ ] Run focused approval tests and observe current generic approval behavior failures.
- [ ] Implement policy lookup, approver checks, and atomic request plus attendance updates. Keep finance approval behavior compatible.
- [ ] Update approval queue queries to include attendance requests with requester/subject summary and use only eligible roles.
- [ ] Re-run focused approval tests and existing finance approval tests.

### Task 5: Notification, RFID, and GPS adapter contracts

**Files:**
- Create: `app/Contracts/AttendanceNotifier.php`, `app/Contracts/RfidAttendanceAdapter.php`, `app/Contracts/GpsAttendancePolicy.php`
- Create: `app/Services/LogAttendanceNotifier.php`, `app/Services/DatabaseRfidAttendanceAdapter.php`, `app/Services/ConfiguredGpsAttendancePolicy.php`
- Modify: `app/Services/NotificationService.php`, service provider bindings if needed
- Test: `tests/Feature/PhaseFourAdapterContractTest.php`

- [ ] Write failing tests for typed notification events, unknown RFID UID, inactive/cross-school UID, missing GPS configuration, outside-radius coordinates, and valid metadata normalization.
- [ ] Run focused adapter tests and confirm missing contracts/bindings.
- [ ] Implement deterministic adapters. Default notification behavior logs an event without claiming WhatsApp/push delivery; GPS uses configured school coordinates/radius only.
- [ ] Expose adapter results to attendance commands without coupling the core to hardware, cloud API, or provider credentials.
- [ ] Re-run adapter tests and confirm no external delivery side effect is claimed.

### Task 6: Attendance dashboard, history, and request UI

**Files:**
- Modify: `app/Http/Controllers/OperationsFoundationController.php`, `app/Http/Controllers/AttendanceController.php`
- Create/Modify: `resources/views/pages/kesiswaan/presensi/*`, `resources/views/pages/foundation/attendance-requests.blade.php`
- Modify: `resources/views/components/header/notification-dropdown.blade.php` only for real pending-count/event links
- Test: `tests/Feature/PhaseFourUiContractTest.php`

- [ ] Write failing view assertions for school/date/class context, student/guru/tendik labels, status text, approval detail, empty state, validation errors, and request success/pending/rejected states.
- [ ] Run focused UI contract tests and confirm missing state/content failures.
- [ ] Implement dashboard summaries, filters for day/week/month/semester, attendance detail, and approval queue UI using real scoped data.
- [ ] Add progressive request forms for sakit/izin/dispensasi/cuti and clear document upload state without fake records.
- [ ] Add real notification links only when a pending request exists; keep empty notification state explanatory.
- [ ] Verify responsive layouts at 320, 375, 414, and 768px and keyboard focus behavior.

### Task 7: End-to-end verification and scope report

**Files:**
- Modify: only files required by failing verification
- Test: full `tests/Feature` suite and route/build checks

- [ ] Run `php artisan view:cache`.
- [ ] Run `php artisan route:list` and verify attendance/request/approval routes and middleware.
- [ ] Run focused Phase 4 suites, the full feature suite, `npm run build`, and `git diff --check`.
- [ ] Exercise manual attendance, student request, teacher leave request, approval, rejection, summary filters, and empty states in a browser if available.
- [ ] Record production boundaries explicitly: RFID hardware, GPS configuration, notification providers, cloud sync, and PDF/Excel exports are not verified by local tests.
