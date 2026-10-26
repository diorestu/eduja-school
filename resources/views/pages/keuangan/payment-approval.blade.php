@extends('layouts.app')
@section('content')
<x-common.page-breadcrumb pageTitle="Detail Pembayaran Siswa" label="Approval Tunggakan" />
<div class="work work-stack grid-cols-1 max-w-3xl">
    @if($canReview)<a class="work-btn w-fit" href="{{ route('finance.approvals', ['type' => 'payment']) }}">Kembali ke Approval Tunggakan</a>@endif
    @if(session('success'))<p role="status" class="work-notice">{{ session('success') }}</p>@endif
    @if($errors->any())<div role="alert" class="work-notice work-error">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div><h2 class="text-lg font-semibold">{{ $student?->name ?? 'Siswa belum terhubung' }}</h2><p class="work-muted">{{ $className }} · NIS {{ $student?->nis ?? '-' }}</p></div>
        <strong class="text-xl tabular-nums">Rp {{ number_format($payment->amount, 2, ',', '.') }}</strong>
    </div>
    <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div><dt class="work-muted">Status pembayaran</dt><dd class="font-semibold">{{ ['pending' => 'Menunggu verifikasi', 'approved' => 'Pembayaran sudah diterima', 'rejected' => 'Pembayaran belum diterima'][$payment->status] ?? $payment->status }}</dd></div>
        <div><dt class="work-muted">Tagihan</dt><dd>{{ $payment->invoice?->invoice_number ?? '-' }}</dd>@foreach($payment->invoice?->invoiceItems ?? [] as $item)<dd>{{ $item->name }}</dd>@endforeach</div>
        <div><dt class="work-muted">Tanggal pembayaran</dt><dd>{{ $payment->payment_date?->translatedFormat('d M Y') }}</dd></div>
        <div><dt class="work-muted">Metode pembayaran</dt><dd>{{ $payment->payment_method }}</dd></div>
        <div><dt class="work-muted">Rekening tujuan</dt><dd>{{ $payment->account?->name ?? '-' }}</dd><dd>{{ $payment->account?->bank_name }} {{ $payment->account?->account_number }}</dd></div>
        <div><dt class="work-muted">Diajukan oleh</dt><dd>{{ $payment->submittedBy?->name ?? $approval->requester?->name ?? '-' }}</dd></div>
    </dl>
    @if($payment->proof_path)
        <a class="work-btn w-fit" href="{{ route('payment-approvals.proof', $approval) }}">Unduh bukti pembayaran</a>
    @else
        <p class="work-muted">Bukti pembayaran belum tersedia.</p>
    @endif
    @if($approval->status !== 'pending')
        <div role="status"><p>{{ $payment->review_note }}</p><p class="work-muted">Ditinjau oleh {{ $approval->reviewer?->name ?? '-' }} · {{ $approval->reviewed_at?->translatedFormat('d M Y H:i') }}</p></div>
    @elseif($canReview)
        <div class="border-t border-gray-200 pt-4 dark:border-gray-700" x-data="{ saving: false }">
            <form method="POST" action="{{ route('approvals.reject', $approval) }}" @submit="saving = true" class="work-stack grid-cols-1">
                @csrf
                <div class="work-field"><label for="payment-review-note">Alasan penolakan (wajib jika ditolak)</label><textarea id="payment-review-note" name="note" rows="3" maxlength="1000" required>{{ old('note') }}</textarea></div>
                <div class="flex flex-wrap gap-3">
                    <button type="submit" class="work-btn" :disabled="saving">Tolak pembayaran</button>
                    <button type="submit" formaction="{{ route('approvals.approve', $approval) }}" formnovalidate class="work-btn work-btn-primary" :disabled="saving">Setujui pembayaran</button>
                </div>
            </form>
        </div>
    @endif
</div>
@endsection
