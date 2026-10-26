@extends('layouts.app')

@section('content')
@php
    $approvalColumns = [
        ['key' => 'type_label', 'label' => 'Jenis Pengajuan', 'bold' => true],
        ['key' => 'requester_name', 'label' => 'Pemohon & Tanggal', 'bold' => true, 'subKey' => 'created_at'],
        ['key' => 'note', 'label' => 'Catatan / Deskripsi'],
        ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
        ['key' => 'aksi', 'label' => 'Aksi', 'sortable' => false, 'onlyEdit' => true],
    ];
    if ($paymentsOnly) {
        array_splice($approvalColumns, 1, 0, [
            ['key' => 'student_name', 'label' => 'Siswa', 'bold' => true],
            ['key' => 'payment_amount', 'label' => 'Nominal Pembayaran'],
        ]);
    }

    $typeNames = [
        'budget' => 'Rencana Anggaran',
        'budget_revision' => 'Revisi Anggaran',
        'bos_income_revision' => 'Revisi Pemasukan BOS',
        'grant_income_revision' => 'Revisi Pemasukan Hibah',
        'other_income_revision' => 'Revisi Pendapatan Lainnya',
        'expense' => 'Pengeluaran Belanja',
        'payment' => 'Pembayaran Siswa / Transfer',
        'transfer' => 'Transfer Antar Rekening',
        'attendance' => 'Izin / Cuti',
    ];

    $statusLabels = [
        'pending' => 'Pending',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak',
    ];

    $isKepsek = auth()->user()?->hasRole(['kepsek', 'super_admin']) ?? false;

    $approvalRows = $approvals->map(function ($app) use ($typeNames, $statusLabels, $isKepsek) {
        $isBudget = in_array($app->type, ['budget', 'budget_revision'], true);
        $isExpense = $app->type === 'expense';
        $requiresKepsek = $isBudget || $isExpense || in_array($app->type, ['bos_income_revision', 'grant_income_revision', 'other_income_revision'], true);
        $canApprove = ! $requiresKepsek || $isKepsek;
        $payment = app(\App\Services\PaymentApprovalAccess::class)->validPayment($app);
        if ($app->type === 'payment') {
            $canApprove = $payment && app(\App\Services\PaymentApprovalAccess::class)->canReview(auth()->user());
        }

        return [
            'id' => $app->id,
            'student_name' => ($payment?->student ?? $payment?->invoice?->student)?->name ?? '-',
            'payment_amount' => $payment ? 'Rp '.number_format($payment->amount, 2, ',', '.') : '-',
            'type' => $app->type,
            'is_budget' => $isBudget,
            'requires_kepsek' => $requiresKepsek,
            'can_approve' => $canApprove,
            'type_label' => $typeNames[$app->type] ?? ucfirst($app->type),
            'requester_name' => $app->requester?->name ?? 'Sistem',
            'created_at' => $app->created_at ? $app->created_at->translatedFormat('d M Y H:i') : '-',
            'note' => $app->note ?: 'Tidak ada catatan tambahan.',
            'status' => $statusLabels[$app->status] ?? ucfirst($app->status),
            'status_raw' => $app->status ?? 'pending',
            'reviewer_name' => $app->reviewer?->name ?? '-',
            'reviewed_at' => $app->reviewed_at ? $app->reviewed_at->translatedFormat('d M Y H:i') : '-',
            'approve_url' => route('approvals.approve', $app),
            'reject_url' => route('approvals.reject', $app),
            'payment_url' => $app->type === 'payment' && $app->approvable_type === \App\Models\PaymentSubmission::class ? route('payment-approvals.show', $app) : null,
            'expense' => $app->approvable instanceof \App\Models\Expense && (int) $app->approvable->school_id === (int) $app->school_id ? [
                'number' => $app->approvable->transaction_number ?? 'Pengeluaran #'.$app->approvable->id,
                'description' => $app->approvable->expense_name,
                'amount' => $app->approvable->amount,
                'recipient' => $app->approvable->recipient_name ?? '-',
                'method' => $app->approvable->payment_method,
                'account' => $app->approvable->account?->name ?? '-',
                'wallet' => $app->approvable->virtualWallet?->name ?? '-',
                'proof_url' => $app->approvable->proof_path ? route('bos.belanja.proof', $app->approvable) : null,
            ] : null,
            'bos_revision_url' => $app->type === 'bos_income_revision' && $app->approvable ? route('finance.bos-income.show', $app->approvable->finance_income_id) : null,
            'bos_revision' => $app->type === 'bos_income_revision' && $app->approvable ? [
                'old' => collect($app->approvable->old_data)->only(['amount', 'source_funding', 'bos_year', 'received_date'])->all(),
                'new' => collect($app->approvable->new_data)->only(['amount', 'source_funding', 'bos_year', 'received_date'])->all(),
            ] : null,
            'only_edit' => true,
            'grant_revision_url' => $app->type === 'grant_income_revision' && $app->approvable ? route('finance.grant-income.show', $app->approvable->finance_income_id) : null,
            'other_revision_url' => $app->type === 'other_income_revision' && $app->approvable ? route('finance.other-income.show', $app->approvable->finance_income_id) : null,
            'other_revision' => $app->type === 'other_income_revision' && $app->approvable ? [
                'old' => collect($app->approvable->old_data)->only(['amount', 'source_funding', 'payment_method', 'received_date'])->all(),
                'new' => collect($app->approvable->new_data)->only(['amount', 'source_funding', 'payment_method', 'received_date'])->all(),
            ] : null,
            'grant_revision' => $app->type === 'grant_income_revision' && $app->approvable ? [
                'old' => collect($app->approvable->old_data)->only(['amount', 'donor_name', 'payment_method', 'received_date'])->all(),
                'new' => collect($app->approvable->new_data)->only(['amount', 'donor_name', 'payment_method', 'received_date'])->all(),
            ] : null,
        ];
    });
