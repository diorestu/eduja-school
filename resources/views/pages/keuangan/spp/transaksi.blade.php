@extends('layouts.app')

@section('content')
@php
    $invoiceColumns = [
        ['key' => 'student_name', 'label' => 'Siswa', 'subKey' => 'student_nis', 'avatar' => true],
        ['key' => 'invoice_number', 'label' => 'No. Tagihan / Deskripsi', 'subKey' => 'item_description'],
        ['key' => 'total_amount', 'label' => 'Total Tagihan', 'type' => 'currency', 'minimumFractionDigits' => 0],
        ['key' => 'paid_amount', 'label' => 'Terbayar', 'type' => 'currency', 'minimumFractionDigits' => 0],
        ['key' => 'remaining_amount', 'label' => 'Sisa Tagihan', 'type' => 'currency', 'minimumFractionDigits' => 0],
        ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
        ['key' => 'aksi', 'label' => 'Aksi', 'sortable' => false, 'onlyEdit' => true],
    ];

    $totalTagihan = 0;
    $totalTerbayar = 0;
    $totalSisa = 0;

    $invoiceRows = $invoices->map(function ($invoice) use (&$totalTagihan, &$totalTerbayar, &$totalSisa) {
        $total = (float) $invoice->total_amount;
        $paid = (float) $invoice->paid_amount;
        $remaining = (float) $invoice->remaining_amount;

        $totalTagihan += $total;
        $totalTerbayar += $paid;
        $totalSisa += $remaining;

        return [
            'id' => $invoice->id,
            'student_name' => $invoice->student?->name ?? 'Siswa',
            'student_nis' => 'NIS: ' . ($invoice->student?->nis ?? '-'),
            'invoice_number' => $invoice->invoice_number,
            'item_description' => $invoice->invoiceItems->first()?->name ?? 'Detail Biaya Sekolah',
            'total_amount' => $total,
            'paid_amount' => $paid,
            'remaining_amount' => $remaining,
            'status' => $invoice->status,
            'can_pay' => $remaining > 0,
            'only_edit' => true,
        ];
    });
@endphp

<x-common.page-breadcrumb pageTitle="Transaksi Keuangan SPP & Pemasukan" label="Pemasukan" />

