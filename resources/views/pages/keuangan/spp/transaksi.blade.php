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

    $arrearsColumns = [
        ['key' => 'name', 'label' => 'Nama Siswa', 'bold' => true, 'subKey' => 'nis_nisn', 'avatar' => true],
        ['key' => 'class_name', 'label' => 'Kelas'],
        ['key' => 'unpaid_count', 'label' => 'Item Tertunggak'],
        ['key' => 'total_arrears', 'label' => 'Nominal Tunggakan', 'type' => 'currency', 'minimumFractionDigits' => 0],
        ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
        ['key' => 'aksi', 'label' => 'Aksi', 'sortable' => false, 'onlyEdit' => true],
    ];

    $arrearsRows = collect($arrearsStudents ?? [])->map(function ($s) {
        $statusLabels = [
            'belum bayar' => 'Belum Bayar',
            'cicil' => 'Cicil',
            'lunas' => 'Lunas',
            'menunggak' => 'Menunggak',
        ];

        return [
            'id' => $s['id'],
            'name' => $s['name'],
            'nis_nisn' => 'NIS: ' . $s['nis'] . ' · NISN: ' . $s['nisn'],
            'class_name' => $s['class_name'],
            'unpaid_count' => $s['unpaid_count'] . ' tagihan',
            'total_arrears' => (float) $s['total_arrears'],
            'status' => $statusLabels[$s['status']] ?? ucfirst($s['status']),
            'status_raw' => $s['status'],
            'invoices' => $s['invoices'],
            'only_edit' => true,
        ];
    });

    $incomeColumns = [
        ['key' => 'received_date_formatted', 'label' => 'Tanggal Terima', 'bold' => true],
        ['key' => 'category_label', 'label' => 'Kategori Pemasukan', 'type' => 'badge'],
        ['key' => 'source_funding', 'label' => 'Sumber / Donatur', 'bold' => true],
        ['key' => 'payment_method', 'label' => 'Metode'],
        ['key' => 'amount', 'label' => 'Nominal Masuk', 'type' => 'currency', 'minimumFractionDigits' => 0],
        ['key' => 'account_name', 'label' => 'Rekening / Kas'],
        ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
    ];

    $incomeRows = collect($incomes ?? [])->map(function ($inc) {
        return [
            'id' => $inc->id,
            'received_date_formatted' => $inc->received_date ? $inc->received_date->translatedFormat('d M Y') : '-',
            'category_label' => ucfirst($inc->source_funding ?? 'Lainnya'),
            'source_funding' => $inc->donor_name ? ($inc->donor_name . ' (' . $inc->source_funding . ')') : ($inc->source_funding ?? 'Pemasukan Kas'),
            'payment_method' => $inc->payment_method ?? 'Transfer',
            'amount' => (float) $inc->amount,
            'account_name' => $inc->account?->name ?? 'Kas Sekolah',
            'status' => 'Diterima',
        ];
    });
@endphp

<x-common.page-breadcrumb pageTitle="Transaksi Keuangan SPP & Pemasukan" label="Pemasukan" />

