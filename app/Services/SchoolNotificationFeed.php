<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\ApprovalRequest;
use App\Models\User;
use Illuminate\Support\Collection;

class SchoolNotificationFeed
{
    public function forUser(User $user): Collection
    {
        $context = app(SchoolContext::class);
        $schoolId = $context->activeSchoolId();
        if (! $schoolId || ! $context->availableSchools($user)->contains('id', $schoolId)
            || $user->hasRole(['dinas', 'yayasan'])) {
            return collect();
        }

        $items = collect();
        foreach (\App\Models\SchoolNotification::where('school_id', $schoolId)->where('user_id', $user->id)
            ->whereNull('read_at')->latest()->limit(10)->get() as $notification) {
            $items->push(['title' => $notification->title, 'url' => $notification->url,
                'notification_id' => $notification->id, 'date' => $notification->created_at]);
        }
        if ($user->hasRole(['kepsek', 'super_admin'])) {
            foreach (ApprovalRequest::where('school_id', $schoolId)->where('status', 'pending')
                ->whereIn('type', ['budget', 'budget_plan', 'budget_revision', 'bos_income_revision', 'grant_income_revision', 'other_income_revision'])
                ->with('approvable')->latest()->get() as $approval) {
                if ($approval->approvable && (int) $approval->approvable->school_id === $schoolId) {
                    $items->push(['title' => 'Anggaran atau revisi keuangan menunggu persetujuan',
                        'url' => route('finance.approvals', ['approval' => $approval->id]), 'date' => $approval->created_at]);
                }
            }
            foreach (ApprovalRequest::where('school_id', $schoolId)->where('type', 'expense')->where('status', 'pending')
                ->where('approvable_type', \App\Models\Expense::class)->with('approvable')->latest()->get() as $approval) {
                $expense = $approval->approvable;
                if ($expense && (int) $expense->school_id === $schoolId && $expense->status === 'pending' && ! $expense->posted_at) {
                    $items->push(['title' => 'Pengeluaran '.($expense->transaction_number ?? '#'.$expense->id).' menunggu persetujuan',
                        'url' => route('finance.approvals', ['approval' => $approval->id]), 'date' => $approval->created_at]);
                }
            }
        }
        $paymentAccess = app(PaymentApprovalAccess::class);
        $canReviewPayments = $paymentAccess->canReview($user);
        foreach (ApprovalRequest::where('school_id', $schoolId)->where('type', 'payment')
            ->where('approvable_type', \App\Models\PaymentSubmission::class)
            ->with(['approvable', 'reviewer'])->latest()->get() as $approval) {
            $payment = $paymentAccess->validPayment($approval);
            if (! $payment) {
                continue;
            }
            $name = ($payment->student ?? $payment->invoice?->student)?->name ?? 'Siswa';
            if ($canReviewPayments && $approval->status === 'pending' && $payment->status === 'pending') {
                $items->push(['title' => 'Pembayaran '.$name.' menunggu verifikasi', 'url' => route('payment-approvals.show', $approval), 'date' => $approval->created_at]);
            }
            if (in_array($approval->status, ['approved', 'rejected'], true) && $payment->status === $approval->status && $paymentAccess->isRecipient($payment, $user)) {
                $items->push(['title' => 'Pembayaran '.$name.($approval->status === 'approved' ? ' sudah diterima' : ' belum diterima'),
                    'url' => route('payment-approvals.show', $approval), 'date' => $approval->reviewed_at ?? $approval->updated_at]);
            }
        }
        foreach (ApprovalRequest::where('school_id', $schoolId)->where('type', 'attendance')->where('status', 'pending')->latest()->get() as $approval) {
            if (app(AttendanceApprovalService::class)->canReview($approval, $user)) {
                $items->push(['title' => 'Permohonan izin menunggu keputusan', 'url' => route('attendance.requests', ['status' => 'pending']), 'date' => $approval->created_at]);
            }
        }
        foreach (Announcement::where('school_id', $schoolId)->whereNotNull('published_at')->where('published_at', '<=', now())
            ->whereDoesntHave('reads', fn ($q) => $q->where('user_id', $user->id)->whereNotNull('read_at'))->latest()->get() as $announcement) {
            if (app(AnnouncementAudience::class)->allows($announcement, $user)) {
                $items->push(['title' => $announcement->title, 'url' => route('announcements.show', $announcement), 'date' => $announcement->published_at]);
            }
        }

        return $items->sortByDesc('date')->take(10)->values();
    }
}
