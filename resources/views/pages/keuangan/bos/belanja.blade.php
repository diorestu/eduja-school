@extends('layouts.app')

@section('content')
@php
    $expenseColumns = [
        ['key' => 'transaction_date', 'label' => 'Tanggal / Bukti', 'bold' => true, 'subKey' => 'reference_invoice'],
        ['key' => 'expense_name', 'label' => 'Uraian Belanja & Akun', 'bold' => true, 'subKey' => 'category_info'],
        ['key' => 'source_funding', 'label' => 'Sumber Dana', 'type' => 'badge'],
        ['key' => 'payment_method', 'label' => 'Metode'],
        ['key' => 'amount', 'label' => 'Jumlah Belanja', 'type' => 'currency', 'minimumFractionDigits' => 0],
        ['key' => 'wallet_or_account', 'label' => 'Dompet / Rekening'],
        ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
        ['key' => 'aksi', 'label' => 'Aksi', 'sortable' => false, 'onlyEdit' => true],
    ];

    $totalBelanja = 0;
    $totalPajak = 0;
    $currentMonthBelanja = 0;
    $nowMonth = now()->format('Y-m');

    $expenseRows = $expenses->map(function ($exp) use (&$totalBelanja, &$totalPajak, &$currentMonthBelanja, $nowMonth) {
        $amt = (float) $exp->amount;
        $tax = (float) $exp->tax_amount;
        $totalBelanja += $amt;
        $totalPajak += $tax;

        if ($exp->transaction_date && $exp->transaction_date->format('Y-m') === $nowMonth) {
            $currentMonthBelanja += $amt;
        }

        $taxStr = '-';
        if ($tax > 0) {
            $taxStr = ($exp->tax_type ?? 'Pajak') . ': Rp ' . number_format($tax, 0, ',', '.') . ($exp->is_tax_paid ? ' (Disetor)' : ' (Belum Setor)');
        }

        $categoryText = $exp->budgetCategory ? ($exp->budgetCategory->code . ' - ' . $exp->budgetCategory->name) : ($exp->expenseType?->name ?? 'Operasional Umum');
        if ($exp->recipient_name) {
            $categoryText .= ' · Penerima: ' . $exp->recipient_name;
        }

        $statusLabels = [
            'pending' => 'Pending Approval',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
        ];

        return [
            'id' => $exp->id,
            'expense_name' => $exp->expense_name,
            'budget_category_id' => $exp->budget_category_id ?? '',
            'category_info' => $categoryText,
            'reference_invoice' => $exp->reference_invoice ? 'No. Bukti: ' . $exp->reference_invoice : 'Tanpa No. Bukti',
            'reference_invoice_raw' => $exp->reference_invoice ?? '',
            'recipient_name_raw' => $exp->recipient_name ?? '',
            'transaction_date' => $exp->transaction_date ? $exp->transaction_date->translatedFormat('d M Y') : '-',
            'transaction_date_raw' => $exp->transaction_date ? $exp->transaction_date->format('Y-m-d') : date('Y-m-d'),
            'source_funding' => $exp->source_funding ?? 'BOS',
            'expense_type_id' => $exp->expense_type_id ?? '',
            'virtual_wallet_id' => $exp->virtual_wallet_id ?? '',
            'payment_method' => $exp->payment_method ?? 'Tunai',
            'account_id' => $exp->account_id ?? '',
            'amount' => $amt,
            'wallet_or_account' => $exp->virtualWallet?->name ?? ($exp->account?->name ?? 'Kas Sekolah'),
            'tax_type' => $exp->tax_type ?? '',
            'tax_amount' => $tax,
            'is_tax_paid' => (bool) $exp->is_tax_paid,
            'tax_summary' => $taxStr,
            'status' => $statusLabels[$exp->status] ?? ucfirst($exp->status ?? 'pending'),
            'status_raw' => $exp->status ?? 'pending',
            'update_url' => route('bos.belanja.update', $exp),
            'only_edit' => true,
        ];
    });

    $bosRemainingBudget = 50000000; // fallback default
@endphp

<x-common.page-breadcrumb pageTitle="Pencatatan Belanja & Operasional Sekolah" label="Pengeluaran" />