<div class="work work-stack"
    x-data="{
        activeTab: 'transaksi', // transaksi | tunggakan | pemasukan
        saving: false,
        activeInvoice: { id: '', number: '', name: '', remaining: 0 },
        payAmount: 0,
        payMethod: 'Tunai',
        payAccountId: '',
        payDate: '{{ date('Y-m-d') }}',
        // Arrears Modal State
        selectedStudent: null,
        selectedArrearsInvoiceId: '',
        arrearsPayAmount: 0,
        arrearsPayMethod: 'Tunai',
        arrearsPayAccountId: '',
        arrearsPayDate: '{{ date('Y-m-d') }}',
        // Catat Pemasukan Modal State
        incomeCategory: 'bos', // bos | hibah | lainnya
        bosSource: 'BOS Reguler',
        bosYear: {{ date('Y') }},
        incomeAmount: '',
        incomeDate: '{{ date('Y-m-d') }}',
        donorName: '',
        otherSource: '',
        incomeMethod: 'Transfer',
        targetAccountId: '',
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
        openPayArrears(row) {
            this.selectedStudent = row;
            if (row.invoices && row.invoices.length > 0) {
                this.selectedArrearsInvoiceId = row.invoices[0].id;
                this.arrearsPayAmount = row.invoices[0].remaining_amount;
            } else {
                this.selectedArrearsInvoiceId = '';
                this.arrearsPayAmount = 0;
            }
            this.$nextTick(() => {
                this.$refs.arrearsPayModal.showModal();
            });
        },
        updateArrearsInvoice(id) {
            this.selectedArrearsInvoiceId = id;
            if (this.selectedStudent && this.selectedStudent.invoices) {
                let inv = this.selectedStudent.invoices.find(i => i.id == id);
                if (inv) {
                    this.arrearsPayAmount = inv.remaining_amount;
                }
            }
        },
        formatRupiah(val) {
            return 'Rp ' + Number(val || 0).toLocaleString('id-ID');
        }
    }"
    @table-edit="activeTab === 'tunggakan' ? openPayArrears($event.detail) : openPay($event.detail)">

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
            <p class="mt-1 text-xl font-bold tracking-tight tabular-nums text-gray-900 dark:text-white">Rp {{ number_format($totalTagihan, 0, ',', '.') }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-xs dark:border-gray-800 dark:bg-gray-900">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">Total Diterima</p>
            <p class="mt-1 text-xl font-bold tracking-tight tabular-nums text-emerald-600 dark:text-emerald-400">Rp {{ number_format($totalTerbayar, 0, ',', '.') }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-xs dark:border-gray-800 dark:bg-gray-900">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-rose-600 dark:text-rose-400">Sisa Piutang</p>
            <p class="mt-1 text-xl font-bold tracking-tight tabular-nums text-rose-600 dark:text-rose-400">Rp {{ number_format($totalSisa, 0, ',', '.') }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-xs dark:border-gray-800 dark:bg-gray-900">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Jumlah Tagihan</p>
            <p class="mt-1 text-xl font-bold tracking-tight tabular-nums text-gray-900 dark:text-white">{{ $invoices->count() }} invoice</p>
        </div>
    </div>

    {{-- TAB NAVIGATION --}}
    <div class="flex border-b border-gray-200 dark:border-gray-800 gap-6 mb-2">
        <button type="button" @click="activeTab = 'transaksi'"
            :class="activeTab === 'transaksi' ? 'border-brand-600 text-brand-600 dark:text-brand-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400'"
            class="py-3 px-1 border-b-2 font-semibold text-sm transition-colors flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            Transaksi SPP & Tagihan
        </button>
        <button type="button" @click="activeTab = 'tunggakan'"
            :class="activeTab === 'tunggakan' ? 'border-brand-600 text-brand-600 dark:text-brand-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400'"
            class="py-3 px-1 border-b-2 font-semibold text-sm transition-colors flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Tunggakan Siswa
            <span class="px-2 py-0.5 rounded-full text-xs bg-rose-50 text-rose-600 dark:bg-rose-950/40 dark:text-rose-400 font-bold">{{ collect($arrearsStudents ?? [])->where('total_arrears', '>', 0)->count() }}</span>
        </button>
        <button type="button" @click="activeTab = 'pemasukan'"
            :class="activeTab === 'pemasukan' ? 'border-brand-600 text-brand-600 dark:text-brand-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400'"
            class="py-3 px-1 border-b-2 font-semibold text-sm transition-colors flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
            Catat Pemasukan (BOS, Hibah, Lainnya)
            <span class="px-2 py-0.5 rounded-full text-xs bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-400 font-bold">{{ collect($incomes ?? [])->count() }}</span>
        </button>
    </div>

    {{-- TAB 1: TRANSAKSI SPP --}}
    <div x-show="activeTab === 'transaksi'" class="space-y-4">
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
    </div>

    {{-- TAB 2: TUNGGAKAN SISWA (FLOW LIHAT TUNGGAKAN SISWA PAGE 18-22) --}}
    <div x-show="activeTab === 'tunggakan'" x-cloak class="space-y-4">
        <div class="work-head mb-0">
            <p class="work-muted">Pencarian dan penerimaan pembayaran tunggakan siswa (Komite/SPP) secara tunai atau transfer langsung.</p>
        </div>

        <x-common.data-table
            :rows="$arrearsRows"
            :columns="$arrearsColumns"
            caption="Daftar Tunggakan Siswa"
            search-label="Cari nama siswa, NIS, atau NISN..."
            row-label="siswa"
            subtitle="Daftar siswa yang memiliki sisa tagihan sekolah"
            :show-actions="false"
            :show-avatar="true"
            :exportable="true"
            export-label="Export Tunggakan"
            empty-message="Tidak ada siswa menunggak."
            empty-hint="Seluruh siswa telah melunasi tagihan yang aktif."
        />
    </div>

    {{-- TAB 3: CATAT PEMASUKAN (BOS, HIBAH, LAINNYA PAGE 25-29) --}}
    <div x-show="activeTab === 'pemasukan'" x-cloak class="space-y-4">
        <div class="work-head mb-0">
            <p class="work-muted">Pencatatan pemasukan dana transfer BOS, Hibah dari donatur, dan pendapatan lainnya ke kas/bank sekolah.</p>
            <button type="button" class="work-btn work-btn-primary" @click="$refs.incomeModal.showModal()">
                <svg class="w-4 h-4 mr-1.5 -ml-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Catat Pemasukan
            </button>
        </div>

        <x-common.data-table
            :rows="$incomeRows"
            :columns="$incomeColumns"
            caption="Buku Kas Masuk (Pemasukan Non-Siswa)"
            search-label="Cari sumber dana atau donatur..."
            row-label="pemasukan"
            subtitle="Daftar transaksi penerimaan dana sekolah dari BOS, hibah, dan lainnya"
            :show-actions="false"
            :show-avatar="false"
            :exportable="true"
            export-label="Export Pemasukan"
            empty-message="Belum ada riwayat pemasukan."
            empty-hint="Pilih Catat Pemasukan untuk merekam dana masuk baru."
        />
    </div>

    {{-- MODAL BAYAR TAGIHAN TUNGGAKAN SISWA (FLOW PAGE 19-20 & 21-22) --}}
    <dialog x-ref="arrearsPayModal" class="account-dialog work" style="max-width: 36rem; width: 100%;" aria-labelledby="arrears-pay-modal-title" aria-describedby="arrears-pay-modal-help"
        @close="saving = false" @cancel="if (saving) $event.preventDefault()"
        @click="const bounds = $el.getBoundingClientRect(); if (!saving && ($event.clientX < bounds.left || $event.clientX > bounds.right || $event.clientY < bounds.top || $event.clientY > bounds.bottom)) $el.close()">
        <div class="account-dialog-head">
            <div class="min-w-0">
                <h2 id="arrears-pay-modal-title" class="text-lg font-semibold text-gray-900 dark:text-white">Penerimaan Pembayaran Siswa</h2>
                <p id="arrears-pay-modal-help" class="work-muted text-xs mt-0.5">Catat pembayaran tunai atau transfer rekening untuk tagihan tertunggak.</p>
            </div>
            <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors p-1 rounded-md" @click="$refs.arrearsPayModal.close()" :disabled="saving" aria-label="Tutup form">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="mb-4 rounded-xl border border-gray-200 bg-gray-50/80 p-3.5 text-xs text-gray-600 dark:border-gray-800 dark:bg-gray-800/40 dark:text-gray-300 space-y-1.5">
            <div class="flex justify-between">
                <span class="text-gray-500">Nama Siswa:</span>
                <span class="font-semibold text-gray-900 dark:text-white" x-text="selectedStudent ? selectedStudent.name : '-'"></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-500">Kelas:</span>
                <span class="font-medium text-gray-900 dark:text-white" x-text="selectedStudent ? selectedStudent.class_name : '-'"></span>
            </div>
            <div class="flex justify-between border-t border-gray-200 dark:border-gray-700/60 pt-1.5">
                <span class="text-gray-500">Total Tunggakan:</span>
                <span class="font-bold text-rose-600 dark:text-rose-400" x-text="formatRupiah(selectedStudent ? selectedStudent.total_arrears : 0)"></span>
            </div>
        </div>

        <form :action="'/spp/transaksi/' + selectedArrearsInvoiceId + '/bayar'" method="POST" @submit="saving = true" :aria-busy="saving" class="space-y-4">
            @csrf

            <div class="work-field">
                <label for="arrears-select-invoice">Pilih Tagihan Siswa <span class="text-red-500">*</span></label>
                <select id="arrears-select-invoice" name="invoice_id" x-model="selectedArrearsInvoiceId" @change="updateArrearsInvoice($event.target.value)" required>
                    <template x-if="selectedStudent && selectedStudent.invoices && selectedStudent.invoices.length > 0">
                        <template x-for="inv in selectedStudent.invoices" :key="inv.id">
                            <option :value="inv.id" x-text="inv.invoice_number + ' - ' + inv.item_name + ' (' + formatRupiah(inv.remaining_amount) + ')'"></option>
                        </template>
                    </template>
                    <template x-if="!selectedStudent || !selectedStudent.invoices || selectedStudent.invoices.length === 0">
                        <option value="">Tidak ada tagihan tertunggak</option>
                    </template>
                </select>
            </div>

            <div class="work-field">
                <label for="arrears-select-method">Metode Pembayaran <span class="text-red-500">*</span></label>
                <select id="arrears-select-method" name="payment_method" x-model="arrearsPayMethod" required>
                    <option value="Tunai">Tunai / Cash</option>
                    <option value="Transfer Bank">Transfer Bank</option>
                </select>
            </div>

            <div x-show="arrearsPayMethod === 'Transfer Bank'" x-cloak class="work-field">
                <label for="arrears-select-account">Pilih Rekening Transfer <span class="text-red-500">*</span></label>
                <select id="arrears-select-account" name="account_id" x-model="arrearsPayAccountId">
                    <option value="">-- Pilih Rekening Komite Sekolah --</option>
                    @foreach($schoolAccounts as $acc)
                        <option value="{{ $acc->id }}">{{ $acc->name }} ({{ $acc->bank_name ?? $acc->type }}) - {{ $acc->account_number ?? '-' }}</option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="work-field">
                    <label for="arrears-nominal">Nominal Pembayaran (Rp) <span class="text-red-500">*</span></label>
                    <input id="arrears-nominal" type="text" inputmode="numeric" data-mask="currency" name="amount_paid" x-model="arrearsPayAmount" required placeholder="0" class="mask-currency">
                </div>
                <div class="work-field">
                    <label for="arrears-date">Tanggal Transaksi <span class="text-red-500">*</span></label>
                    <input id="arrears-date" type="date" name="payment_date" x-model="arrearsPayDate" required>
                </div>
            </div>

            <div class="account-dialog-actions mt-5 pt-3 border-t border-gray-100 dark:border-gray-800">
                <button type="button" class="work-btn" @click="$refs.arrearsPayModal.close()" :disabled="saving">Batal</button>
                <button type="submit" class="work-btn work-btn-primary" :disabled="saving || !selectedArrearsInvoiceId || (Number(String(arrearsPayAmount).replace(/\D/g, '')) <= 0)">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Simpan Pembayaran
                </button>
            </div>
        </form>
    </dialog>

    {{-- MODAL PEMBAYARAN SPP SINGLE INVOICE --}}
    <dialog x-ref="paymentModal" class="account-dialog work" aria-labelledby="payment-modal-title" aria-describedby="payment-modal-help"
        @close="saving = false" @cancel="if (saving) $event.preventDefault()"
        @click="const bounds = $el.getBoundingClientRect(); if (!saving && ($event.clientX < bounds.left || $event.clientX > bounds.right || $event.clientY < bounds.top || $event.clientY > bounds.bottom)) $el.close()">
        <div class="account-dialog-head">
            <div class="min-w-0">
                <h2 id="payment-modal-title" class="text-lg font-semibold text-gray-900 dark:text-white">Catat Pembayaran Tagihan</h2>
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
                <input id="pay-amount" type="text" inputmode="numeric" data-mask="currency" name="amount_paid" x-model="payAmount" required placeholder="0" class="mask-currency">
            </div>

            <div class="work-field">
                <label for="pay-method">Metode Pembayaran <span class="text-red-500">*</span></label>
                <select id="pay-method" name="payment_method" x-model="payMethod" required>
                    <option value="Tunai">Tunai / Cash</option>
                    <option value="Transfer Bank">Transfer Bank</option>
                    <option value="E-Wallet">E-Wallet (Gopay/Ovo/Dana)</option>
                </select>
            </div>

            <div x-show="payMethod === 'Transfer Bank'" x-cloak class="work-field">
                <label for="pay-account">Rekening Tujuan Transfer</label>
                <select id="pay-account" name="account_id" x-model="payAccountId">
                    <option value="">-- Pilih Rekening Sekolah --</option>
                    @foreach($schoolAccounts as $acc)
                        <option value="{{ $acc->id }}">{{ $acc->name }} ({{ $acc->bank_name ?? $acc->type }})</option>
                    @endforeach
                </select>
            </div>

            <div class="work-field">
                <label for="pay-date">Tanggal Pembayaran <span class="text-red-500">*</span></label>
                <input id="pay-date" type="date" name="payment_date" x-model="payDate" required>
            </div>

            <div class="account-dialog-actions mt-5 pt-3 border-t border-gray-100 dark:border-gray-800">
                <button type="button" class="work-btn" @click="$refs.paymentModal.close()" :disabled="saving">
                    Batal
                </button>
                <button type="submit" class="work-btn work-btn-primary" :disabled="saving || activeInvoice.remaining <= 0">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Simpan Pembayaran
                </button>
            </div>
        </form>
    </dialog>

    {{-- MODAL CATAT PEMASUKAN (BOS, HIBAH, LAINNYA FLOW PAGE 25-29) --}}
    <dialog x-ref="incomeModal" class="account-dialog work" style="max-width: 40rem; width: 100%;" aria-labelledby="income-modal-title" aria-describedby="income-modal-help"
        @close="saving = false" @cancel="if (saving) $event.preventDefault()"
        @click="const bounds = $el.getBoundingClientRect(); if (!saving && ($event.clientX < bounds.left || $event.clientX > bounds.right || $event.clientY < bounds.top || $event.clientY > bounds.bottom)) $el.close()">
        <div class="account-dialog-head">
            <div class="min-w-0">
                <h2 id="income-modal-title" class="text-lg font-semibold text-gray-900 dark:text-white">Catat Pemasukan Kas / Bank</h2>
                <p id="income-modal-help" class="work-muted text-xs mt-0.5">Input penerimaan dana transfer BOS, Hibah yayasan/donatur, atau pendapatan lainnya.</p>
            </div>
            <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors p-1 rounded-md" @click="$refs.incomeModal.close()" :disabled="saving" aria-label="Tutup form">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <form method="POST" action="{{ route('spp.transaksi.pemasukan') }}" enctype="multipart/form-data" @submit="saving = true" :aria-busy="saving" class="space-y-4">
            @csrf

            {{-- Kategori Pemasukan --}}
            <div class="work-field">
                <label for="income-category">Pilih Kategori Pemasukan <span class="text-red-500">*</span></label>
                <select id="income-category" name="category" x-model="incomeCategory" required>
                    <option value="bos">Pemasukan BOS (Reguler / Kinerja / Afirmasi)</option>
                    <option value="hibah">Pemasukan Hibah (Donatur / Yayasan)</option>
                    <option value="lainnya">Pendapatan Lainnya (Kantin / Sewa / dll)</option>
                </select>
            </div>

            {{-- FORM KHUSUS BOS (PAGE 25) --}}
            <template x-if="incomeCategory === 'bos'">
                <div class="space-y-4 rounded-xl border border-gray-100 bg-gray-50/70 p-3.5 dark:border-gray-800 dark:bg-gray-800/40">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="work-field">
                            <label for="bos-source">Pilih Jenis BOS <span class="text-red-500">*</span></label>
                            <select id="bos-source" name="source_funding" x-model="bosSource" required>
                                <option value="BOS Reguler">BOS Reguler</option>
                                <option value="BOS Kinerja">BOS Kinerja</option>
                                <option value="BOS Afirmasi">BOS Afirmasi</option>
                                <option value="BOS Daerah">BOS Daerah (BOSDA)</option>
                            </select>
                        </div>
                        <div class="work-field">
                            <label for="bos-year">Tahun BOS <span class="text-red-500">*</span></label>
                            <input id="bos-year" name="bos_year" type="number" min="2020" max="2099" x-model="bosYear" required>
                        </div>
                    </div>
                    <div class="work-field">
                        <label for="bos-proof">Upload Dokumen SP2D (PDF/JPG/PNG) <span class="text-gray-400 font-normal">(opsional)</span></label>
                        <input id="bos-proof" name="proof" type="file" accept=".pdf,.jpg,.jpeg,.png,.heic"
                            class="block w-full text-xs text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-medium file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200">
                    </div>
                </div>
            </template>

            {{-- FORM KHUSUS HIBAH (PAGE 26-27) --}}
            <template x-if="incomeCategory === 'hibah'">
                <div class="space-y-4 rounded-xl border border-gray-100 bg-gray-50/70 p-3.5 dark:border-gray-800 dark:bg-gray-800/40">
                    <div class="work-field">
                        <label for="hibah-donor">Nama Donatur / Instansi <span class="text-red-500">*</span></label>
                        <input id="hibah-donor" name="donor_name" type="text" x-model="donorName" placeholder="Contoh: PT. Sumber Makmur / Alumni 2010" required>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="work-field">
                            <label for="hibah-method">Metode Hibah <span class="text-red-500">*</span></label>
                            <select id="hibah-method" name="payment_method" x-model="incomeMethod" required>
                                <option value="Transfer">Transfer Bank</option>
                                <option value="Tunai">Tunai / Cash</option>
                            </select>
                        </div>
                        <div class="work-field" x-show="incomeMethod === 'Transfer'">
                            <label for="hibah-account">Rekening Tujuan</label>
                            <select id="hibah-account" name="account_id" x-model="targetAccountId">
                                <option value="">-- Pilih Rekening Sekolah --</option>
                                @foreach($schoolAccounts as $acc)
                                    <option value="{{ $acc->id }}">{{ $acc->name }} ({{ $acc->bank_name ?? $acc->type }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="work-field">
                        <label for="hibah-proof">Upload Bukti Hibah (PDF/JPG/PNG/HEIC)</label>
                        <input id="hibah-proof" name="proof" type="file" accept=".pdf,.jpg,.jpeg,.png,.heic"
                            class="block w-full text-xs text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-medium file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200">
                    </div>
                </div>
            </template>

            {{-- FORM KHUSUS LAINNYA (PAGE 28-29) --}}
            <template x-if="incomeCategory === 'lainnya'">
                <div class="space-y-4 rounded-xl border border-gray-100 bg-gray-50/70 p-3.5 dark:border-gray-800 dark:bg-gray-800/40">
                    <div class="work-field">
                        <label for="other-source">Sumber Pendapatan <span class="text-red-500">*</span></label>
                        <input id="other-source" name="source_funding" type="text" x-model="otherSource" placeholder="Contoh: Bagi Hasil Kantin Sekolah / Sewa Aula" required>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="work-field">
                            <label for="other-method">Metode Pembayaran <span class="text-red-500">*</span></label>
                            <select id="other-method" name="payment_method" x-model="incomeMethod" required>
                                <option value="Transfer">Transfer Bank</option>
                                <option value="Tunai">Tunai / Cash</option>
                            </select>
                        </div>
                        <div class="work-field" x-show="incomeMethod === 'Transfer'">
                            <label for="other-account">Pilih Rekening Transfer</label>
                            <select id="other-account" name="account_id" x-model="targetAccountId">
                                <option value="">-- Pilih Rekening Sekolah --</option>
                                @foreach($schoolAccounts as $acc)
                                    <option value="{{ $acc->id }}">{{ $acc->name }} ({{ $acc->bank_name ?? $acc->type }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="work-field">
                        <label for="other-proof">Upload Bukti Transaksi (PDF/JPG/PNG/HEIC)</label>
                        <input id="other-proof" name="proof" type="file" accept=".pdf,.jpg,.jpeg,.png,.heic"
                            class="block w-full text-xs text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-medium file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200">
                    </div>
                </div>
            </template>

            {{-- NOMINAL & TANGGAL (SEMUA KATEGORI) --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="work-field">
                    <label for="income-amount">Nominal Pemasukan (Rp) <span class="text-red-500">*</span></label>
                    <input id="income-amount" name="amount" type="text" inputmode="numeric" data-mask="currency" x-model="incomeAmount" placeholder="Contoh: 5.000.000" required class="mask-currency">
                </div>
                <div class="work-field">
                    <label for="income-date">Tanggal Terima <span class="text-red-500">*</span></label>
                    <input id="income-date" name="received_date" type="date" x-model="incomeDate" required>
                </div>
            </div>

            <div class="account-dialog-actions mt-5 pt-3 border-t border-gray-100 dark:border-gray-800">
                <button type="button" class="work-btn" @click="$refs.incomeModal.close()" :disabled="saving">Batal</button>
                <button type="submit" class="work-btn work-btn-primary" :disabled="saving">
                    <span x-show="!saving">Simpan Pemasukan</span>
                    <span x-show="saving" x-cloak>Menyimpan…</span>
                </button>
            </div>
        </form>
    </dialog>
</div>
@endsection
