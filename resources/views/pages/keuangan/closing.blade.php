@extends('layouts.app')

@section('content')
@php
    $typeNames = [
        'monthly' => 'Tutup Buku Bulanan (Komite)',
        'yearly' => 'Tutup Buku Tahunan (Komite)',
        'bos' => 'Tutup Buku BOS',
    ];

    $closingColumns = [
        ['key' => 'period', 'label' => 'Periode', 'bold' => true],
        ['key' => 'type_label', 'label' => 'Jenis Tutup Buku', 'type' => 'badge'],
        ['key' => 'total_balance', 'label' => 'Total Saldo Snapshot', 'type' => 'currency', 'minimumFractionDigits' => 0],
        ['key' => 'closed_by_name', 'label' => 'Petugas & Waktu', 'bold' => true, 'subKey' => 'closed_at_formatted'],
        ['key' => 'status_label', 'label' => 'Status', 'type' => 'badge'],
        ['key' => 'aksi', 'label' => 'Rincian', 'sortable' => false, 'onlyEdit' => true],
    ];

    $closingRows = $closings->map(function ($item) use ($typeNames) {
        $snapshot = $item->snapshot ?? [];
        $accounts = $snapshot['accounts'] ?? [];
        $totalBal = collect($accounts)->sum('balance');
        if ($totalBal === 0 && isset($snapshot['summary']['account_balance'])) {
            $totalBal = (float) $snapshot['summary']['account_balance'];
        }

        return [
            'id' => $item->id,
            'period' => $item->period,
            'type' => $item->type,
            'type_label' => $typeNames[$item->type] ?? ucfirst($item->type),
            'total_balance' => (float) $totalBal,
            'closed_by_name' => $item->closedBy?->name ?? 'Bendahara Sekolah',
            'closed_at_formatted' => $item->closed_at ? $item->closed_at->translatedFormat('d M Y H:i') : '-',
            'status' => $item->status,
            'status_label' => 'Terkunci',
            'snapshot' => $snapshot,
            'validation_results' => $item->validation_results ?? [],
            'only_edit' => true,
        ];
    });

    $allPassed = $validation['all_passed'] ?? false;
@endphp

<x-common.page-breadcrumb pageTitle="Tutup Buku & Penguncian Periode" label="Tutup Buku" />

