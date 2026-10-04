@extends('layouts.app')

@section('content')
@php
    $expenseColumns = [
        ['key' => 'transaction_date', 'label' => 'Tanggal / Bukti', 'bold' => true, 'subKey' => 'reference_invoice'],
        ['key' => 'expense_name', 'label' => 'Uraian Belanja & Akun', 'bold' => true, 'subKey' => 'category_info'],
        ['key' => 'source_funding', 'label' => 'Sumber Dana', 'type' => 'badge'],
        ['key' => 'payment_method', 'label' => 'Metode'],
        ['key' => 'amount', 'label' => 'Jumlah Belanja', 'type' => 'currency', 'minimumFractionDigits' => 0],
        ['key' => 'tax_summary', 'label' => 'Pajak'],
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

        $categoryText = $exp->budgetCategory ? ($exp->budgetCategory->code . ' - ' . $exp->budgetCategory->name) : 'Operasional Umum';
        if ($exp->recipient_name) {
            $categoryText .= ' · Penerima: ' . $exp->recipient_name;
        }

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
            'payment_method' => $exp->payment_method ?? 'Tunai',
            'amount' => $amt,
            'tax_type' => $exp->tax_type ?? '',
            'tax_amount' => $tax,
            'is_tax_paid' => (bool) $exp->is_tax_paid,
            'tax_summary' => $taxStr,
            'update_url' => route('bos.belanja.update', $exp),
            'only_edit' => true,
        ];
    });
@endphp

<x-common.page-breadcrumb pageTitle="Pencatatan Belanja & Operasional Sekolah" label="Pengeluaran" />

