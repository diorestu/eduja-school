<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Models\BudgetPlan;
use App\Models\BudgetPlanRevision;
use App\Models\Expense;
use App\Models\PaymentSubmission;
use App\Services\AttendanceApprovalService;
use App\Services\FinanceService;
use App\Services\SchoolContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use LogicException;

class ApprovalController extends Controller
{
    private const APPROVABLE_TYPES = [
        BudgetPlan::class,
        BudgetPlanRevision::class,
        Expense::class,
        PaymentSubmission::class,
    ];

    public function approve(
        Request $request,
        ApprovalRequest $approval,
        SchoolContext $schoolContext,
        AttendanceApprovalService $attendanceApprovals,
    ): RedirectResponse {
        return $this->transition($request, $approval, $schoolContext, $attendanceApprovals, 'approved', 'Approval berhasil disetujui.');
    }

    public function reject(
        Request $request,
        ApprovalRequest $approval,
        SchoolContext $schoolContext,
        AttendanceApprovalService $attendanceApprovals,
    ): RedirectResponse {
        return $this->transition($request, $approval, $schoolContext, $attendanceApprovals, 'rejected', 'Approval berhasil ditolak.');
    }

    private function transition(
        Request $request,
        ApprovalRequest $approval,
        SchoolContext $schoolContext,
        AttendanceApprovalService $attendanceApprovals,
        string $status,
        string $message,
    ): RedirectResponse {
        if ($approval->type === 'attendance') {
            return $this->transitionAttendance($request, $approval, $attendanceApprovals, $status, $message);
        }

        abort_unless($approval->school_id === $schoolContext->activeSchoolId(), 403);
        abort_unless($approval->status === 'pending', 422, 'Approval ini sudah diproses.');

        if ($approval->type === 'expense') {
            abort_unless($request->user()->hasRole(['kepsek', 'super_admin']), 403);
        }

        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $approvable = $this->approvable($approval);

        if ($approvable) {
            try {
                app(FinanceService::class)->assertApprovalScope($approval->school_id, $approvable);
            } catch (\InvalidArgumentException) {
                abort(403);
            }
        }

        DB::transaction(function () use ($approval, $approvable, $status, $validated, $request): void {
            $approval->update([
                'status' => $status,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'note' => $validated['note'] ?? $approval->note,
            ]);

            if ($approvable && Schema::hasColumn($approvable->getTable(), 'status')) {
                $payload = ['status' => $status];
                if (Schema::hasColumn($approvable->getTable(), 'reviewed_by')) {
                    $payload['reviewed_by'] = $request->user()->id;
                }
                if (Schema::hasColumn($approvable->getTable(), 'reviewed_at')) {
                    $payload['reviewed_at'] = now();
                }
                $approvable->forceFill($payload)->save();
            }

            if ($approvable) {
                app(FinanceService::class)->applyApproval($approval, $approvable, $status, $request->user()->id);
            }
        });

        return back()->with('success', $message);
    }

    private function transitionAttendance(
        Request $request,
        ApprovalRequest $approval,
        AttendanceApprovalService $attendanceApprovals,
        string $status,
        string $message,
    ): RedirectResponse {
        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            if ($status === 'approved') {
                $attendanceApprovals->approve($approval, $request->user(), $validated['note'] ?? null);
            } else {
                $attendanceApprovals->reject($approval, $request->user(), $validated['note'] ?? null);
            }
        } catch (AuthorizationException $exception) {
            abort(403, $exception->getMessage());
        } catch (LogicException $exception) {
            abort(422, $exception->getMessage());
        } catch (InvalidArgumentException $exception) {
            abort(422, $exception->getMessage());
        }

        return back()->with('success', $message);
    }

    private function approvable(ApprovalRequest $approval)
    {
        if (! in_array($approval->approvable_type, self::APPROVABLE_TYPES, true) || ! $approval->approvable_id) {
            return null;
        }

        $model = new $approval->approvable_type;

        return $model->newQuery()->find($approval->approvable_id);
    }
}