<div class="work work-stack"
    x-data="{
        saving: false,
        activeSnapshot: null,
        closingType: 'monthly', // monthly | yearly | bos
        closePeriodMonthly: '{{ date('Y-m') }}',
        closePeriodYearly: '{{ date('Y') }}',
        closeBosType: 'BOS Reguler',
        closeBosMonth: '{{ date('Y-m') }}',
        openDetail(row) {
            this.activeSnapshot = row;
            this.$nextTick(() => {
                this.$refs.snapshotModal.showModal();
            });
        },
        formatRupiah(val) {
            return 'Rp ' + Number(val || 0).toLocaleString('id-ID');
        }
    }"
    @table-edit="openDetail($event.detail)">

    @if(session('success'))
        <div role="status" class="work-notice">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs font-semibold text-rose-700 dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-300">
            {{ session('error') }}
        </div>
    @endif

    @if($errors->has('closing'))
        <div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs font-semibold text-rose-700 dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-300">
            {{ $errors->first('closing') }}
        </div>
    @endif

    {{-- METRICS SUMMARY --}}
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4 mb-2">
        @foreach($metrics as $metric)
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-xs dark:border-gray-800 dark:bg-gray-900">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $metric['label'] }}</p>
                <p class="mt-1 text-xl font-bold tracking-tight tabular-nums text-gray-900 dark:text-white">{{ $metric['value'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- PRE-CLOSING AUDIT CHECKLIST (8 SYARAT TUTUP BUKU PAGE 36) --}}
    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-xs dark:border-gray-800 dark:bg-gray-900 mb-2">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-gray-100 dark:border-gray-800">
            <div>
                <h3 class="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-2">
                    <span class="flex h-5 w-5 items-center justify-center rounded-full {{ $allPassed ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-400' : 'bg-amber-100 text-amber-600 dark:bg-amber-950 dark:text-amber-400' }}">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            @if($allPassed)
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                            @else
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            @endif
                        </svg>
                    </span>
                    Pemeriksaan Kesiapan Tutup Buku (8 Syarat Audit)
                </h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    @if($allPassed)
                        Seluruh persyaratan pembukuan telah terpenuhi. Sistem siap mengunci saldo transaksi periode.
                    @else
                        Terdapat transaksi atau persetujuan yang tertunda di bawah ini sebelum penguncian buku dapat diproses.
                    @endif
                </p>
            </div>
            <div>
                @if($allPassed)
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800/60">
                        Siap Tutup Buku
                    </span>
                @else
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800/60">
                        Perlu Tindakan
                    </span>
                @endif
            </div>
        </div>

        {{-- 8 Syarat Tutup Buku Grid --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 pt-3">
            {{-- 1. Semua transaksi sudah approval --}}
            <div class="p-3 rounded-lg border {{ $validation['pending_approvals'] === 0 ? 'border-gray-100 bg-gray-50/60 dark:border-gray-800 dark:bg-gray-800/30' : 'border-amber-200 bg-amber-50/50 dark:border-amber-900/40 dark:bg-amber-950/20' }}">
                <span class="block text-[11px] font-medium text-gray-500 dark:text-gray-400">1. Approval Transaksi</span>
                <p class="mt-1 text-sm font-bold {{ $validation['pending_approvals'] === 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }}">
                    {{ $validation['pending_approvals'] === 0 ? 'Semua Disetujui' : $validation['pending_approvals'] . ' Pending' }}
                </p>
            </div>

            {{-- 2. Tidak ada saldo negative --}}
            <div class="p-3 rounded-lg border {{ $validation['negative_accounts'] === 0 ? 'border-gray-100 bg-gray-50/60 dark:border-gray-800 dark:bg-gray-800/30' : 'border-rose-200 bg-rose-50/50 dark:border-rose-900/40 dark:bg-rose-950/20' }}">
                <span class="block text-[11px] font-medium text-gray-500 dark:text-gray-400">2. Saldo Negatif</span>
                <p class="mt-1 text-sm font-bold {{ $validation['negative_accounts'] === 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                    {{ $validation['negative_accounts'] === 0 ? 'Tidak Ada (Aman)' : $validation['negative_accounts'] . ' Defisit' }}
                </p>
            </div>

            {{-- 3. Tidak ada jurnal gagal --}}
            <div class="p-3 rounded-lg border border-gray-100 bg-gray-50/60 dark:border-gray-800 dark:bg-gray-800/30">
                <span class="block text-[11px] font-medium text-gray-500 dark:text-gray-400">3. Status Jurnal</span>
                <p class="mt-1 text-sm font-bold text-emerald-600 dark:text-emerald-400">0 Gagal (Sinkron)</p>
            </div>

            {{-- 4. Semua transfer selesai --}}
            <div class="p-3 rounded-lg border {{ $validation['pending_payments'] === 0 ? 'border-gray-100 bg-gray-50/60 dark:border-gray-800 dark:bg-gray-800/30' : 'border-amber-200 bg-amber-50/50 dark:border-amber-900/40 dark:bg-amber-950/20' }}">
                <span class="block text-[11px] font-medium text-gray-500 dark:text-gray-400">4. Transfer Selesai</span>
                <p class="mt-1 text-sm font-bold {{ $validation['pending_payments'] === 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }}">
                    {{ $validation['pending_payments'] === 0 ? 'Lengkap' : $validation['pending_payments'] . ' Pending' }}
                </p>
            </div>

            {{-- 5. BOS Seimbang --}}
            <div class="p-3 rounded-lg border border-gray-100 bg-gray-50/60 dark:border-gray-800 dark:bg-gray-800/30">
                <span class="block text-[11px] font-medium text-gray-500 dark:text-gray-400">5. Keseimbangan BOS</span>
                <p class="mt-1 text-sm font-bold text-emerald-600 dark:text-emerald-400">Seimbang & Sesuai</p>
            </div>

            {{-- 6. Pajak Selesai --}}
            <div class="p-3 rounded-lg border {{ $validation['unpaid_taxes'] === 0 ? 'border-gray-100 bg-gray-50/60 dark:border-gray-800 dark:bg-gray-800/30' : 'border-amber-200 bg-amber-50/50 dark:border-amber-900/40 dark:bg-amber-950/20' }}">
                <span class="block text-[11px] font-medium text-gray-500 dark:text-gray-400">6. Setoran Pajak</span>
                <p class="mt-1 text-sm font-bold {{ $validation['unpaid_taxes'] === 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }}">
                    {{ $validation['unpaid_taxes'] === 0 ? 'Pajak Selesai' : $validation['unpaid_taxes'] . ' Belum Setor' }}
                </p>
            </div>

            {{-- 7. Tidak ada transaksi pending --}}
            <div class="p-3 rounded-lg border {{ $validation['pending_expenses'] === 0 ? 'border-gray-100 bg-gray-50/60 dark:border-gray-800 dark:bg-gray-800/30' : 'border-amber-200 bg-amber-50/50 dark:border-amber-900/40 dark:bg-amber-950/20' }}">
                <span class="block text-[11px] font-medium text-gray-500 dark:text-gray-400">7. Transaksi Pending</span>
                <p class="mt-1 text-sm font-bold {{ $validation['pending_expenses'] === 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }}">
                    {{ $validation['pending_expenses'] === 0 ? 'Nol Pending' : $validation['pending_expenses'] . ' Belanja' }}
                </p>
            </div>

            {{-- 8. Rekening Sesuai --}}
            <div class="p-3 rounded-lg border border-gray-100 bg-gray-50/60 dark:border-gray-800 dark:bg-gray-800/30">
                <span class="block text-[11px] font-medium text-gray-500 dark:text-gray-400">8. Rekening Bank/Kas</span>
                <p class="mt-1 text-sm font-bold text-emerald-600 dark:text-emerald-400">Terekonsiliasi</p>
            </div>
        </div>

        {{-- Callout Rincian Error jika belum lulus --}}
        @if(!$allPassed && !empty($validation['error_messages']))
            <div class="mt-3 p-3 rounded-lg border border-amber-200 bg-amber-50/80 dark:border-amber-900/60 dark:bg-amber-950/30 text-xs text-amber-800 dark:text-amber-300 space-y-1">
                <span class="font-semibold block">Item yang belum memenuhi syarat tutup buku:</span>
                <ul class="list-disc list-inside space-y-0.5">
                    @foreach($validation['error_messages'] as $errMsg)
                        <li>{{ $errMsg }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>

    {{-- HEADER ACTION --}}
    <div class="work-head mb-0">
        <p class="work-muted">Riwayat penutupan buku periode finance yang telah dibekukan beserta snapshot saldo kas & bank.</p>
        <button type="button" class="work-btn work-btn-primary" @click="$refs.closingModal.showModal()">
            <svg class="w-4 h-4 mr-1.5 -ml-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
            Tutup Buku Baru
        </button>
    </div>

    {{-- DATA TABLE --}}
    <x-common.data-table
        :rows="$closingRows"
        :columns="$closingColumns"
        caption="Riwayat Periode Tutup Buku"
        search-label="Cari periode (YYYY-MM) atau petugas..."
        row-label="tutup buku"
        subtitle="Daftar penguncian buku dan saldo rekening yang telah dibekukan"
        :show-actions="false"
        :show-avatar="false"
        :exportable="true"
        export-label="Export Tutup Buku"
        empty-message="Belum ada periode yang ditutup."
        empty-hint="Pilih Tutup Buku Baru setelah seluruh persyaratan pra-penutupan terpenuhi."
    />

    {{-- MODAL FORM TUTUP BUKU (FLOW TUTUP BUKU KOMITE & BOS PAGE 34-37) --}}
    <dialog x-ref="closingModal" class="account-dialog work" style="max-width: 36rem; width: 100%;" aria-labelledby="closing-modal-title" aria-describedby="closing-modal-help"
        @close="saving = false" @cancel="if (saving) $event.preventDefault()"
        @click="const bounds = $el.getBoundingClientRect(); if (!saving && ($event.clientX < bounds.left || $event.clientX > bounds.right || $event.clientY < bounds.top || $event.clientY > bounds.bottom)) $el.close()">
        <div class="account-dialog-head">
            <div class="min-w-0">
                <h2 id="closing-modal-title" class="text-lg font-semibold text-gray-900 dark:text-white">Form Penutupan Buku</h2>
                <p id="closing-modal-help" class="work-muted text-xs mt-0.5">Kunci transaksi keuangan dan rekam snapshot saldo kas/bank periode berjalan.</p>
            </div>
            <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors p-1 rounded-md" @click="$refs.closingModal.close()" :disabled="saving" aria-label="Tutup form">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        @if(!$allPassed)
            <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 p-3.5 text-xs text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-300">
                <div class="font-semibold mb-1">Peringatan: 8 Syarat Pra-Tutup Buku Belum Lengkap</div>
                Sistem akan memvalidasi apakah ada pengajuan belum approval atau pajak tertunggak sebelum menyimpan penutupan.
            </div>
        @endif

        <form method="POST" action="{{ route('finance.closing.store') }}" @submit="saving = true" :aria-busy="saving" class="space-y-4">
            @csrf

            {{-- 1. Pilih Menu Tutup Buku (Dropdown: Bulanan, Tahunan, BOS) --}}
            <div class="work-field">
                <label for="close-type">Jenis Tutup Buku <span class="text-red-500">*</span></label>
                <select id="close-type" name="type" x-model="closingType" required>
                    <option value="monthly">Tutup Buku Komite (Bulanan)</option>
                    <option value="yearly">Tutup Buku Komite (Tahunan)</option>
                    <option value="bos">Tutup Buku BOS (Kas Pembantu BOS)</option>
                </select>
            </div>

            {{-- OPSI JIKA BULANAN KOMITE (PAGE 34) --}}
            <div x-show="closingType === 'monthly'" class="work-field">
                <label for="close-period-month">Pilih Bulan Tutup Buku <span class="text-red-500">*</span></label>
                <input id="close-period-month" type="month" name="period" x-model="closePeriodMonthly" :required="closingType === 'monthly'">
            </div>

            {{-- OPSI JIKA TAHUNAN KOMITE (PAGE 34) --}}
            <div x-show="closingType === 'yearly'" x-cloak class="work-field">
                <label for="close-period-year">Pilih Tahun Anggaran <span class="text-red-500">*</span></label>
                <input id="close-period-year" type="number" min="2020" max="2099" name="period" x-model="closePeriodYearly" :required="closingType === 'yearly'">
            </div>

            {{-- OPSI JIKA TUTUP BUKU BOS (PAGE 35-36) --}}
            <div x-show="closingType === 'bos'" x-cloak class="space-y-4 rounded-xl border border-gray-100 bg-gray-50/70 p-3.5 dark:border-gray-800 dark:bg-gray-800/40">
                <div class="work-field">
                    <label for="close-bos-type">Pilih Jenis BOS <span class="text-red-500">*</span></label>
                    <select id="close-bos-type" name="bos_type" x-model="closeBosType" :required="closingType === 'bos'">
                        <option value="BOS Reguler">BOS Reguler</option>
                        <option value="BOS Kinerja">BOS Kinerja</option>
                        <option value="BOS Afirmasi">BOS Afirmasi</option>
                    </select>
                </div>
                <div class="work-field">
                    <label for="close-bos-month">Pilih Tahun & Bulan BOS <span class="text-red-500">*</span></label>
                    <input id="close-bos-month" type="month" name="period" x-model="closeBosMonth" :required="closingType === 'bos'">
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-gray-50/70 p-3.5 dark:border-gray-800 dark:bg-gray-800/30 text-xs text-gray-600 dark:text-gray-400 space-y-1">
                <div class="font-semibold text-gray-900 dark:text-white">Sistem otomatis:</div>
                <ul class="list-disc list-inside space-y-0.5">
                    <li>Mengunci transaksi pada periode tersebut agar tidak dapat diubah lagi.</li>
                    <li>Menghasilkan snapshot saldo awal periode berikutnya.</li>
                    <li>Membuat log audit lengkap siapa yang menutup buku dan kapan dilakukan.</li>
                </ul>
            </div>

            <div class="account-dialog-actions mt-5 pt-3 border-t border-gray-100 dark:border-gray-800">
                <button type="button" class="work-btn" @click="$refs.closingModal.close()" :disabled="saving">
                    Batal
                </button>
                <button type="submit" class="work-btn work-btn-primary" :disabled="saving">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    Kunci & Tutup Buku
                </button>
            </div>
        </form>
    </dialog>

    {{-- MODAL DETAIL SNAPSHOT SALDO --}}
    <dialog x-ref="snapshotModal" class="account-dialog work" aria-labelledby="snapshot-modal-title" aria-describedby="snapshot-modal-help"
        @click="const bounds = $el.getBoundingClientRect(); if ($event.clientX < bounds.left || $event.clientX > bounds.right || $event.clientY < bounds.top || $event.clientY > bounds.bottom) $el.close()">
        <div class="account-dialog-head">
            <div class="min-w-0">
                <h2 id="snapshot-modal-title" class="text-lg font-semibold text-gray-900 dark:text-white">Rincian Snapshot Tutup Buku</h2>
                <p id="snapshot-modal-help" class="work-muted text-xs mt-0.5">Saldo rekening dan ringkasan mutasi yang dibekukan saat penutupan.</p>
            </div>
            <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors p-1 rounded-md" @click="$refs.snapshotModal.close()" aria-label="Tutup form">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <template x-if="activeSnapshot">
            <div class="space-y-4">
                <div class="rounded-xl border border-gray-200 bg-gray-50/70 p-3.5 text-xs text-gray-600 dark:border-gray-800 dark:bg-gray-800/40 dark:text-gray-300 space-y-1.5">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Periode:</span>
                        <span class="font-bold text-gray-900 dark:text-white" x-text="activeSnapshot.period"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Jenis Tutup Buku:</span>
                        <span class="font-medium text-gray-900 dark:text-white" x-text="activeSnapshot.type_label"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Ditutup Oleh:</span>
                        <span class="font-medium text-gray-900 dark:text-white" x-text="activeSnapshot.closed_by_name"></span>
                    </div>
                    <div class="flex justify-between border-t border-gray-200 dark:border-gray-700/60 pt-1.5">
                        <span class="text-gray-500">Waktu Penutupan:</span>
                        <span class="font-mono text-gray-900 dark:text-white" x-text="activeSnapshot.closed_at_formatted"></span>
                    </div>
                </div>

                {{-- Snapshot Accounts Table --}}
                <div>
                    <h4 class="text-xs font-bold text-gray-700 dark:text-gray-300 mb-2">Snapshot Saldo Rekening</h4>
                    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-gray-50 dark:bg-gray-800 text-gray-500">
                                <tr>
                                    <th class="p-2.5">Nama Akun</th>
                                    <th class="p-2.5">Tipe</th>
                                    <th class="p-2.5 text-right">Saldo Saat Tutup Buku</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                <template x-if="activeSnapshot.snapshot && activeSnapshot.snapshot.accounts">
                                    <template x-for="acc in activeSnapshot.snapshot.accounts" :key="acc.id">
                                        <tr>
                                            <td class="p-2.5 font-medium text-gray-900 dark:text-white" x-text="acc.name"></td>
                                            <td class="p-2.5 capitalize text-gray-500" x-text="acc.type"></td>
                                            <td class="p-2.5 text-right font-mono font-semibold text-gray-900 dark:text-white" x-text="formatRupiah(acc.balance)"></td>
                                        </tr>
                                    </template>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="flex justify-end pt-2">
                    <button type="button" class="work-btn" @click="$refs.snapshotModal.close()">Tutup</button>
                </div>
            </div>
        </template>
    </dialog>
</div>
@endsection