<div class="work work-stack"
    x-data="{
        saving: false,
        createAmount: 0,
        createTaxType: '',
        createTaxAmount: 0,
        calculateCreateTax() {
            let amt = parseFloat(this.createAmount) || 0;
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
        editingItem: null,
        editCategoryId: '',
        editExpenseName: '',
        editAmount: 0,
        editDate: '',
        editFunding: 'BOS',
        editPaymentMethod: 'Tunai',
        editReference: '',
        editRecipient: '',
        editTaxType: '',
        editTaxAmount: 0,
        editIsTaxPaid: false,
        calculateEditTax() {
            let amt = parseFloat(this.editAmount) || 0;
            if (this.editTaxType === 'PPN') {
                this.editTaxAmount = Math.round(amt * 0.11);
            } else if (this.editTaxType === 'PPh 22') {
                this.editTaxAmount = Math.round(amt * 0.015);
            } else if (this.editTaxType === 'PPh 23') {
                this.editTaxAmount = Math.round(amt * 0.02);
            } else {
                this.editTaxAmount = 0;
            }
        },
        openEdit(row) {
            this.editingItem = row;
            this.editCategoryId = row.budget_category_id || '';
            this.editExpenseName = row.expense_name || '';
            this.editAmount = row.amount || 0;
            this.editDate = row.transaction_date_raw || '';
            this.editFunding = row.source_funding || 'BOS';
            this.editPaymentMethod = row.payment_method || 'Tunai';
            this.editReference = row.reference_invoice_raw || '';
            this.editRecipient = row.recipient_name_raw || '';
            this.editTaxType = row.tax_type || '';
            this.editTaxAmount = row.tax_amount || 0;
            this.editIsTaxPaid = row.is_tax_paid || false;
            this.$nextTick(() => {
                this.$refs.editExpenseModal.showModal();
            });
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
        <p class="work-muted">Pencatatan realisasi belanja operasional sekolah, BOS, yayasan, dan potongan pajak terkait.</p>
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

    {{-- MODAL TAMBAH PENGELUARAN --}}
    <dialog x-ref="createExpenseModal" class="account-dialog work" aria-labelledby="create-expense-title" aria-describedby="create-expense-help"
        @close="saving = false" @cancel="if (saving) $event.preventDefault()"
        @click="const bounds = $el.getBoundingClientRect(); if (!saving && ($event.clientX < bounds.left || $event.clientX > bounds.right || $event.clientY < bounds.top || $event.clientY > bounds.bottom)) $el.close()">
        <div class="account-dialog-head">
            <div class="min-w-0">
                <h2 id="create-expense-title" class="text-lg font-semibold text-gray-900 dark:text-white">Catat Pengeluaran Belanja</h2>
                <p id="create-expense-help" class="work-muted text-xs mt-0.5">Input transaksi realisasi pengeluaran dan perhitungan pajaknya.</p>
            </div>
            <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors p-1 rounded-md" @click="$refs.createExpenseModal.close()" :disabled="saving" aria-label="Tutup form">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <form method="POST" action="{{ route('bos.belanja.store') }}" @submit="saving = true" :aria-busy="saving" class="space-y-4">
            @csrf
            <input type="hidden" name="academic_year_id" value="{{ $activeYear?->id }}" />

            <div class="work-field">
                <label for="create-budget-category">Akun Anggaran RKAS</label>
                <select id="create-budget-category" name="budget_category_id">
                    <option value="">-- Pilih Akun Anggaran --</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->code }} - {{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="work-field">
                <label for="create-expense-name">Uraian Belanja / Kegiatan <span class="text-red-500">*</span></label>
                <input id="create-expense-name" name="expense_name" type="text" required placeholder="Contoh: Pembelian ATK Ujian Ganjil">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="work-field">
                    <label for="create-amount">Jumlah Belanja (Rp) <span class="text-red-500">*</span></label>
                    <input id="create-amount" name="amount" type="number" x-model="createAmount" @input="calculateCreateTax()" min="1" step="1000" required placeholder="Contoh: 1500000">
                </div>

                <div class="work-field">
                    <label for="create-date">Tanggal Belanja <span class="text-red-500">*</span></label>
                    <input id="create-date" name="transaction_date" type="date" value="{{ date('Y-m-d') }}" required>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="work-field">
                    <label for="create-funding">Sumber Dana <span class="text-red-500">*</span></label>
                    <select id="create-funding" name="source_funding" required>
                        <option value="BOS">BOS (Reguler/Kinerja)</option>
                        <option value="Yayasan">Yayasan (Swasta)</option>
                        <option value="Komite">Komite / Iuran</option>
                    </select>
                </div>

                <div class="work-field">
                    <label for="create-payment">Metode Pembayaran <span class="text-red-500">*</span></label>
                    <select id="create-payment" name="payment_method" required>
                        <option value="Tunai">Tunai / Cash</option>
                        <option value="Transfer">Transfer Bank</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="work-field">
                    <label for="create-reference">No. Bukti / Invoice</label>
                    <input id="create-reference" name="reference_invoice" type="text" placeholder="Contoh: NOTA-0922">
                </div>

                <div class="work-field">
                    <label for="create-recipient">Penerima Dana / Toko</label>
                    <input id="create-recipient" name="recipient_name" type="text" placeholder="Contoh: CV. Restu Agung">
                </div>
            </div>

            <div class="rounded-xl border border-gray-100 bg-gray-50/70 p-3.5 dark:border-gray-800 dark:bg-gray-800/30 space-y-3">
                <span class="block text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Potongan Pajak</span>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="work-field">
                        <label for="create-tax-type">Jenis Pajak</label>
                        <select id="create-tax-type" name="tax_type" x-model="createTaxType" @change="calculateCreateTax()">
                            <option value="">Tidak Ada Pajak</option>
                            <option value="PPN">PPN (11%)</option>
                            <option value="PPh 21">PPh 21 (Honor/Gaji)</option>
                            <option value="PPh 22">PPh 22 (Barang 1.5%)</option>
                            <option value="PPh 23">PPh 23 (Jasa 2%)</option>
                        </select>
                    </div>

                    <div class="work-field">
                        <label for="create-tax-amount">Nilai Pajak (Rp)</label>
                        <input id="create-tax-amount" name="tax_amount" type="number" x-model="createTaxAmount" readonly class="bg-gray-100 dark:bg-gray-800/60 text-gray-500">
                    </div>
                </div>

                <label class="flex items-center gap-2 cursor-pointer pt-1">
                    <input type="checkbox" name="is_tax_paid" value="1" class="h-4 w-4 rounded-sm border-gray-300 text-brand-600 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900">
                    <span class="text-xs text-gray-600 dark:text-gray-400 font-medium">Pajak sudah langsung disetor ke kas negara (SSP)</span>
                </label>
            </div>

            <div class="account-dialog-actions mt-5 pt-3 border-t border-gray-100 dark:border-gray-800">
                <button type="button" class="work-btn work-btn-secondary" @click="$refs.createExpenseModal.close()" :disabled="saving">
                    Batal
                </button>
                <button type="submit" class="work-btn work-btn-primary" :disabled="saving">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Simpan Transaksi Belanja
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

        <form method="POST" :action="editingItem ? editingItem.update_url : '#'" @submit="saving = true" :aria-busy="saving" class="space-y-4">
            @csrf
            @method('PUT')

            <div class="work-field">
                <label for="edit-budget-category">Akun Anggaran RKAS</label>
                <select id="edit-budget-category" name="budget_category_id" x-model="editCategoryId">
                    <option value="">-- Pilih Akun Anggaran --</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->code }} - {{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="work-field">
                <label for="edit-expense-name">Uraian Belanja / Kegiatan <span class="text-red-500">*</span></label>
                <input id="edit-expense-name" name="expense_name" type="text" x-model="editExpenseName" required>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="work-field">
                    <label for="edit-amount">Jumlah Belanja (Rp) <span class="text-red-500">*</span></label>
                    <input id="edit-amount" name="amount" type="number" x-model="editAmount" @input="calculateEditTax()" min="1" step="1000" required>
                </div>

                <div class="work-field">
                    <label for="edit-date">Tanggal Belanja <span class="text-red-500">*</span></label>
                    <input id="edit-date" name="transaction_date" type="date" x-model="editDate" required>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="work-field">
                    <label for="edit-funding">Sumber Dana <span class="text-red-500">*</span></label>
                    <select id="edit-funding" name="source_funding" x-model="editFunding" required>
                        <option value="BOS">BOS (Reguler/Kinerja)</option>
                        <option value="Yayasan">Yayasan (Swasta)</option>
                        <option value="Komite">Komite / Iuran</option>
                    </select>
                </div>

                <div class="work-field">
                    <label for="edit-payment">Metode Pembayaran <span class="text-red-500">*</span></label>
                    <select id="edit-payment" name="payment_method" x-model="editPaymentMethod" required>
                        <option value="Tunai">Tunai / Cash</option>
                        <option value="Transfer">Transfer Bank</option>
                    </select>
                </div>
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

            <div class="rounded-xl border border-gray-100 bg-gray-50/70 p-3.5 dark:border-gray-800 dark:bg-gray-800/30 space-y-3">
                <span class="block text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Potongan Pajak</span>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="work-field">
                        <label for="edit-tax-type">Jenis Pajak</label>
                        <select id="edit-tax-type" name="tax_type" x-model="editTaxType" @change="calculateEditTax()">
                            <option value="">Tidak Ada Pajak</option>
                            <option value="PPN">PPN (11%)</option>
                            <option value="PPh 21">PPh 21 (Honor/Gaji)</option>
                            <option value="PPh 22">PPh 22 (Barang 1.5%)</option>
                            <option value="PPh 23">PPh 23 (Jasa 2%)</option>
                        </select>
                    </div>

                    <div class="work-field">
                        <label for="edit-tax-amount">Nilai Pajak (Rp)</label>
                        <input id="edit-tax-amount" name="tax_amount" type="number" x-model="editTaxAmount" readonly class="bg-gray-100 dark:bg-gray-800/60 text-gray-500">
                    </div>
                </div>

                <label class="flex items-center gap-2 cursor-pointer pt-1">
                    <input type="checkbox" name="is_tax_paid" value="1" x-model="editIsTaxPaid" class="h-4 w-4 rounded-sm border-gray-300 text-brand-600 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900">
                    <span class="text-xs text-gray-600 dark:text-gray-400 font-medium">Pajak sudah langsung disetor ke kas negara (SSP)</span>
                </label>
            </div>

            <div class="account-dialog-actions mt-5 pt-3 border-t border-gray-100 dark:border-gray-800">
                <button type="button" class="work-btn work-btn-secondary" @click="$refs.editExpenseModal.close()" :disabled="saving">
                    Batal
                </button>
                <button type="submit" class="work-btn work-btn-primary" :disabled="saving">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </dialog>
</div>
@endsection
