@extends('layouts.app')

@section('content')
@php
    $revisionColumns = [
        ['key' => 'budget_year_name', 'label' => 'Tahun Anggaran', 'bold' => true],
        ['key' => 'source_funding', 'label' => 'Sumber Dana', 'type' => 'badge'],
        ['key' => 'program_name', 'label' => 'Program & Kegiatan', 'bold' => true, 'subKey' => 'activity_name'],
        ['key' => 'amount', 'label' => 'Nominal Saat Ini', 'type' => 'currency', 'minimumFractionDigits' => 0],
        ['key' => 'revisions_count', 'label' => 'Riwayat Revisi'],
        ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
        ['key' => 'aksi', 'label' => 'Aksi', 'sortable' => false, 'onlyEdit' => true],
    ];

    $revisionRows = $budgetPlans->map(function ($plan) {
        $statusLabels = [
            'pending' => 'Pending',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            'draft' => 'Draft',
        ];

        return [
            'id' => $plan->id,
            'budget_year_name' => $plan->budgetYear?->name ?? 'Semua Periode',
            'source_funding' => strtoupper($plan->source_funding),
            'program_name' => $plan->program_name,
            'activity_name' => $plan->activity_name,
            'amount' => (float) $plan->amount,
            'revisions_count' => $plan->revisions->count() . ' kali revisi',
            'status' => $statusLabels[$plan->status] ?? ucfirst($plan->status),
            'status_raw' => $plan->status ?? 'pending',
            'revision_url' => route('finance.budgets.revisions.store', $plan),
            'only_edit' => true,
        ];
    });
@endphp

<x-common.page-breadcrumb pageTitle="Revisi Anggaran" label="Perencanaan Anggaran" />

<div class="work work-stack"
    x-data="{
        saving: false,
        activePlan: null,
        revisionAmount: '',
        revisionReason: '',
        openEdit(row) {
            this.activePlan = row;
            this.revisionAmount = row.amount || '';
            this.revisionReason = '';
            this.$nextTick(() => {
                this.$refs.revisionModal.showModal();
            });
        },
        formatRupiah(val) {
            if (!val && val !== 0) return 'Rp 0';
            return 'Rp ' + Number(val).toLocaleString('id-ID');
        }
    }"
    @table-edit="openEdit($event.detail)">

    @if(session('success'))
        <div role="status" class="work-notice">{{ session('success') }}</div>
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
        <p class="work-muted">Pilih rencana anggaran pada tabel untuk mengajukan penyesuaian/revisi nominal belanja ke Kepala Sekolah.</p>
    </div>

    <x-common.data-table
        :rows="$revisionRows"
        :columns="$revisionColumns"
        caption="Daftar Rencana untuk Revisi Anggaran"
        search-label="Cari mata anggaran..."
        row-label="rencana anggaran"
        subtitle="Pengajuan revisi dan riwayat penyesuaian pagu anggaran"
        :show-actions="false"
        :show-avatar="false"
        :exportable="false"
        empty-message="Belum ada rencana anggaran."
        empty-hint="Buat rencana anggaran terlebih dahulu di menu Susun Anggaran."
    />

    {{-- MODAL AJUKAN REVISI ANGGARAN --}}
    <dialog x-ref="revisionModal" class="account-dialog work" aria-labelledby="revision-modal-title" aria-describedby="revision-modal-help"
        @close="saving = false" @cancel="if (saving) $event.preventDefault()"
        @click="const bounds = $el.getBoundingClientRect(); if (!saving && ($event.clientX < bounds.left || $event.clientX > bounds.right || $event.clientY < bounds.top || $event.clientY > bounds.bottom)) $el.close()">
        <div class="account-dialog-head">
            <div class="min-w-0">
                <h2 id="revision-modal-title" class="text-lg font-semibold text-gray-900 dark:text-white">Ajukan Revisi Anggaran</h2>
                <p id="revision-modal-help" class="work-muted text-xs mt-0.5">Pengajuan revisi akan masuk antrian approval Kepala Sekolah sebelum diterapkan.</p>
            </div>
            <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors p-1 rounded-md" @click="$refs.revisionModal.close()" :disabled="saving" aria-label="Tutup form">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="mb-4 p-3 bg-gray-50 dark:bg-gray-800/50 rounded-lg border border-gray-200 dark:border-gray-700/60 text-xs space-y-1">
            <div class="flex justify-between">
                <span class="text-gray-500 dark:text-gray-400">Tahun:</span>
                <span class="font-medium text-gray-900 dark:text-white" x-text="activePlan?.budget_year_name"></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-500 dark:text-gray-400">Program:</span>
                <span class="font-medium text-gray-900 dark:text-white truncate max-w-[240px]" x-text="activePlan?.program_name"></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-500 dark:text-gray-400">Kegiatan:</span>
                <span class="font-medium text-gray-900 dark:text-white truncate max-w-[240px]" x-text="activePlan?.activity_name"></span>
            </div>
            <div class="flex justify-between border-t border-gray-200 dark:border-gray-700 pt-1 mt-1">
                <span class="text-gray-500 dark:text-gray-400">Nominal Saat Ini:</span>
                <span class="font-semibold text-gray-900 dark:text-white" x-text="formatRupiah(activePlan?.amount)"></span>
            </div>
        </div>

        <form method="POST" :action="activePlan ? activePlan.revision_url : ''" @submit="saving = true" :aria-busy="saving">
            @csrf

            <div class="work-field">
                <label for="rev-amount">Nominal Anggaran Baru (Rp) <span class="text-red-500">*</span></label>
                <input id="rev-amount" name="new_amount" x-model="revisionAmount" type="number" min="0" step="1000" inputmode="decimal" required placeholder="Contoh: 18000000">
            </div>

            <div class="work-field">
                <label for="rev-reason">Alasan Revisi Anggaran <span class="text-red-500">*</span></label>
                <textarea id="rev-reason" name="reason" x-model="revisionReason" rows="3" maxlength="1000" required placeholder="Jelaskan dasar kebutuhan penyesuaian pagu anggaran ini…"></textarea>
                <p class="work-muted text-xs mt-1">Alasan revisi akan ditelaah oleh Kepala Sekolah.</p>
            </div>

            <div class="account-dialog-actions mt-4">
                <button type="button" class="work-btn" @click="$refs.revisionModal.close()" :disabled="saving">Batal</button>
                <button type="submit" class="work-btn work-btn-primary" :disabled="saving">
                    <span x-show="!saving">Ajukan Revisi</span>
                    <span x-show="saving" x-cloak>Mengajukan…</span>
                </button>
            </div>
        </form>
    </dialog>
</div>
@endsection
