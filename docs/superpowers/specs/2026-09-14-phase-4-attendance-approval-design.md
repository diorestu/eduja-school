# EDUJA Fase 4: Attendance and Approval Core

## Status

Draft for user review. No implementation changes are included in this specification.

## Source and scope

This phase follows the attendance and approval flows in `/Users/user/Downloads/Flow dan ERD belum AI (1).pdf`, pages 4, 8, 36, 46, 48-49, and 60-66.

The phase builds the application core first: school-scoped attendance, absence requests, approval transitions, summaries, history, and adapter boundaries for RFID/GPS/notifications. Hardware and external gateway delivery remain integration work after this phase.

## Attendance model

### Student attendance

- One attendance record is scoped by `school_id`, `student_id`, `school_class_id`, and `attendance_date`.
- Supported statuses are `H` (hadir), `S` (sakit), `I` (izin), `D` (dispensasi), and `A` (alpha).
- Status changes from an approved absence request must be traceable to the request and reviewer.
- Student attendance reads require an active school and a student/class relationship in that school.

### Teacher and tendik attendance

- One attendance record is scoped by `school_id`, `teacher_id`, and `attendance_date`.
- Supported statuses are `H`, `S`, `I`, `C` (cuti), `A`, and `DL` (dinas luar), subject to the existing product status vocabulary.
- Teacher/tendik records must be active and belong to the selected school before attendance can be recorded.
- The same table may serve guru and tendik, but summaries must distinguish them by `role_type`.

### Sources

The core accepts `manual`, `rfid`, and `gps` as explicit sources. Source metadata is stored with the attendance record. The core does not claim that an RFID reader, GPS radius, photo capture, local queue, or cloud sync exists until the corresponding adapter is connected and verified.

## Absence request lifecycle

### Student request

1. A linked student or guardian submits an absence request.
2. The request validates student ownership, active school, non-past dates, date order, and overlap with existing requests.
3. The request is stored as `pending` with optional supporting document metadata.
4. The request enters the approval queue for a configured school approver: wali kelas, wakasek, or TU.
5. Approval changes the request and relevant attendance status atomically; rejection leaves attendance unchanged.
6. The requester receives an application notification event.

### Guru/tendik request

1. A linked teacher/tendik submits sakit or cuti request.
2. The request validates active employment, dates, overlap, and required leave fields.
3. The request is stored as `pending`.
4. The request enters the approval queue for kepala sekolah, wakasek, or TU according to the school's configured approval policy.
5. Approval changes the request and attendance record atomically; rejection leaves attendance unchanged and permits resubmission.

## Approval rules

- Approval requests are school-scoped and can only be read or transitioned in the active school.
- Every transition checks request status, approver role, approvable ownership, and active school before mutation.
- Approval and approvable updates happen in one database transaction.
- Duplicate transitions return a clear handled-state response and do not mutate data.
- A requester cannot approve their own request unless an explicit policy allows it; default policy denies self-approval.
- Expense approval remains governed by the existing finance rule and is not broadened by attendance approvers.
- Approval history retains requester, reviewer, decision, note, and timestamps.

## Summaries and history

- Today summary reports student hadir, student alpha, guru hadir, tendik hadir, and pending requests by active school.
- History supports day, week, month, and semester windows.
- Detail includes date, clock-in/out, status, source, and location metadata only when actually recorded.
- Admin attendance views support filters for person type, class, department, date, and status.
- No summary or history query may use data from another school or another linked person.

## Adapter boundaries

- `NotificationService` emits a typed notification event with recipient, school, request, and message metadata. The default adapter may log the event but must not imply WhatsApp delivery.
- `RfidAttendanceAdapter` accepts a UID and school context, resolves a local linked identity, and returns a normalized attendance command or a rejection reason.
- `GpsAttendancePolicy` validates coordinates, timestamp, and optional photo metadata against configured school coordinates/radius. No hard-coded coordinates are permitted.
- Queue retry and cloud sync are deferred until the adapter contract has deterministic tests.

## UI and UX contract

- Attendance entry uses a clear school/date/class context at the top.
- Status controls use text labels and accessible state, not color alone.
- Request forms reveal date/document fields progressively based on request type.
- Approval detail shows requester, subject, dates, reason, document status, and the exact decision actions.
- Pending, approved, rejected, empty, loading, and error states are distinct and actionable.
- All controls have visible labels, keyboard focus, at least 44px target size, and reduced-motion-safe transitions.
- Existing EDUJA cream/ink/teal identity is preserved; no fabricated names, locations, attendance values, or delivery claims are shown.

## Required tests

- School and person scope for student and teacher/tendik attendance.
- Duplicate daily attendance prevention and source preservation.
- Student/guardian request ownership and date/overlap validation.
- Teacher/tendik leave validation and active employment requirement.
- Approval role matrix, self-approval denial, duplicate decision handling, and atomic rollback.
- Approved request updates attendance; rejected request does not.
- Summary/history filters and guru/tendik separation.
- RFID/GPS adapter contracts reject unknown identities, invalid scope, and missing configuration.
- Notification events are emitted without claiming external delivery.
- UI empty/loading/error/approval states and school-scope labels.

## Deferred

- Physical RFID reader installation and local MySQL queue deployment.
- Cloud API scheduler and retry worker deployment.
- WhatsApp Gateway and push/email provider credentials/delivery.
- Production GPS geofence source and photo storage policy.
- PDF/Excel exports for attendance reports.