@endphp

<x-common.page-breadcrumb :pageTitle="$paymentsOnly ? 'Approval Tunggakan' : 'Antrian Approval Keuangan'" label="Approval" />

<div class="work work-stack"
    x-data="{
        activeApproval: null,
        openDetail(row) {
            this.activeApproval = row;
            this.$nextTick(() => {
                this.$refs.approvalModal.showModal();
            });
        }
    }"
    x-init="$nextTick(() => { const row = @js($approvalRows->firstWhere('id', (int) request('approval'))); if (row) openDetail(row); })"
    @table-edit="openDetail($event.detail)">

    @if(session('success'))
        <div role="status" class="work-notice">{{ session('success') }}</div>
    @endif
    @if($errors->any())<div role="alert" class="work-notice work-error">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    @if(session('error'))
        <div role="status" class="work-notice work-error">{{ session('error') }}</div>
    @endif

    {{-- METRICS COUNTER --}}
    @if(count($metrics))
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4 mb-2">
            @foreach($metrics as $metric)
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-xs dark:border-gray-800 dark:bg-gray-900">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $metric['label'] }}</p>
                    <p class="mt-1 text-xl font-bold tracking-tight tabular-nums text-gray-900 dark:text-white">{{ $metric['value'] }}</p>
                </div>
            @endforeach
        </div>
    @endif

    <div class="work-head mb-0">
        <p class="work-muted">{{ $paymentsOnly ? 'Tinjau bukti pembayaran siswa sebelum menyetujui atau menolak pengajuan.' : 'Antrian verifikasi dan persetujuan pengeluaran dana, revisi anggaran belanja, dan transaksi keuangan sekolah.' }}</p>
    </div>

    <x-common.data-table
        :rows="$approvalRows"
        :columns="$approvalColumns"
        caption="Daftar Pengajuan Approval"
        search-label="Cari pengajuan..."
        row-label="pengajuan"
        :subtitle="$paymentsOnly ? 'Pengajuan pembayaran siswa dan hasil verifikasinya' : 'Antrian verifikasi persetujuan transaksi dan anggaran'"
        :show-actions="false"
        :show-avatar="false"
        :exportable="false"
        empty-message="Belum ada antrian approval."
        :empty-hint="$paymentsOnly ? 'Pengajuan pembayaran siswa akan muncul di sini.' : 'Pengajuan belanja atau revisi anggaran yang membutuhkan persetujuan akan muncul di sini.'"
    />

    {{-- MODAL DETAIL & AKSI APPROVAL --}}
    <dialog x-ref="approvalModal" class="account-dialog work" style="max-width: 32rem; width: 100%;" aria-labelledby="approval-modal-title" aria-describedby="approval-modal-help"
        @click="const bounds = $el.getBoundingClientRect(); if ($event.clientX < bounds.left || $event.clientX > bounds.right || $event.clientY < bounds.top || $event.clientY > bounds.bottom) $el.close()">
        <div class="account-dialog-head">
            <div class="min-w-0">
                <h2 id="approval-modal-title" class="text-lg font-semibold text-gray-900 dark:text-white">Detail Pengajuan Approval</h2>
                <p id="approval-modal-help" class="work-muted text-xs mt-0.5">Tinjau data pengajuan sebelum memberikan keputusan.</p>
            </div>
            <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors p-1 rounded-md" @click="$refs.approvalModal.close()" aria-label="Tutup dialog">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="p-4 bg-gray-50 dark:bg-gray-800/60 rounded-xl border border-gray-200 dark:border-gray-700/80 text-xs space-y-2.5 mb-5">
            @if($errors->any())<div role="alert" class="work-notice work-error">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
            <template x-if="activeApproval?.expense">
                <div class="space-y-2 text-sm">
                    <p class="font-semibold" x-text="activeApproval.expense.number"></p>
                    <p x-text="activeApproval.expense.description"></p>
                    <p x-text="'Nominal: Rp ' + Number(activeApproval.expense.amount).toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></p>
                    <p x-text="'Penerima: ' + activeApproval.expense.recipient"></p>
                    <p x-text="'Metode: ' + activeApproval.expense.method"></p>
                    <p x-text="'Kas/rekening: ' + activeApproval.expense.account"></p>
                    <p x-text="'Dompet sumber: ' + activeApproval.expense.wallet"></p>
                    <template x-if="activeApproval.expense.proof_url"><a class="work-btn" :href="activeApproval.expense.proof_url">Unduh bukti transaksi</a></template>
                </div>
            </template>
            <template x-if="activeApproval?.payment_url"><a class="work-btn work-btn-primary" :href="activeApproval.payment_url">Review pembayaran dan bukti</a></template>
            <div class="flex justify-between">
                <span class="text-gray-500 dark:text-gray-400">Jenis:</span>
                <span class="font-semibold text-gray-900 dark:text-white" x-text="activeApproval?.type_label"></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-500 dark:text-gray-400">Diajukan Oleh:</span>
                <span class="font-medium text-gray-900 dark:text-white" x-text="activeApproval?.requester_name"></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-500 dark:text-gray-400">Tanggal Pengajuan:</span>
                <span class="font-medium text-gray-900 dark:text-white" x-text="activeApproval?.created_at"></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-500 dark:text-gray-400">Status Saat Ini:</span>
                <span class="font-bold uppercase tracking-wider" :class="{
                    'text-amber-600 dark:text-amber-400': activeApproval?.status_raw === 'pending',
                    'text-emerald-600 dark:text-emerald-400': activeApproval?.status_raw === 'approved',
                    'text-rose-600 dark:text-rose-400': activeApproval?.status_raw === 'rejected',
                }" x-text="activeApproval?.status"></span>
            </div>
            <div class="border-t border-gray-200 dark:border-gray-700 pt-2">
                <span class="text-gray-500 dark:text-gray-400 block mb-1">Catatan / Alasan:</span>
                <p class="text-gray-800 dark:text-gray-200 bg-white dark:bg-gray-900 p-2.5 rounded-lg border border-gray-200 dark:border-gray-700/60" x-text="activeApproval?.note"></p>
            </div>
            <template x-if="activeApproval?.bos_revision">
                <div class="border-t border-gray-200 dark:border-gray-700 pt-2 space-y-2">
                    <p x-text="'Nominal: Rp ' + Number(activeApproval.bos_revision.old.amount).toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' menjadi Rp ' + Number(activeApproval.bos_revision.new.amount).toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></p>
                    <p x-text="'Sumber: ' + activeApproval.bos_revision.old.source_funding + ' menjadi ' + activeApproval.bos_revision.new.source_funding"></p>
                    <p x-text="'Tahun: ' + (activeApproval.bos_revision.old.bos_year || '-') + ' menjadi ' + activeApproval.bos_revision.new.bos_year"></p>
                    <p x-text="'Tanggal: ' + activeApproval.bos_revision.old.received_date + ' menjadi ' + activeApproval.bos_revision.new.received_date"></p>
                    <a class="work-btn" :href="activeApproval.bos_revision_url">Lihat jejak revisi dan SP2D</a>
                </div>
            </template>
            <template x-if="activeApproval?.grant_revision">
                <div class="work-stack grid-cols-1 text-sm">
                    <p x-text="'Nominal: Rp ' + Number(activeApproval.grant_revision.old.amount).toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' menjadi Rp ' + Number(activeApproval.grant_revision.new.amount).toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></p>
                    <p x-text="'Donatur: ' + activeApproval.grant_revision.old.donor_name + ' menjadi ' + activeApproval.grant_revision.new.donor_name"></p>
                    <p x-text="'Metode: ' + activeApproval.grant_revision.old.payment_method + ' menjadi ' + activeApproval.grant_revision.new.payment_method"></p>
                    <p x-text="'Tanggal: ' + activeApproval.grant_revision.old.received_date + ' menjadi ' + activeApproval.grant_revision.new.received_date"></p>
                    <a class="work-btn" :href="activeApproval.grant_revision_url">Lihat jejak revisi dan bukti</a>
                </div>
            </template>
            <template x-if="activeApproval?.other_revision">
                <div class="work-stack grid-cols-1 text-sm">
                    <p x-text="'Nominal: Rp ' + Number(activeApproval.other_revision.old.amount).toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' menjadi Rp ' + Number(activeApproval.other_revision.new.amount).toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></p>
                    <p x-text="'Sumber: ' + activeApproval.other_revision.old.source_funding + ' menjadi ' + activeApproval.other_revision.new.source_funding"></p>
                    <p x-text="'Metode: ' + activeApproval.other_revision.old.payment_method + ' menjadi ' + activeApproval.other_revision.new.payment_method"></p>
                    <p x-text="'Tanggal: ' + activeApproval.other_revision.old.received_date + ' menjadi ' + activeApproval.other_revision.new.received_date"></p>
                    <a class="work-btn" :href="activeApproval.other_revision_url">Lihat jejak revisi dan bukti</a>
                </div>
            </template>

            <template x-if="activeApproval?.status_raw !== 'pending'">
                <div class="border-t border-gray-200 dark:border-gray-700 pt-2 space-y-1">
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-gray-400">Ditinjau Oleh:</span>
                        <span class="font-medium text-gray-900 dark:text-white" x-text="activeApproval?.reviewer_name"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-gray-400">Waktu Peninjauan:</span>
                        <span class="font-medium text-gray-900 dark:text-white" x-text="activeApproval?.reviewed_at"></span>
                    </div>
                </div>
            </template>
        </div>

        <template x-if="activeApproval?.status_raw === 'pending'">
            <div class="space-y-4">
                <template x-if="!activeApproval?.can_approve">
                    <div class="p-3 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-300 rounded-lg text-xs flex items-center gap-2">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span x-text="activeApproval?.type === 'other_income_revision' ? 'Revisi pendapatan Lainnya harus disetujui atau ditolak oleh Kepala Sekolah.' : (activeApproval?.type === 'grant_income_revision' ? 'Revisi pemasukan Hibah harus disetujui atau ditolak oleh Kepala Sekolah.' : (activeApproval?.type === 'bos_income_revision' ? 'Revisi pemasukan BOS harus disetujui atau ditolak oleh Kepala Sekolah.' : (activeApproval?.is_budget ? 'Hanya Kepala Sekolah yang memiliki wewenang untuk menyetujui atau menolak penyusunan dan revisi anggaran.' : 'Hanya Kepala Sekolah yang memiliki wewenang untuk menyetujui atau menolak pengeluaran belanja.')))"></span>
                    </div>
                </template>

                <div class="flex items-center justify-end gap-3">
                    <template x-if="!activeApproval?.can_approve">
                        <button type="button" class="work-btn" @click="$refs.approvalModal.close()">Tutup</button>
                    </template>

                    <template x-if="activeApproval?.can_approve && !activeApproval?.payment_url">
                        <div class="flex items-center gap-3">
                            <form :action="activeApproval?.reject_url" method="POST">
                                @csrf
                                <button type="submit" class="work-btn text-rose-600 hover:text-white hover:bg-rose-600 border-rose-300 dark:border-rose-800">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    Tolak
                                </button>
                            </form>

                            <form :action="activeApproval?.approve_url" method="POST">
                                @csrf
                                <button type="submit" class="work-btn work-btn-primary">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    Setujui
                                </button>
                            </form>
                        </div>
                    </template>
                </div>
            </div>
        </template>
        <template x-if="activeApproval?.status_raw !== 'pending'">
            <div class="flex justify-end">
                <button type="button" class="work-btn" @click="$refs.approvalModal.close()">Tutup</button>
            </div>
        </template>
    </dialog>
</div>
@endsection
