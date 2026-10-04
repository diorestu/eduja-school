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

    $typeNames = [
        'budget' => 'Rencana Anggaran',
        'budget_revision' => 'Revisi Anggaran',
        'expense' => 'Pengeluaran Belanja',
        'transfer' => 'Transfer Antar Rekening',
        'attendance' => 'Izin / Cuti',
    ];

    $statusLabels = [
        'pending' => 'Pending',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak',
    ];

    $approvalRows = $approvals->map(function ($app) use ($typeNames, $statusLabels) {
        return [
            'id' => $app->id,
            'type' => $app->type,
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
            'only_edit' => true,
        ];
    });
@endphp

<x-common.page-breadcrumb pageTitle="Antrian Approval Keuangan" label="Approval" />

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
    @table-edit="openDetail($event.detail)">

    @if(session('success'))
        <div role="status" class="work-notice">{{ session('success') }}</div>
    @endif
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
        <p class="work-muted">Antrian verifikasi dan persetujuan pengeluaran dana, revisi anggaran belanja, dan transaksi keuangan sekolah.</p>
    </div>

    <x-common.data-table
        :rows="$approvalRows"
        :columns="$approvalColumns"
        caption="Daftar Pengajuan Approval"
        search-label="Cari pengajuan..."
        row-label="pengajuan"
        subtitle="Antrian verifikasi persetujuan transaksi dan anggaran"
        :show-actions="false"
        :show-avatar="false"
        :exportable="false"
        empty-message="Belum ada antrian approval."
        empty-hint="Pengajuan belanja atau revisi anggaran yang membutuhkan persetujuan akan muncul di sini."
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
            <div class="flex items-center justify-end gap-3">
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
        <template x-if="activeApproval?.status_raw !== 'pending'">
            <div class="flex justify-end">
                <button type="button" class="work-btn" @click="$refs.approvalModal.close()">Tutup</button>
            </div>
        </template>
    </dialog>
</div>
@endsection
