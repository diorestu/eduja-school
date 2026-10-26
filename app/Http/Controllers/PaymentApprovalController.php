<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Services\PaymentApprovalAccess;
use App\Services\SchoolContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PaymentApprovalController extends Controller
{
    private function payment(Request $request, ApprovalRequest $approval, SchoolContext $context, PaymentApprovalAccess $access)
    {
        abort_unless((int) $approval->school_id === $context->activeSchoolIdFor(), 403);
        $payment = $access->validPayment($approval);
        abort_unless($payment && ($access->canReview($request->user()) || $access->isRecipient($payment, $request->user())), 403);

        return $payment;
    }

    public function show(Request $request, ApprovalRequest $approval, SchoolContext $context, PaymentApprovalAccess $access)
    {
        $payment = $this->payment($request, $approval, $context, $access);
        $student = $payment->student ?? $payment->invoice?->student;
        $student?->load('schoolClasses.academicYear');
        $classes = $student?->schoolClasses->where('school_id', $approval->school_id);
        $className = $classes?->first(fn ($class) => $class->academicYear?->is_active)?->name ?? $classes?->first()?->name ?? '-';

        return response()->view('pages.keuangan.payment-approval', [
            'title' => 'Detail Pembayaran Siswa', 'approval' => $approval, 'payment' => $payment,
            'student' => $student, 'className' => $className, 'canReview' => $access->canReview($request->user()),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function proof(Request $request, ApprovalRequest $approval, SchoolContext $context, PaymentApprovalAccess $access)
    {
        $payment = $this->payment($request, $approval, $context, $access);
        $path = $payment->proof_path ?? '';
        abort_unless(str_starts_with($path, 'payment-proofs/'.$approval->school_id.'/') && ! str_contains($path, '..'), 404);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);

        return $disk->download($path, 'bukti-pembayaran-'.$payment->id.'.'.pathinfo($path, PATHINFO_EXTENSION), ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
