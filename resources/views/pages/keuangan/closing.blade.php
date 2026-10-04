@extends('layouts.app')

@section('content')
@php
    $typeNames = [
        'monthly' => 'Tutup Buku Bulanan',
        'yearly' => 'Tutup Buku Tahunan',
        'bos' => 'Tutup Buku Kas BOS',
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

    {{-- METRICS SUMMARY --}}
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4 mb-2">
        @foreach($metrics as $metric)
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-xs dark:border-gray-800 dark:bg-gray-900">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $metric['label'] }}</p>
                <p class="mt-1 text-xl font-bold tracking-tight text-gray-900 dark:text-white">{{ $metric['value'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- PRE-CLOSING AUDIT CHECKLIST --}}
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
                    Pemeriksaan Kesiapan Tutup Buku (Pre-Closing Audit)
                </h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    @if($allPassed)
                        Seluruh persyaratan pembukuan telah terpenuhi. Sistem siap mengunci saldo periode.
                    @else
                        Selesaikan transaksi atau persetujuan yang tertunda di bawah ini sebelum melakukan penguncian buku.
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

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 pt-3">
            {{-- Check 1: Approval Pending --}}
            <div class="p-3 rounded-lg border {{ $validation['pending_approvals'] === 0 ? 'border-gray-100 bg-gray-50/60 dark:border-gray-800 dark:bg-gray-800/30' : 'border-amber-200 bg-amber-50/50 dark:border-amber-900/40 dark:bg-amber-950/20' }}">
                <span class="block text-[11px] font-medium text-gray-500 dark:text-gray-400">Persetujuan Pending</span>
                <p class="mt-1 text-sm font-bold {{ $validation['pending_approvals'] === 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }}">
                    {{ $validation['pending_approvals'] }} pengajuan
                </p>
                @if($validation['pending_approvals'] > 0)
                    <a href="{{ route('approvals.index') }}" class="mt-1 inline-block text-[11px] text-brand-600 dark:text-brand-400 hover:underline">Selesaikan &rarr;</a>
                @endif
            </div>

            {{-- Check 2: Pembayaran Pending --}}
            <div class="p-3 rounded-lg border {{ $validation['pending_payments'] === 0 ? 'border-gray-100 bg-gray-50/60 dark:border-gray-800 dark:bg-gray-800/30' : 'border-amber-200 bg-amber-50/50 dark:border-amber-900/40 dark:bg-amber-950/20' }}">
                <span class="block text-[11px] font-medium text-gray-500 dark:text-gray-400">Verifikasi Pembayaran</span>
                <p class="mt-1 text-sm font-bold {{ $validation['pending_payments'] === 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }}">
                    {{ $validation['pending_payments'] }} transaksi
                </p>
                @if($validation['pending_payments'] > 0)
                    <a href="{{ route('spp.transaksi.index') }}" class="mt-1 inline-block text-[11px] text-brand-600 dark:text-brand-400 hover:underline">Periksa SPP &rarr;</a>
                @endif
            </div>

            {{-- Check 3: Pengeluaran Belanja Pending --}}
            <div class="p-3 rounded-lg border {{ $validation['pending_expenses'] === 0 ? 'border-gray-100 bg-gray-50/60 dark:border-gray-800 dark:bg-gray-800/30' : 'border-amber-200 bg-amber-50/50 dark:border-amber-900/40 dark:bg-amber-950/20' }}">
                <span class="block text-[11px] font-medium text-gray-500 dark:text-gray-400">Pengeluaran Belanja</span>
                <p class="mt-1 text-sm font-bold {{ $validation['pending_expenses'] === 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }}">
                    {{ $validation['pending_expenses'] }} pending
                </p>
                @if($validation['pending_expenses'] > 0)
                    <a href="{{ route('bos.belanja.index') }}" class="mt-1 inline-block text-[11px] text-brand-600 dark:text-brand-400 hover:underline">Periksa Belanja &rarr;</a>
                @endif
            </div>

            {{-- Check 4: Rekening Defisit --}}
            <div class="p-3 rounded-lg border {{ $validation['negative_accounts'] === 0 ? 'border-gray-100 bg-gray-50/60 dark:border-gray-800 dark:bg-gray-800/30' : 'border-rose-200 bg-rose-50/50 dark:border-rose-900/40 dark:bg-rose-950/20' }}">
                <span class="block text-[11px] font-medium text-gray-500 dark:text-gray-400">Rekening Saldo Defisit</span>
                <p class="mt-1 text-sm font-bold {{ $validation['negative_accounts'] === 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                    {{ $validation['negative_accounts'] }} rekening
                </p>
                @if($validation['negative_accounts'] > 0)
                    <a href="{{ route('finance.accounts') }}" class="mt-1 inline-block text-[11px] text-brand-600 dark:text-brand-400 hover:underline">Rekening Kas &rarr;</a>
                @endif
            </div>

            {{-- Check 5: Pajak Belum Disetor --}}
            <div class="p-3 rounded-lg border {{ $validation['unpaid_taxes'] === 0 ? 'border-gray-100 bg-gray-50/60 dark:border-gray-800 dark:bg-gray-800/30' : 'border-amber-200 bg-amber-50/50 dark:border-amber-900/40 dark:bg-amber-950/20' }}">
                <span class="block text-[11px] font-medium text-gray-500 dark:text-gray-400">Pajak Belanja Belum Setor</span>
                <p class="mt-1 text-sm font-bold {{ $validation['unpaid_taxes'] === 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }}">
                    {{ $validation['unpaid_taxes'] }} transaksi
                </p>
                @if($validation['unpaid_taxes'] > 0)
                    <a href="{{ route('bos.belanja.index') }}" class="mt-1 inline-block text-[11px] text-brand-600 dark:text-brand-400 hover:underline">Setor Pajak &rarr;</a>
                @endif
            </div>
        </div>
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
        empty-message="Belum ada periode tutup buku yang tercatat."
        empty-hint="Pilih Tutup Buku Baru setelah pemeriksaan pra-tutup buku berstatus siap."
    />

    {{-- MODAL PROSES TUTUP BUKU --}}
    <dialog x-ref="closingModal" class="account-dialog work" aria-labelledby="closing-modal-title" aria-describedby="closing-modal-help"
        @close="saving = false" @cancel="if (saving) $event.preventDefault()"
        @click="const bounds = $el.getBoundingClientRect(); if (!saving && ($event.clientX < bounds.left || $event.clientX > bounds.right || $event.clientY < bounds.top || $event.clientY > bounds.bottom)) $el.close()">
        <div class="account-dialog-head">
            <div class="min-w-0">
                <h2 id="closing-modal-title" class="text-lg font-semibold text-gray-900 dark:text-white">Proses Tutup Buku Periode</h2>
                <p id="closing-modal-help" class="work-muted text-xs mt-0.5">Kunci pembukuan dan bekukan saldo rekening sekolah pada periode terpilih.</p>
            </div>
            <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors p-1 rounded-md" @click="$refs.closingModal.close()" :disabled="saving" aria-label="Tutup form">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        @if(!$allPassed)
            <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 p-3.5 text-xs text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-300">
                <div class="font-semibold mb-1">Peringatan: Pemeriksaan audit belum lulus</div>
                Terdapat pengajuan approval atau kewajiban transaksi yang belum selesai. Anda disarankan menyelesaikan antrian terlebih dahulu agar penutupan buku tidak ditolak oleh sistem.
            </div>
        @endif

        <form method="POST" action="{{ route('finance.closing.store') }}" @submit="saving = true" :aria-busy="saving" class="space-y-4">
            @csrf

            <div class="work-field">
                <label for="close-type">Jenis Penutupan Buku <span class="text-red-500">*</span></label>
                <select id="close-type" name="type" required>
                    <option value="monthly">Tutup Buku Bulanan (Komite, SPP & Operasional)</option>
                    <option value="yearly">Tutup Buku Tahunan (Laporan Akhir Tahun Buku)</option>
                    <option value="bos">Tutup Buku Kas BOS (Buku Kas Pembantu)</option>
                </select>
            </div>

            <div class="work-field">
                <label for="close-period">Pilih Periode Pembukuan <span class="text-red-500">*</span></label>
                <input id="close-period" type="month" name="period" value="{{ date('Y-m') }}" required>
                <span class="text-xs text-gray-500 dark:text-gray-400 mt-1 block">Format: Bulan dan Tahun (misal: {{ date('Y-m') }}).</span>
            </div>

            <div class="rounded-xl border border-gray-200 bg-gray-50/70 p-3.5 dark:border-gray-800 dark:bg-gray-800/30 text-xs text-gray-600 dark:text-gray-400 space-y-1">
                <div class="font-semibold text-gray-900 dark:text-white">Dampak Penguncian Buku:</div>
                <ul class="list-disc list-inside space-y-0.5">
                    <li>Snapshot saldo seluruh rekening kas & bank akan dicatat secara permanen.</li>
                    <li>Transaksi pada periode bersangkutan tidak dapat diubah atau dihapus.</li>
                    <li>Data snapshot dijadikan dasar perhitungan saldo awal periode berikutnya.</li>
                </ul>
            </div>

            <div class="account-dialog-actions mt-5 pt-3 border-t border-gray-100 dark:border-gray-800">
                <button type="button" class="work-btn work-btn-secondary" @click="$refs.closingModal.close()" :disabled="saving">
                    Batal
                </button>
                <button type="submit" class="work-btn work-btn-primary" :disabled="saving">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    Konfirmasi & Kunci Buku
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
                    <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">Snapshot Saldo Rekening</h4>
                    <div class="rounded-xl border border-gray-200 dark:border-gray-800 overflow-hidden">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-gray-50 dark:bg-gray-800/50 border-b border-gray-200 dark:border-gray-800 text-gray-500">
                                <tr>
                                    <th class="px-3 py-2 font-medium">Rekening</th>
                                    <th class="px-3 py-2 font-medium">Tipe</th>
                                    <th class="px-3 py-2 font-medium text-right">Saldo Saat Ditutup</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                <template x-for="acc in (activeSnapshot.snapshot?.accounts || [])" :key="acc.id">
                                    <tr>
                                        <td class="px-3 py-2 font-medium text-gray-900 dark:text-white" x-text="acc.name"></td>
                                        <td class="px-3 py-2 text-gray-500" x-text="acc.type"></td>
                                        <td class="px-3 py-2 text-right font-mono font-semibold text-gray-900 dark:text-white" x-text="formatRupiah(acc.balance)"></td>
                                    </tr>
                                </template>
                                <tr class="bg-gray-50/50 dark:bg-gray-800/20 font-bold border-t border-gray-200 dark:border-gray-800">
                                    <td colspan="2" class="px-3 py-2 text-gray-900 dark:text-white">Total Saldo Terkunci</td>
                                    <td class="px-3 py-2 text-right font-mono text-brand-600 dark:text-brand-400" x-text="formatRupiah(activeSnapshot.total_balance)"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Snapshot Summary --}}
                <template x-if="activeSnapshot.snapshot?.summary">
                    <div>
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">Ringkasan Mutasi Ledger</h4>
                        <div class="grid grid-cols-2 gap-2 text-xs">
                            <div class="p-2.5 rounded-lg border border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-800/40">
                                <span class="text-gray-500 block">Total Penerimaan</span>
                                <span class="font-bold text-emerald-600" x-text="formatRupiah(activeSnapshot.snapshot.summary.collected)"></span>
                            </div>
                            <div class="p-2.5 rounded-lg border border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-800/40">
                                <span class="text-gray-500 block">Total Pengeluaran</span>
                                <span class="font-bold text-rose-600" x-text="formatRupiah(activeSnapshot.snapshot.summary.spent)"></span>
                            </div>
                        </div>
                    </div>
                </template>

                <div class="flex justify-end pt-3 border-t border-gray-100 dark:border-gray-800">
                    <button type="button" class="work-btn work-btn-secondary" @click="$refs.snapshotModal.close()">
                        Tutup Rincian
                    </button>
                </div>
            </div>
        </template>
    </dialog>
</div>
@endsection