<div class="work work-stack"
    x-data="{
        saving: false,
        expenseCategory: 'Operasional', // Operasional | BOS | Transfer Alokasi
        createAmount: 0,
        createOpAmount: 0,
        createOpWalletId: '{{ $virtualWallets->first()?->id ?? '' }}',
        createBosAccountId: '{{ $schoolAccounts->first()?->id ?? '' }}',
        createTaxType: '',
        createTaxAmount: 0,
        accountsMap: @js($schoolAccounts->pluck('current_balance', 'id')),
        walletsMap: @js($virtualWallets->pluck('nominal', 'id')),
        // BOS specific state
        bosYear: {{ date('Y') }},
        bosSource: 'BOS Reguler',
        bosComponent: 'Belanja barang',
        bosRemainingBudget: 50000000,
        parseNum(val) {
            return parseFloat(String(val || '').replace(/\D/g, '')) || 0;
        },
        calculateCreateTax() {
            let amt = this.parseNum(this.createAmount);
            if (this.createTaxType === 'PPN') {
                this.createTaxAmount = Math.round(amt * 0.11);
            } else if (this.createTaxType === 'PPh 22') {
                this.createTaxAmount = Math.round(amt * 0.015);
            } else if (this.createTaxType === 'PPh 23') {
                this.createTaxAmount = Math.round(amt * 0.02);
            } else {
                this.createTaxAmount = 0;
            }
        },
        get selectedOpWalletBalance() {
            return parseFloat(this.walletsMap[this.createOpWalletId] ?? 0);
        },
        get isOpWalletBalanceInsufficient() {
            let amt = this.parseNum(this.createOpAmount);
            return Boolean(this.createOpWalletId && amt > this.selectedOpWalletBalance);
        },
        get selectedBosAccountBalance() {
            return parseFloat(this.accountsMap[this.createBosAccountId] ?? 0);
        },
        get isBosAccountBalanceInsufficient() {
            let amt = this.parseNum(this.createAmount);
            return Boolean(this.createBosAccountId && amt > this.selectedBosAccountBalance);
        },
        get isCreateInsufficient() {
            if (this.expenseCategory === 'BOS') {
                return this.isBosAccountBalanceInsufficient;
            } else {
                return this.isOpWalletBalanceInsufficient;
            }
        },
        editingItem: null,
        editCategoryId: '',
        editExpenseTypeId: '',
        editVirtualWalletId: '',
        editExpenseName: '',
        editAmount: 0,
        editDate: '',
        editFunding: 'BOS',
        editPaymentMethod: 'Transfer',
        editAccountId: '',
        editReference: '',
        editRecipient: '',
        editTaxType: '',
        editTaxAmount: 0,
        editIsTaxPaid: false,
        get editSelectedAccountBalance() {
            return parseFloat(this.accountsMap[this.editAccountId] ?? 0);
        },
        get isEditAccountBalanceInsufficient() {
            let amt = this.parseNum(this.editAmount);
            return Boolean(this.editFunding === 'BOS' && this.editAccountId && amt > this.editSelectedAccountBalance);
        },
        get editSelectedWalletBalance() {
            return parseFloat(this.walletsMap[this.editVirtualWalletId] ?? 0);
        },
        get isEditWalletBalanceInsufficient() {
            let amt = this.parseNum(this.editAmount);
            return Boolean(this.editFunding === 'Komite' && this.editVirtualWalletId && amt > this.editSelectedWalletBalance);
        },
        get isEditInsufficient() {
            if (this.editFunding === 'BOS') {
                return this.isEditAccountBalanceInsufficient;
            } else if (this.editFunding === 'Komite') {
                return this.isEditWalletBalanceInsufficient;
            }
            return false;
        },
        openEdit(row) {
            this.editingItem = row;
            this.editCategoryId = row.budget_category_id || '';
            this.editExpenseTypeId = row.expense_type_id || '';
            this.editVirtualWalletId = row.virtual_wallet_id || '';
            this.editExpenseName = row.expense_name || '';
            this.editAmount = window.formatCurrencyMask ? window.formatCurrencyMask(row.amount) : (row.amount || 0);
            this.editDate = row.transaction_date_raw || '';
            this.editFunding = row.source_funding || 'BOS';
            this.editPaymentMethod = row.payment_method || 'Transfer';
            this.editAccountId = row.account_id || '';
            this.editReference = row.reference_invoice_raw || '';
            this.editRecipient = row.recipient_name_raw || '';
            this.editTaxType = row.tax_type || '';
            this.editTaxAmount = row.tax_amount || 0;
            this.editIsTaxPaid = row.is_tax_paid || false;
            this.$nextTick(() => {
                this.$refs.editExpenseModal.showModal();
            });
        },
        formatRupiah(val) {
            return 'Rp ' + Number(val || 0).toLocaleString('id-ID');
        }
    }"
    @table-edit="openEdit($event.detail)">

    @if(session('success'))
        <div role="status" class="work-notice">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs font-semibold text-rose-700 dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-300">
            {{ session('error') }}
        </div>
    @endif

    @if($errors->any())
        <div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs font-semibold text-rose-700 dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-300">
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- METRICS SUMMARY --}}
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4 mb-2">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-xs dark:border-gray-800 dark:bg-gray-900">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Total Belanja</p>
            <p class="mt-1 text-xl font-bold tracking-tight tabular-nums text-gray-900 dark:text-white">Rp {{ number_format($totalBelanja, 0, ',', '.') }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-xs dark:border-gray-800 dark:bg-gray-900">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-rose-600 dark:text-rose-400">Belanja Bulan Ini</p>
            <p class="mt-1 text-xl font-bold tracking-tight tabular-nums text-rose-600 dark:text-rose-400">Rp {{ number_format($currentMonthBelanja, 0, ',', '.') }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-xs dark:border-gray-800 dark:bg-gray-900">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-amber-600 dark:text-amber-400">Pajak Terpotong</p>
            <p class="mt-1 text-xl font-bold tracking-tight tabular-nums text-amber-600 dark:text-amber-400">Rp {{ number_format($totalPajak, 0, ',', '.') }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-xs dark:border-gray-800 dark:bg-gray-900">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Buku Pengeluaran</p>
            <p class="mt-1 text-xl font-bold tracking-tight tabular-nums text-gray-900 dark:text-white">{{ $expenses->count() }} transaksi</p>
        </div>
    </div>

    {{-- HEADER ACTION --}}
    <div class="work-head mb-0">
        <p class="work-muted">Pencatatan realisasi belanja operasional komite sekolah, BOS, yayasan, dan potongan pajak terkait.</p>
        <button type="button" class="work-btn work-btn-primary" @click="$refs.createExpenseModal.showModal()">
            <svg class="w-4 h-4 mr-1.5 -ml-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Catat Pengeluaran
        </button>
    </div>

    {{-- DATA TABLE --}}
    <x-common.data-table
        :rows="$expenseRows"
        :columns="$expenseColumns"
        caption="Buku Pembantu Pengeluaran Belanja"
        search-label="Cari uraian belanja, akun, bukti, atau toko..."
        row-label="pengeluaran"
        subtitle="Daftar realisasi belanja dan transaksi pengeluaran operasional"
        :show-actions="false"
        :show-avatar="false"
        :exportable="true"
        export-label="Export Belanja"
        empty-message="Belum ada transaksi pengeluaran belanja."
        empty-hint="Pilih Catat Pengeluaran untuk mencatat transaksi pertama."
    />

    {{-- MODAL TAMBAH PENGELUARAN (FLOW INPUT PENGELUARAN KOMITE & BOS PAGE 30-33) --}}
    <dialog x-ref="createExpenseModal" class="account-dialog work" style="max-width: 44rem; width: 100%;" aria-labelledby="create-expense-title" aria-describedby="create-expense-help"
        @close="saving = false" @cancel="if (saving) $event.preventDefault()"
        @click="const bounds = $el.getBoundingClientRect(); if (!saving && ($event.clientX < bounds.left || $event.clientX > bounds.right || $event.clientY < bounds.top || $event.clientY > bounds.bottom)) $el.close()">
        <div class="account-dialog-head">
            <div class="min-w-0">
                <h2 id="create-expense-title" class="text-lg font-semibold text-gray-900 dark:text-white">Catat Pengeluaran Sekolah</h2>
                <p id="create-expense-help" class="work-muted text-xs mt-0.5">Input transaksi belanja Operasional (Komite), BOS, atau Transfer Alokasi.</p>
            </div>
            <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors p-1 rounded-md" @click="$refs.createExpenseModal.close()" :disabled="saving" aria-label="Tutup form">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <form method="POST" action="{{ route('bos.belanja.store') }}" enctype="multipart/form-data" @submit="if (isCreateInsufficient) { $event.preventDefault(); return false; } saving = true" :aria-busy="saving" class="space-y-4">
            @csrf
            <input type="hidden" name="academic_year_id" value="{{ $activeYear?->id }}" />

            {{-- 1. Pilih Kategori (dropdown: Operasional, BOS, Transfer Alokasi) --}}
            <div class="work-field">
                <label for="create-expense-category">Kategori Pengeluaran <span class="text-red-500">*</span></label>
                <select id="create-expense-category" name="category" x-model="expenseCategory" required>
                    <option value="Operasional">Operasional (Komite / Biaya Rutin)</option>
                    <option value="BOS">BOS (Bantuan Operasional Sekolah)</option>
                    <option value="Transfer Alokasi">Transfer Alokasi (Antar Dompet / Rekening)</option>
                </select>
            </div>

            {{-- FORM KHUSUS OPERASIONAL KOMITE (PAGE 30-31) --}}
            <template x-if="expenseCategory === 'Operasional' || expenseCategory === 'Transfer Alokasi'">
                <div class="space-y-4 rounded-xl border border-gray-100 bg-gray-50/70 p-3.5 dark:border-gray-800 dark:bg-gray-800/40">
                    <input type="hidden" name="source_funding" value="Komite" />

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="work-field">
                            <label for="create-op-date">Tanggal Transaksi <span class="text-red-500">*</span></label>
                            <input id="create-op-date" name="transaction_date" type="date" value="{{ date('Y-m-d') }}" required>
                        </div>
                        <div class="work-field">
                            <label for="create-op-type">Jenis Pengeluaran Komite <span class="text-red-500">*</span></label>
                            <select id="create-op-type" name="expense_type_id" required>
                                <option value="" disabled selected>-- Pilih Jenis Pengeluaran Komite --</option>
                                @foreach($komiteExpenseTypes as $et)
                                    <option value="{{ $et->id }}">{{ $et->code }} - {{ $et->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="work-field">
                        <label for="create-op-name">Deskripsi / Uraian Belanja <span class="text-red-500">*</span></label>
                        <input id="create-op-name" name="expense_name" type="text" required placeholder="Contoh: Konsumsi rapat komite sekolah">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="work-field">
                            <label for="create-op-amount">Nominal <span class="text-red-500">*</span></label>
                            <div class="relative flex items-center">
                                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-xs font-semibold text-gray-500 pointer-events-none select-none z-10">Rp</span>
                                <input id="create-op-amount" name="amount" type="text" inputmode="numeric" data-mask="currency" x-model="createOpAmount" required placeholder="Contoh: 750.000" class="w-full !pl-11 pr-3 py-2 text-xs rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white tabular-nums mask-currency" style="padding-left: 2.75rem !important;">
                            </div>
                        </div>
                        <div class="work-field">
                            <label for="create-op-recipient">Penerima Dana</label>
                            <input id="create-op-recipient" name="recipient_name" type="text" placeholder="Contoh: Toko Berkah / Catering Ibu Ani">
                        </div>
                    </div>

                    {{-- Asal Dana Komite: HANYA dari Dompet Virtual --}}
                    <div class="work-field">
                        <label for="create-op-wallet">Asal Dana: Dompet Virtual <span class="text-red-500">*</span></label>
                        <select id="create-op-wallet" name="virtual_wallet_id" x-model="createOpWalletId" required>
                            <option value="" disabled>-- Pilih Dompet Virtual Sumber Dana --</option>
                            @foreach($virtualWallets as $vw)
                                <option value="{{ $vw->id }}">{{ $vw->name }} (Saldo: Rp {{ number_format($vw->nominal, 0, ',', '.') }})</option>
                            @endforeach
                        </select>
                        <input type="hidden" name="payment_method" value="Transfer" />
                    </div>

                    {{-- Warning Saldo Dompet Virtual Tidak Mencukupi --}}
                    <div x-show="isOpWalletBalanceInsufficient" x-cloak
                        class="p-3 rounded-lg border border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-300 text-xs flex items-center gap-2">
                        <svg class="w-4 h-4 shrink-0 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        <span><strong>Peringatan Saldo Dompet:</strong> Saldo dompet virtual tidak mencukupi untuk nominal pengeluaran ini (Saldo: <span x-text="formatRupiah(selectedOpWalletBalance)"></span>, Pengeluaran: <span x-text="formatRupiah(parseNum(createOpAmount))"></span>).</span>
                    </div>

                    <div class="work-field">
                        <label for="create-op-proof">Upload Bukti Transaksi (Kuitansi / Nota)</label>
                        <input id="create-op-proof" name="proof" type="file" accept=".pdf,.jpg,.jpeg,.png,.heic"
                            class="block w-full text-xs text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-medium file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200">
                    </div>
                </div>
            </template>

            {{-- FORM KHUSUS PENGELUARAN BOS (PAGE 32-33) --}}
            <template x-if="expenseCategory === 'BOS'">
                <div class="space-y-4 rounded-xl border border-gray-100 bg-gray-50/70 p-3.5 dark:border-gray-800 dark:bg-gray-800/40">
                    <input type="hidden" name="source_funding" value="BOS" />

                    {{-- Step 1: Pilih Tahun BOS, Sumber Dana BOS & Komponen BOS --}}
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="work-field">
                            <label for="create-bos-year">Tahun BOS <span class="text-red-500">*</span></label>
                            <input id="create-bos-year" name="bos_year" type="number" min="2020" max="2099" x-model="bosYear" required>
                        </div>
                        <div class="work-field">
                            <label for="create-bos-source">Sumber Dana BOS <span class="text-red-500">*</span></label>
                            <select id="create-bos-source" name="bos_source" x-model="bosSource">
                                <option value="BOS Reguler">BOS Reguler</option>
                                <option value="BOS Kinerja">BOS Kinerja</option>
                                <option value="BOS Afirmasi">BOS Afirmasi</option>
                            </select>
                        </div>
                        <div class="work-field">
                            <label for="create-bos-comp">Komponen BOS <span class="text-red-500">*</span></label>
                            <select id="create-bos-comp" name="bos_component" x-model="bosComponent">
                                <option value="Belanja pegawai">Belanja pegawai</option>
                                <option value="Belanja barang">Belanja barang</option>
                                <option value="Belanja modal">Belanja modal</option>
                                <option value="Pembelajaran">Pembelajaran</option>
                                <option value="Pemeliharaan">Pemeliharaan</option>
                            </select>
                        </div>
                    </div>

                    {{-- Akun / Kategori Anggaran BOS (Hanya Jenis Pengeluaran BOS) --}}
                    <div class="work-field">
                        <label for="create-bos-cat">Jenis Pengeluaran BOS (Akun Anggaran RKAS) <span class="text-red-500">*</span></label>
                        <select id="create-bos-cat" name="budget_category_id" required>
                            <option value="" disabled selected>-- Pilih Jenis Pengeluaran BOS --</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->code }} - {{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Warning jika Anggaran Tidak Cukup (Page 32-33) --}}
                    <div x-show="parseNum(createAmount) > bosRemainingBudget" x-cloak
                        class="p-3 rounded-lg border border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-300 text-xs flex items-center gap-2">
                        <svg class="w-4 h-4 shrink-0 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        <span><strong>Peringatan Anggaran:</strong> Sisa anggaran BOS komponen ini tidak mencukupi (Sisa: <span x-text="formatRupiah(bosRemainingBudget)"></span>).</span>
                    </div>

                    <div class="work-field">
                        <label for="create-bos-name">Deskripsi / Uraian Kegiatan <span class="text-red-500">*</span></label>
                        <input id="create-bos-name" name="expense_name" type="text" required placeholder="Contoh: Pengadaan bahan ajar praktikum murid">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="work-field">
                            <label for="create-bos-amount">Nominal <span class="text-red-500">*</span></label>
                            <div class="relative flex items-center">
                                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-xs font-semibold text-gray-500 pointer-events-none select-none z-10">Rp</span>
                                <input id="create-bos-amount" name="amount" type="text" inputmode="numeric" data-mask="currency" x-model="createAmount" @input="calculateCreateTax()" required placeholder="Contoh: 4.500.000" class="w-full !pl-11 pr-3 py-2 text-xs rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white tabular-nums mask-currency" style="padding-left: 2.75rem !important;">
                            </div>
                        </div>
                        <div class="work-field">
                            <label for="create-bos-date">Tanggal Transaksi <span class="text-red-500">*</span></label>
                            <input id="create-bos-date" name="transaction_date" type="date" value="{{ date('Y-m-d') }}" required>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="work-field">
                            <label for="create-bos-recipient">Penerima Dana / Rekanan <span class="text-red-500">*</span></label>
                            <input id="create-bos-recipient" name="recipient_name" type="text" placeholder="Contoh: CV. Restu Agung" required>
                        </div>
                        <div class="work-field">
                            <label for="create-bos-account">Rekening Pembayaran BOS <span class="text-red-500">*</span></label>
                            <select id="create-bos-account" name="account_id" x-model="createBosAccountId" required>
                                <option value="" disabled>-- Pilih Rekening Pembayaran --</option>
                                @foreach($schoolAccounts as $acc)
                                    <option value="{{ $acc->id }}">
                                        {{ $acc->name }} ({{ $acc->bank_name ? $acc->bank_name . ' - ' . $acc->account_number : $acc->type }}) — Saldo: Rp {{ number_format($acc->current_balance, 0, ',', '.') }}
                                    </option>
                                @endforeach
                            </select>
                            <input type="hidden" name="payment_method" value="Transfer" />
                        </div>
                    </div>

                    {{-- Warning Saldo Rekening Sekolah Tidak Mencukupi --}}
                    <div x-show="isBosAccountBalanceInsufficient" x-cloak
                        class="p-3 rounded-lg border border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-300 text-xs flex items-center gap-2">
                        <svg class="w-4 h-4 shrink-0 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        <span><strong>Peringatan Saldo Rekening:</strong> Saldo rekening pembayaran tidak mencukupi untuk pengeluaran ini (Saldo: <span x-text="formatRupiah(selectedBosAccountBalance)"></span>, Pengeluaran: <span x-text="formatRupiah(parseNum(createAmount))"></span>).</span>
                    </div>

                    {{-- CHECKLIST DOKUMEN BELANJA BOS SESUAI FLOW (PAGE 33) --}}
                    <div class="border-t border-gray-200 dark:border-gray-700/60 pt-3 space-y-3">
                        <span class="block text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300">Kelengkapan Dokumen Belanja BOS</span>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                            {{-- Dokumen Belanja --}}
                            <div class="p-2.5 rounded-lg border border-gray-200 dark:border-gray-700 space-y-1.5">
                                <span class="font-semibold block text-[11px] text-gray-500">1. Bukti Dokumen Belanja</span>
                                <label class="flex items-center gap-1.5 cursor-pointer"><input type="checkbox" name="document_checklist[]" value="Kuitansi" checked class="rounded text-brand-600"> Kuitansi</label>
                                <label class="flex items-center gap-1.5 cursor-pointer"><input type="checkbox" name="document_checklist[]" value="Faktur/Nota" checked class="rounded text-brand-600"> Faktur / Nota Pembelian</label>
                                <label class="flex items-center gap-1.5 cursor-pointer"><input type="checkbox" name="document_checklist[]" value="e-Billing" class="rounded text-brand-600"> e-Billing</label>
                                <label class="flex items-center gap-1.5 cursor-pointer"><input type="checkbox" name="document_checklist[]" value="BAST" class="rounded text-brand-600"> BAST</label>
                            </div>

                            {{-- Bukti Perpajakan --}}
                            <div class="p-2.5 rounded-lg border border-gray-200 dark:border-gray-700 space-y-1.5">
                                <span class="font-semibold block text-[11px] text-gray-500">2. Bukti Perpajakan</span>
                                <label class="flex items-center gap-1.5 cursor-pointer"><input type="checkbox" name="document_checklist[]" value="SSP" class="rounded text-brand-600"> Surat Setoran Pajak</label>
                                <label class="flex items-center gap-1.5 cursor-pointer"><input type="checkbox" name="document_checklist[]" value="Faktur Pajak" class="rounded text-brand-600"> Faktur / Nota Pajak</label>
                            </div>

                            {{-- Dokumen Pendukung Kegiatan --}}
                            <div class="p-2.5 rounded-lg border border-gray-200 dark:border-gray-700 space-y-1.5">
                                <span class="font-semibold block text-[11px] text-gray-500">3. Pendukung Kegiatan</span>
                                <label class="flex items-center gap-1.5 cursor-pointer"><input type="checkbox" name="document_checklist[]" value="Daftar Hadir" class="rounded text-brand-600"> Daftar Hadir</label>
                                <label class="flex items-center gap-1.5 cursor-pointer"><input type="checkbox" name="document_checklist[]" value="Honor/Transport" class="rounded text-brand-600"> Daftar Penerima Honor</label>
                                <label class="flex items-center gap-1.5 cursor-pointer"><input type="checkbox" name="document_checklist[]" value="SK/Tugas" class="rounded text-brand-600"> SK / Surat Tugas</label>
                                <label class="flex items-center gap-1.5 cursor-pointer"><input type="checkbox" name="document_checklist[]" value="Dokumentasi" class="rounded text-brand-600"> Dokumentasi Foto</label>
                            </div>
                        </div>

                        <div class="work-field pt-2">
                            <label for="create-bos-proof">Upload File Berkas Dokumen (PDF gabungan)</label>
                            <input id="create-bos-proof" name="proof" type="file" accept=".pdf,.jpg,.jpeg,.png,.heic"
                                class="block w-full text-xs text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-medium file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200">
                        </div>
                    </div>
                </div>
            </template>

            <div class="account-dialog-actions mt-5 pt-3 border-t border-gray-100 dark:border-gray-800">
                <button type="button" class="work-btn" @click="$refs.createExpenseModal.close()" :disabled="saving">Batal</button>
                <button type="submit" class="work-btn work-btn-primary disabled:opacity-50 disabled:cursor-not-allowed" :disabled="saving || isCreateInsufficient">
                    <span x-show="!saving && !isCreateInsufficient">Ajukan Pengeluaran</span>
                    <span x-show="!saving && isCreateInsufficient" x-cloak>Saldo Tidak Mencukupi</span>
                    <span x-show="saving" x-cloak>Menyimpan…</span>
                </button>
            </div>
        </form>
    </dialog>

    {{-- MODAL UBAH PENGELUARAN --}}
    <dialog x-ref="editExpenseModal" class="account-dialog work" aria-labelledby="edit-expense-title" aria-describedby="edit-expense-help"
        @close="saving = false" @cancel="if (saving) $event.preventDefault()"
        @click="const bounds = $el.getBoundingClientRect(); if (!saving && ($event.clientX < bounds.left || $event.clientX > bounds.right || $event.clientY < bounds.top || $event.clientY > bounds.bottom)) $el.close()">
        <div class="account-dialog-head">
            <div class="min-w-0">
                <h2 id="edit-expense-title" class="text-lg font-semibold text-gray-900 dark:text-white">Ubah Transaksi Pengeluaran</h2>
                <p id="edit-expense-help" class="work-muted text-xs mt-0.5">Perbarui rincian belanja operasional dan pajak.</p>
            </div>
            <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors p-1 rounded-md" @click="$refs.editExpenseModal.close()" :disabled="saving" aria-label="Tutup form">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <form method="POST" :action="editingItem ? editingItem.update_url : '#'" @submit="if (isEditInsufficient) { $event.preventDefault(); return false; } saving = true" :aria-busy="saving" class="space-y-4">
            @csrf
            @method('PUT')

            {{-- 1. Sumber Dana --}}
            <div class="work-field">
                <label for="edit-funding">Sumber Dana <span class="text-red-500">*</span></label>
                <select id="edit-funding" name="source_funding" x-model="editFunding" required>
                    <option value="BOS">BOS (Bantuan Operasional Sekolah)</option>
                    <option value="Komite">Komite (Operasional Rutin)</option>
                </select>
            </div>

            {{-- Jenis Pengeluaran / Akun Anggaran sesuai Sumber Dana --}}
            <div class="work-field" x-show="editFunding === 'BOS'">
                <label for="edit-budget-category">Jenis Pengeluaran BOS (Akun Anggaran RKAS) <span class="text-red-500">*</span></label>
                <select id="edit-budget-category" name="budget_category_id" x-model="editCategoryId" :required="editFunding === 'BOS'">
                    <option value="">-- Pilih Jenis Pengeluaran BOS --</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->code }} - {{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="work-field" x-show="editFunding === 'Komite'" x-cloak>
                <label for="edit-expense-type">Jenis Pengeluaran Komite <span class="text-red-500">*</span></label>
                <select id="edit-expense-type" name="expense_type_id" x-model="editExpenseTypeId" :required="editFunding === 'Komite'">
                    <option value="">-- Pilih Jenis Pengeluaran Komite --</option>
                    @foreach($komiteExpenseTypes as $et)
                        <option value="{{ $et->id }}">{{ $et->code }} - {{ $et->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="work-field">
                <label for="edit-expense-name">Uraian Belanja / Kegiatan <span class="text-red-500">*</span></label>
                <input id="edit-expense-name" name="expense_name" x-model="editExpenseName" type="text" required>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="work-field">
                    <label for="edit-amount">Jumlah Belanja <span class="text-red-500">*</span></label>
                    <div class="relative flex items-center">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-xs font-semibold text-gray-500 pointer-events-none select-none z-10">Rp</span>
                        <input id="edit-amount" name="amount" type="text" inputmode="numeric" data-mask="currency" x-model="editAmount" required class="w-full !pl-11 pr-3 py-2 text-xs rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white tabular-nums mask-currency" style="padding-left: 2.75rem !important;">
                    </div>
                </div>
                <div class="work-field">
                    <label for="edit-date">Tanggal Belanja <span class="text-red-500">*</span></label>
                    <input id="edit-date" name="transaction_date" type="date" x-model="editDate" required>
                </div>
            </div>

            {{-- Asal Dana: Dompet Virtual untuk Komite, Rekening untuk BOS --}}
            <div class="work-field" x-show="editFunding === 'Komite'" x-cloak>
                <label for="edit-virtual-wallet">Asal Dana: Dompet Virtual <span class="text-red-500">*</span></label>
                <select id="edit-virtual-wallet" name="virtual_wallet_id" x-model="editVirtualWalletId" :required="editFunding === 'Komite'">
                    <option value="">-- Pilih Dompet Virtual Sumber Dana --</option>
                    @foreach($virtualWallets as $vw)
                        <option value="{{ $vw->id }}">{{ $vw->name }} (Saldo: Rp {{ number_format($vw->nominal, 0, ',', '.') }})</option>
                    @endforeach
                </select>
            </div>

            {{-- Warning Saldo Dompet Virtual pada Edit --}}
            <div x-show="isEditWalletBalanceInsufficient" x-cloak
                class="p-3 rounded-lg border border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-300 text-xs flex items-center gap-2">
                <svg class="w-4 h-4 shrink-0 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <span><strong>Peringatan Saldo Dompet:</strong> Saldo dompet virtual tidak mencukupi untuk jumlah belanja ini (Saldo: <span x-text="formatRupiah(editSelectedWalletBalance)"></span>, Belanja: <span x-text="formatRupiah(parseNum(editAmount))"></span>).</span>
            </div>

            <div class="work-field" x-show="editFunding === 'BOS'">
                <label for="edit-account">Rekening Pembayaran BOS <span class="text-red-500">*</span></label>
                <select id="edit-account" name="account_id" x-model="editAccountId" :required="editFunding === 'BOS'">
                    <option value="">-- Pilih Rekening Sekolah --</option>
                    @foreach($schoolAccounts as $acc)
                        <option value="{{ $acc->id }}">
                            {{ $acc->name }} ({{ $acc->bank_name ? $acc->bank_name . ' - ' . $acc->account_number : $acc->type }}) — Saldo: Rp {{ number_format($acc->current_balance, 0, ',', '.') }}
                        </option>
                    @endforeach
                </select>
                <input type="hidden" name="payment_method" value="Transfer" />
            </div>

            {{-- Warning Saldo Rekening Sekolah pada Edit --}}
            <div x-show="isEditAccountBalanceInsufficient" x-cloak
                class="p-3 rounded-lg border border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-300 text-xs flex items-center gap-2">
                <svg class="w-4 h-4 shrink-0 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <span><strong>Peringatan Saldo Rekening:</strong> Saldo rekening pembayaran tidak mencukupi untuk jumlah belanja ini (Saldo: <span x-text="formatRupiah(editSelectedAccountBalance)"></span>, Belanja: <span x-text="formatRupiah(parseNum(editAmount))"></span>).</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="work-field">
                    <label for="edit-reference">No. Bukti / Invoice</label>
                    <input id="edit-reference" name="reference_invoice" type="text" x-model="editReference">
                </div>
                <div class="work-field">
                    <label for="edit-recipient">Penerima Dana / Toko</label>
                    <input id="edit-recipient" name="recipient_name" type="text" x-model="editRecipient">
                </div>
            </div>

            <div class="account-dialog-actions mt-5 pt-3 border-t border-gray-100 dark:border-gray-800">
                <button type="button" class="work-btn" @click="$refs.editExpenseModal.close()" :disabled="saving">Batal</button>
                <button type="submit" class="work-btn work-btn-primary disabled:opacity-50 disabled:cursor-not-allowed" :disabled="saving || isEditInsufficient">
                    <span x-show="!saving && !isEditInsufficient">Simpan Perubahan</span>
                    <span x-show="!saving && isEditInsufficient" x-cloak>Saldo Tidak Mencukupi</span>
                    <span x-show="saving" x-cloak>Menyimpan…</span>
                </button>
            </div>
        </form>
    </dialog>
</div>
@endsection