<div class="work work-stack"
    x-data="{
        saving: false,
        activeInvoice: { id: '', number: '', name: '', remaining: 0 },
        payAmount: 0,
        openPay(row) {
            this.activeInvoice = {
                id: row.id,
                number: row.invoice_number,
                name: row.student_name,
                remaining: row.remaining_amount
            };
            this.payAmount = row.remaining_amount > 0 ? row.remaining_amount : 0;
            this.$nextTick(() => {
                this.$refs.paymentModal.showModal();
            });
        },
        formatRupiah(val) {
            return 'Rp ' + Number(val || 0).toLocaleString('id-ID');
        }
    }"
    @table-edit="openPay($event.detail)">

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
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-xs dark:border-gray-800 dark:bg-gray-900">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Total Ditagihkan</p>
            <p class="mt-1 text-xl font-bold tracking-tight text-gray-900 dark:text-white">Rp {{ number_format($totalTagihan, 0, ',', '.') }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-xs dark:border-gray-800 dark:bg-gray-900">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">Total Diterima</p>
            <p class="mt-1 text-xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400">Rp {{ number_format($totalTerbayar, 0, ',', '.') }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-xs dark:border-gray-800 dark:bg-gray-900">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-rose-600 dark:text-rose-400">Sisa Piutang</p>
            <p class="mt-1 text-xl font-bold tracking-tight text-rose-600 dark:text-rose-400">Rp {{ number_format($totalSisa, 0, ',', '.') }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-xs dark:border-gray-800 dark:bg-gray-900">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Jumlah Tagihan</p>
            <p class="mt-1 text-xl font-bold tracking-tight text-gray-900 dark:text-white">{{ $invoices->count() }} invoice</p>
        </div>
    </div>

    {{-- QUICK ACTIONS --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3 mb-2">
        <!-- Generate Invoices Card -->
        <div class="lg:col-span-1 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900 shadow-xs">
            <div class="flex items-center gap-2 mb-2">
                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                </span>
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Generate Tagihan Bulanan</h3>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">Buat tagihan SPP bulanan otomatis untuk seluruh siswa aktif.</p>

            <form action="{{ route('spp.transaksi.generate') }}" method="POST" class="space-y-3">
                @csrf
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Pilih Bulan Tagihan</label>
                    <input type="month" name="billing_month" value="{{ date('Y-m') }}" required
                        class="h-9 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-1.5 text-xs text-gray-800 focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                </div>
                <button type="submit" class="work-btn work-btn-primary w-full justify-center">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    Proses Tagihan
                </button>
            </form>
        </div>

        <!-- Search / Filter Card -->
        <div class="lg:col-span-2 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900 shadow-xs flex flex-col justify-between">
            <div>
                <div class="flex items-center gap-2 mb-2">
                    <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Pencarian Cepat Siswa</h3>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">Filter data tagihan berdasarkan Nama atau NIS siswa dari database.</p>
            </div>

            <form action="{{ route('spp.transaksi.index') }}" method="GET" class="flex gap-2">
                <div class="relative flex-1">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama siswa atau NIS..."
                        class="h-9 w-full rounded-lg border border-gray-300 bg-transparent pl-9 pr-3 py-1.5 text-xs text-gray-800 placeholder:text-gray-400 focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                    <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
                <button type="submit" class="work-btn work-btn-secondary">
                    Filter Data
                </button>
                @if(request('search'))
                    <a href="{{ route('spp.transaksi.index') }}" class="work-btn work-btn-secondary text-gray-500">
                        Reset
                    </a>
                @endif
            </form>
        </div>
    </div>

    {{-- DATA TABLE --}}
    <x-common.data-table
        :rows="$invoiceRows"
        :columns="$invoiceColumns"
        caption="Daftar Tagihan Biaya Sekolah"
        search-label="Cari nama siswa, NIS, atau nomor tagihan..."
        row-label="tagihan"
        subtitle="Data tagihan dan riwayat pembayaran siswa"
        :show-actions="false"
        :show-avatar="true"
        :exportable="true"
        export-label="Export Tagihan"
        empty-message="Belum ada data tagihan."
        empty-hint="Generate tagihan bulanan atau periksa filter pencarian siswa."
    />

    {{-- MODAL PEMBAYARAN SPP --}}
    <dialog x-ref="paymentModal" class="account-dialog work" aria-labelledby="payment-modal-title" aria-describedby="payment-modal-help"
        @close="saving = false" @cancel="if (saving) $event.preventDefault()"
        @click="const bounds = $el.getBoundingClientRect(); if (!saving && ($event.clientX < bounds.left || $event.clientX > bounds.right || $event.clientY < bounds.top || $event.clientY > bounds.bottom)) $el.close()">
        <div class="account-dialog-head">
            <div class="min-w-0">
                <h2 id="payment-modal-title" class="text-lg font-semibold text-gray-900 dark:text-white">Catat Pembayaran SPP</h2>
                <p id="payment-modal-help" class="work-muted text-xs mt-0.5">Konfirmasi penerimaan pembayaran dari siswa atau orang tua.</p>
            </div>
            <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors p-1 rounded-md" @click="$refs.paymentModal.close()" :disabled="saving" aria-label="Tutup form">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="mb-4 rounded-xl border border-gray-200 bg-gray-50/80 p-3.5 text-xs text-gray-600 dark:border-gray-800 dark:bg-gray-800/40 dark:text-gray-300 space-y-1.5">
            <div class="flex justify-between">
                <span class="text-gray-500">Nama Siswa:</span>
                <span class="font-semibold text-gray-900 dark:text-white" x-text="activeInvoice.name"></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-500">No. Tagihan:</span>
                <span class="font-mono font-medium text-gray-900 dark:text-white" x-text="activeInvoice.number"></span>
            </div>
            <div class="flex justify-between border-t border-gray-200 dark:border-gray-700/60 pt-1.5">
                <span class="text-gray-500">Sisa Tagihan:</span>
                <span class="font-bold text-rose-600 dark:text-rose-400" x-text="formatRupiah(activeInvoice.remaining)"></span>
            </div>
        </div>

        <form :action="'/spp/transaksi/' + activeInvoice.id + '/bayar'" method="POST" @submit="saving = true" :aria-busy="saving" class="space-y-4">
            @csrf

            <div class="work-field">
                <label for="pay-amount">Jumlah Bayar (Rp) <span class="text-red-500">*</span></label>
                <input id="pay-amount" type="number" name="amount_paid" x-model="payAmount" :max="activeInvoice.remaining" min="1" step="1000" required placeholder="0">
            </div>

            <div class="work-field">
                <label for="pay-method">Metode Pembayaran <span class="text-red-500">*</span></label>
                <select id="pay-method" name="payment_method" required>
                    <option value="Tunai">Tunai / Cash</option>
                    <option value="Transfer Bank">Transfer Bank</option>
                    <option value="E-Wallet">E-Wallet (Gopay/Ovo/Dana)</option>
                </select>
            </div>

            <div class="work-field">
                <label for="pay-date">Tanggal Pembayaran <span class="text-red-500">*</span></label>
                <input id="pay-date" type="date" name="payment_date" value="{{ date('Y-m-d') }}" required>
            </div>

            <div class="account-dialog-actions mt-5 pt-3 border-t border-gray-100 dark:border-gray-800">
                <button type="button" class="work-btn work-btn-secondary" @click="$refs.paymentModal.close()" :disabled="saving">
                    Batal
                </button>
                <button type="submit" class="work-btn work-btn-primary" :disabled="saving || activeInvoice.remaining <= 0">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Simpan Pembayaran
                </button>
            </div>
        </form>
    </dialog>
</div>
@endsection
