<?php

namespace App\Services;

use App\Models\ApprovalRequest;
use App\Models\PaymentSubmission;
use App\Models\User;

class PaymentApprovalAccess
{
    public function canReview(User $user): bool
    {
        return app(PermissionService::class)->hasAnyRole($user, ['bendahara', 'teller', 'kepsek', 'super_admin']);
    }

    public function validPayment(ApprovalRequest $approval): ?PaymentSubmission
    {
        if ($approval->type !== 'payment' || $approval->approvable_type !== PaymentSubmission::class) {
            return null;
        }
        $payment = $approval->approvable;
        if (! $payment instanceof PaymentSubmission || (int) $payment->school_id !== (int) $approval->school_id) {
            return null;
        }
        if ($payment->student_id && (! $payment->student || (int) $payment->student->school_id !== (int) $approval->school_id)) {
            return null;
        }
        if ($payment->invoice_id && (! $payment->invoice || (int) $payment->invoice->student?->school_id !== (int) $approval->school_id
            || (int) ($payment->invoice->school_id ?? $payment->invoice->student?->school_id) !== (int) $approval->school_id
            || ($payment->student_id && (int) $payment->invoice->student_id !== (int) $payment->student_id))) {
            return null;
        }
        if ($payment->account_id && (! $payment->account || (int) $payment->account->school_id !== (int) $approval->school_id)) {
            return null;
        }

        return $payment;
    }

    public function isRecipient(PaymentSubmission $payment, User $user): bool
    {
        $student = $payment->student ?? $payment->invoice?->student;

        return $student && (int) $student->school_id === (int) $payment->school_id
            && in_array($user->id, [$student->user_id, $student->guardian_user_id], true);
    }
}
