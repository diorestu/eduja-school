@extends('layouts.app')

@section('content')
@php
    $billingColumns = [
        ['key' => 'name', 'label' => 'Nama Tagihan', 'bold' => true],
        ['key' => 'income_type_name', 'label' => 'Jenis Tagihan', 'type' => 'badge'],
        ['key' => 'academic_year_name', 'label' => 'Tahun Ajaran'],
        ['key' => 'amount', 'label' => 'Nominal', 'type' => 'currency', 'minimumFractionDigits' => 0],
        ['key' => 'billing_frequency', 'label' => 'Periode'],
        ['key' => 'due_date', 'label' => 'Deadline'],
        ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
        ['key' => 'aksi', 'label' => 'Aksi', 'sortable' => false, 'onlyEdit' => true],
    ];

    $billingRows = $billingItems->map(function ($item) {
        $statusLabels = [
            'active' => 'Aktif',
            'nonaktif' => 'Nonaktif',
            'closed' => 'Nonaktif',
            'draft' => 'Draft',
        ];

        return [
            'id' => $item->id,
            'name' => $item->name,
            'income_type_id' => $item->income_type_id,
            'income_type_name' => $item->incomeType?->name ?? 'SPP Komite',
            'academic_year_id' => $item->academic_year_id,
            'academic_year_name' => $item->academicYear?->year ?? '2025/2026',
            'amount' => (float) $item->amount,
            'allow_installment' => (bool) $item->allow_installment,
            'minimum_installment' => (float) $item->minimum_installment,
            'has_late_fee' => (bool) $item->has_late_fee,
            'late_fee_per_day' => (float) $item->late_fee_per_day,
            'late_fee_maximum' => (float) $item->late_fee_maximum,
            'billing_frequency' => $item->billing_frequency ?? 'Bulanan',
            'start_date_raw' => $item->start_date ? $item->start_date->format('Y-m-d') : '',
            'due_date' => $item->due_date ? \Carbon\Carbon::parse($item->due_date)->translatedFormat('d M Y') : 'Setiap Bulan',
            'due_date_raw' => $item->due_date ? \Carbon\Carbon::parse($item->due_date)->format('Y-m-d') : '',
            'target_type' => $item->target_type ?? 'school',
            'status' => $statusLabels[$item->status] ?? 'Aktif',
            'status_raw' => $item->status ?? 'active',
            'update_url' => route('finance.billing.update', $item),
            'delete_url' => route('finance.billing.destroy', $item),
            'only_edit' => true,
        ];
    });

    $arrearsStudentsData = $arrearsStudents->map(function ($s) {
        $statusLabels = [
            'belum bayar' => 'Belum Bayar',
            'cicil' => 'Cicil',
            'lunas' => 'Lunas',
            'menunggak' => 'Menunggak',
        ];

        return [
            'id' => $s['id'],
            'name' => $s['name'],
            'nis' => $s['nis'],
            'nisn' => $s['nisn'],
            'class_name' => $s['class_name'],
            'total_arrears' => (float) $s['total_arrears'],
            'status' => $statusLabels[$s['status']] ?? ucfirst($s['status']),
            'status_raw' => $s['status'],
            'invoices' => $s['invoices'] ?? [],
            'unpaid_invoices' => $s['unpaid_invoices'] ?? [],
            'payments' => $s['payments'] ?? [],
        ];
    });
@endphp

<x-common.page-breadcrumb pageTitle="Tagihan Murid & Komite" label="Tagihan" />

<div class="work work-stack"
    x-data="{
        activeTab: '{{ request('tab', 'billing') }}', // billing | arrears
        billingView: 'list', // list | detail
        selectedBilling: null,
        detailStatusFilter: '',
        detailClassFilter: '',
        detailSearchQuery: '',
        
        // Filter List Tagihan
        filterYear: '',
        filterStatus: '',
        searchBillQuery: '',

        // Flow Tunggakan Siswa State
        arrearsView: 'list', // list | detail
        selectedArrearsStudent: null,
        arrearsDetailTab: 'bills', // bills | payments
        arrearsSearchQuery: '',
        arrearsYearFilter: '',
        arrearsClassFilter: '',
        arrearsSortFilter: 'desc',
        arrearsMonthFilter: '',
        arrearsYearNumFilter: '',

        // Payment Modal & Confirmation State (Step 5, 6, 7)
        payStudent: null,
        payInvoiceId: '',
        paySelectedInvoice: null,
        payAmount: '',
        payMethod: 'Tunai',
        payAccountId: '',
        payDate: '{{ date('Y-m-d') }}',
        confirmingPayment: false,
        savingPay: false,

        // Wizard Form State
        wizardStep: 1,
        saving: false,
        formErrors: {},
        newBill: {
            income_type_id: '',
            income_type_name: '',
            academic_year_id: '{{ $academicYears->firstWhere('is_active')?->id ?? $academicYears->first()?->id }}',
            academic_year_name: '{{ $academicYears->firstWhere('is_active')?->year ?? '2025/2026' }}',
            name: '',
            amount: '',
            allow_installment: 'ya',
            minimum_installment: '100000',
            has_late_fee: 'tidak',
            late_fee_per_day: '5000',
            late_fee_maximum: '100000',
            billing_frequency: 'Bulanan',
            billing_day: 5,
            due_day: 25,
            start_date: '{{ date('Y-m-d') }}',
            due_date: '{{ date('Y-m-d', strtotime('+1 month')) }}',
            target_type: 'pilihan',
            target_department_id: '',
            target_generation: '',
            target_class_ids: [],
            target_class_id: '',
            target_student_id: ''
        },

        // Raw server items
        billingDetails: @js($billingDetails),
        arrearsStudents: @js($arrearsStudentsData),
        classesList: @js($classes),
        totalActiveStudents: {{ $students->count() }},

        // Modals from server session
        showSuccessModal: {{ session('success_modal') ? 'true' : 'false' }},
        showPaymentSuccessModal: {{ session('payment_success_modal') ? 'true' : 'false' }},

        // Edit Tagihan Modal State
        editingItem: null,
        editName: '',
        editIncomeTypeId: '',
        editAmount: '',
        editFrequency: 'Bulanan',
        editDueDate: '',
        editStatus: 'active',

        // TUNGGAKAN SISWA METHODS
        openStudentDetail(student) {
            this.selectedArrearsStudent = student;
            this.arrearsView = 'detail';
            this.arrearsDetailTab = 'bills';
        },

        backToArrearsList() {
            this.arrearsView = 'list';
            this.selectedArrearsStudent = null;
        },

        openPayModal(student, invoice = null) {
            this.payStudent = student;
            this.confirmingPayment = false;
            this.payMethod = 'Tunai';
            this.payAccountId = '';
            this.payDate = '{{ date('Y-m-d') }}';

            const unpaids = student.unpaid_invoices || student.invoices.filter(i => i.remaining_amount > 0);
            if (invoice) {
                this.payInvoiceId = invoice.id;
                this.paySelectedInvoice = invoice;
                this.payAmount = window.formatCurrencyMask ? window.formatCurrencyMask(invoice.remaining_amount) : invoice.remaining_amount;
            } else if (unpaids && unpaids.length > 0) {
                this.payInvoiceId = unpaids[0].id;
                this.paySelectedInvoice = unpaids[0];
                this.payAmount = window.formatCurrencyMask ? window.formatCurrencyMask(unpaids[0].remaining_amount) : unpaids[0].remaining_amount;
            } else {
                this.payInvoiceId = '';
                this.paySelectedInvoice = null;
                this.payAmount = 0;
            }

            this.$nextTick(() => {
                this.$refs.paymentModal.showModal();
            });
        },

        onInvoiceSelectChange(id) {
            this.payInvoiceId = id;
            if (this.payStudent && this.payStudent.invoices) {
                const inv = this.payStudent.invoices.find(i => i.id == id);
                if (inv) {
                    this.paySelectedInvoice = inv;
                    this.payAmount = window.formatCurrencyMask ? window.formatCurrencyMask(inv.remaining_amount) : inv.remaining_amount;
                }
            }
        },

        proceedToConfirmation() {
            if (!this.payInvoiceId) {
                alert('Pilih tagihan yang akan dibayar.');
                return;
            }
            const cleanPay = Number(String(this.payAmount || '').replace(/\D/g, ''));
            if (!this.payAmount || cleanPay <= 0) {
                alert('Nominal bayar harus lebih dari 0.');
                return;
            }
            if (this.paySelectedInvoice && cleanPay > Number(this.paySelectedInvoice.remaining_amount)) {
                alert('Nominal bayar tidak boleh melebihi sisa tagihan.');
                return;
            }
            if (this.payMethod === 'Transfer' && !this.payAccountId) {
                alert('Pilih rekening tujuan transfer sekolah.');
                return;
            }

            this.confirmingPayment = true;
        },

        closePaymentModal() {
            this.$refs.paymentModal.close();
            this.confirmingPayment = false;
            this.payStudent = null;
        },

        submitPayment() {
            this.savingPay = true;
            this.$refs.payForm.submit();
        },

        filteredArrearsStudents() {
            let list = [...this.arrearsStudents];
            if (this.arrearsSearchQuery.trim()) {
                const q = this.arrearsSearchQuery.toLowerCase();
                list = list.filter(s => s.name.toLowerCase().includes(q) || s.nis.toLowerCase().includes(q) || (s.nisn && s.nisn.toLowerCase().includes(q)));
            }
            if (this.arrearsClassFilter) {
                list = list.filter(s => s.class_name.toLowerCase().includes(this.arrearsClassFilter.toLowerCase()));
            }
            if (this.arrearsMonthFilter) {
                list = list.filter(s => s.invoices && s.invoices.some(inv => inv.period && inv.period.toLowerCase().includes(this.arrearsMonthFilter.toLowerCase())));
            }
            if (this.arrearsYearNumFilter) {
                list = list.filter(s => s.invoices && s.invoices.some(inv => inv.period && inv.period.includes(this.arrearsYearNumFilter)));
            }
            if (this.arrearsSortFilter === 'desc') {
                list.sort((a, b) => b.total_arrears - a.total_arrears);
            } else if (this.arrearsSortFilter === 'asc') {
                list.sort((a, b) => a.total_arrears - b.total_arrears);
            } else if (this.arrearsSortFilter === 'name') {
                list.sort((a, b) => a.name.localeCompare(b.name));
            } else if (this.arrearsSortFilter === 'nis') {
                list.sort((a, b) => a.nis.localeCompare(b.nis));
            }
            return list;
        },

        // TAGIHAN KOMITE WIZARD METHODS
        openCreateWizard() {
            this.wizardStep = 1;
            this.formErrors = {};
            this.newBill.name = '';
            this.newBill.amount = '';
            if (this.newBill.income_type_id) {
                this.updateIncomeTypeName();
            }
            this.$nextTick(() => {
                this.$refs.wizardModal.showModal();
            });
        },

        closeCreateWizard() {
            this.$refs.wizardModal.close();
            this.wizardStep = 1;
        },

        updateIncomeTypeName() {
            const selectEl = document.getElementById('wizard-income-type');
            if (selectEl && selectEl.selectedOptions[0]) {
                this.newBill.income_type_name = selectEl.selectedOptions[0].text;
                if (!this.newBill.name) {
                    this.newBill.name = this.newBill.income_type_name;
                }
            }
            const yearSelect = document.getElementById('wizard-academic-year');
            if (yearSelect && yearSelect.selectedOptions[0]) {
                this.newBill.academic_year_name = yearSelect.selectedOptions[0].text;
            }
        },

        goToStep2() {
            this.formErrors = {};
            if (!this.newBill.income_type_id) {
                this.formErrors.income_type_id = 'Pilih jenis tagihan.';
            }
            if (!this.newBill.name || !this.newBill.name.trim()) {
                this.formErrors.name = 'Nama tagihan wajib diisi.';
            }
            const cleanBillAmount = Number(String(this.newBill.amount || '').replace(/\D/g, ''));
            if (!this.newBill.amount || cleanBillAmount <= 0) {
                this.formErrors.amount = 'Nominal tagihan harus lebih dari 0.';
            }
            const cleanMinInst = Number(String(this.newBill.minimum_installment || '').replace(/\D/g, ''));
            if (this.newBill.allow_installment === 'ya' && (!this.newBill.minimum_installment || cleanMinInst <= 0)) {
                this.formErrors.minimum_installment = 'Minimal cicilan harus lebih dari 0.';
            }

            if (Object.keys(this.formErrors).length === 0) {
                this.updateIncomeTypeName();
                this.wizardStep = 2;
            }
        },

        goToStep3() {
            this.wizardStep = 3;
        },

        submitWizard() {
            this.saving = true;
            this.$refs.wizardForm.submit();
        },

        viewBillingDetail(item) {
            const fullDetail = this.billingDetails.find(b => b.id === item.id) || item;
            this.selectedBilling = fullDetail;
            this.billingView = 'detail';
            this.detailStatusFilter = '';
            this.detailClassFilter = '';
            this.detailSearchQuery = '';
        },

        backToList() {
            this.billingView = 'list';
            this.selectedBilling = null;
        },

        openEdit(row) {
            this.editingItem = row;
            this.editName = row.name || '';
            this.editIncomeTypeId = row.income_type_id || '';
            this.editAmount = window.formatCurrencyMask ? window.formatCurrencyMask(row.amount) : (row.amount || '');
            this.editFrequency = row.billing_frequency || 'Bulanan';
            this.editDueDate = row.due_date_raw || '';
            this.editStatus = row.status_raw || 'active';
            this.$nextTick(() => {
                this.$refs.editBillingModal.showModal();
            });
        },

        formatRupiah(val) {
            if (!val && val !== 0) return 'Rp 0';
            const clean = Number(String(val).replace(/\D/g, '')) || 0;
            return 'Rp ' + clean.toLocaleString('id-ID');
        },

        calculateTargetStudents() {
            if (this.newBill.target_type === 'school') {
                return this.totalActiveStudents || 64;
            }
            if (this.newBill.target_student_id) {
                return 1;
            }
            if (this.newBill.target_class_ids.length > 0) {
                return this.newBill.target_class_ids.length * 32;
            }
            if (this.newBill.target_class_id) {
                return 32;
            }
            return 64;
        },

        getTargetSummaryText() {
            if (this.newBill.target_type === 'school') {
                return 'Satu Sekolah (Seluruh Murid)';
            }
            if (this.newBill.target_student_id) {
                return 'Murid Tertentu';
            }
            if (this.newBill.target_class_ids.length > 0) {
                return 'Rombel ' + this.newBill.target_class_ids.map(id => {
                    const c = this.classesList.find(cls => cls.id == id);
                    return c ? c.name : 'Kelas';
                }).join(', ');
            }
            if (this.newBill.target_class_id) {
                const c = this.classesList.find(cls => cls.id == this.newBill.target_class_id);
                return c ? 'Rombel ' + c.name : 'Pilihan Rombel';
            }
            return 'Pilihan Tertentu';
        },

        filteredBillingRows() {
            let list = this.billingDetails;
            if (this.filterYear) {
                list = list.filter(item => item.academic_year_name.includes(this.filterYear));
            }
            if (this.filterStatus) {
                list = list.filter(item => (item.status || 'active') === this.filterStatus);
            }
            if (this.searchBillQuery.trim()) {
                const q = this.searchBillQuery.toLowerCase();
                list = list.filter(item => item.name.toLowerCase().includes(q) || item.income_type_name.toLowerCase().includes(q));
            }
            return list;
        },

        filteredDetailStudents() {
            if (!this.selectedBilling || !this.selectedBilling.students) return [];
            let list = this.selectedBilling.students;
            if (this.detailStatusFilter) {
                list = list.filter(s => s.status.toLowerCase() === this.detailStatusFilter.toLowerCase());
            }
            if (this.detailClassFilter) {
                list = list.filter(s => s.class_name.toLowerCase().includes(this.detailClassFilter.toLowerCase()));
            }
            if (this.detailSearchQuery.trim()) {
                const q = this.detailSearchQuery.toLowerCase();
                list = list.filter(s => s.name.toLowerCase().includes(q) || s.nis.toLowerCase().includes(q));
            }
            return list;
        }
    }">

    @if(session('success'))
        <div role="status" class="work-notice">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div role="status" class="work-notice work-error">{{ session('error') }}</div>
    @endif

    {{-- TOP BANNER INFO --}}
    <div class="rounded-xl border border-sky-100 bg-sky-50/70 p-4 dark:border-sky-900/40 dark:bg-sky-950/20 flex items-start gap-3.5 shadow-2xs">
        <div class="w-8 h-8 rounded-lg bg-sky-100 dark:bg-sky-900/60 flex items-center justify-center text-sky-600 dark:text-sky-400 shrink-0 mt-0.5">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
            </svg>
        </div>
        <div class="text-xs text-sky-900 dark:text-sky-200 leading-relaxed">
            <strong class="font-semibold text-sky-950 dark:text-sky-100">Data tagihan komite selalu real-time.</strong>
            <span> Setiap pembayaran akan langsung memperbarui status murid dan mengirim notifikasi ke orang tua.</span>
        </div>
    </div>

    {{-- TAGIHAN KOMITE --}}
    <div class="space-y-4">
        {{-- VIEW MODE: LIST TAGIHAN --}}
        <div x-show="billingView === 'list'" class="space-y-4">
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-xs dark:border-gray-800 dark:bg-gray-900">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Tagihan Komite</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Kelola tagihan komite untuk murid</p>
                    </div>

                    <button type="button" @click="openCreateWizard()"
                        class="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg bg-brand-600 hover:bg-brand-700 text-white text-xs font-semibold shadow-xs transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        <span>Buat Tagihan Baru</span>
                    </button>
                </div>

                {{-- FILTERS --}}
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-4 pt-4 border-t border-gray-100 dark:border-gray-800">
                    <div>
                        <label class="block text-[11px] font-medium text-gray-600 dark:text-gray-400 mb-1">Tahun Ajaran</label>
                        <select x-model="filterYear"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-1.5 text-xs text-gray-800 dark:text-gray-200 focus:outline-hidden focus:ring-1 focus:ring-brand-500">
                            <option value="">Semua Tahun Ajaran</option>
                            @foreach($academicYears as $ay)
                                <option value="{{ $ay->year }}">{{ $ay->year }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-[11px] font-medium text-gray-600 dark:text-gray-400 mb-1">Status</label>
                        <select x-model="filterStatus"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-1.5 text-xs text-gray-800 dark:text-gray-200 focus:outline-hidden focus:ring-1 focus:ring-brand-500">
                            <option value="">Semua Status</option>
                            <option value="active">Aktif</option>
                            <option value="nonaktif">Nonaktif</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[11px] font-medium text-gray-600 dark:text-gray-400 mb-1">Pencarian</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            </span>
                            <input type="text" x-model="searchBillQuery" placeholder="Cari nama tagihan..."
                                class="w-full pl-9 pr-3 py-1.5 text-xs rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200 placeholder-gray-400 focus:outline-hidden focus:ring-1 focus:ring-brand-500">
                        </div>
                    </div>
                </div>
            </div>

            {{-- TABLE TAGIHAN KOMITE (SECTION CLASS DATA-TABLE) --}}
            <section class="data-table rounded-xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-gray-900 overflow-hidden" aria-label="Daftar Tagihan Komite">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-gray-700 dark:text-gray-300">
                        <thead class="bg-gray-50/75 dark:bg-gray-800/60 text-[11px] font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider border-b border-gray-200 dark:border-gray-800">
                            <tr>
                                <th class="py-3 px-4 w-12 text-center">No</th>
                                <th class="py-3 px-4">Jenis Tagihan</th>
                                <th class="py-3 px-4 w-28">Tahun Ajaran</th>
                                <th class="py-3 px-4 w-36">Nominal</th>
                                <th class="py-3 px-4 w-28">Periode</th>
                                <th class="py-3 px-4 w-28 text-center">Status</th>
                                <th class="py-3 px-4 w-32 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                            <template x-for="(item, index) in filteredBillingRows()" :key="item.id">
                                <tr class="hover:bg-gray-50/70 dark:hover:bg-gray-800/30 transition-colors">
                                    <td class="py-3 px-4 text-center text-gray-400" x-text="index + 1"></td>
                                    <td class="py-3 px-4">
                                        <button type="button" @click="viewBillingDetail(item)"
                                            class="font-semibold text-gray-900 dark:text-white hover:text-brand-600 text-left flex flex-col">
                                            <span x-text="item.name"></span>
                                            <span class="text-[11px] text-gray-400 font-normal" x-text="item.income_type_name"></span>
                                        </button>
                                    </td>
                                    <td class="py-3 px-4 text-gray-600 dark:text-gray-300" x-text="item.academic_year_name"></td>
                                    <td class="py-3 px-4 font-semibold text-gray-900 dark:text-white tabular-nums" x-text="formatRupiah(item.amount)"></td>
                                    <td class="py-3 px-4" x-text="item.billing_frequency"></td>
                                    <td class="py-3 px-4 text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium"
                                            :class="item.status === 'active' || item.status === 'Aktif' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300'"
                                            x-text="item.status === 'active' || item.status === 'Aktif' ? 'Aktif' : 'Nonaktif'"></span>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <button type="button" @click="viewBillingDetail(item)"
                                                class="p-1 rounded-md text-brand-600 hover:text-brand-800 hover:bg-brand-50 dark:text-brand-400 dark:hover:bg-brand-950/50 transition-colors"
                                                title="Lihat status tagihan di murid">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                            </button>

                                            <button type="button" @click="openEdit(item)"
                                                class="p-1 rounded-md text-gray-500 hover:text-gray-800 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800 transition-colors"
                                                title="Ubah tagihan">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                            </button>

                                            <form method="POST" :action="'/finance/billing/' + item.id" onsubmit="return confirm('Apakah Anda yakin ingin menghapus tagihan ini? (Tagihan dengan transaksi tidak dapat dihapus)')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                    class="p-1 rounded-md text-red-500 hover:text-red-700 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/50 transition-colors"
                                                    title="Hapus tagihan">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            </template>

                            <template x-if="filteredBillingRows().length === 0">
                                <tr>
                                    <td colspan="7" class="py-8 text-center text-xs text-gray-500 dark:text-gray-400">
                                        Belum ada tagihan komite yang sesuai filter.
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div class="p-3.5 bg-gray-50/60 dark:bg-gray-800/40 border-t border-gray-200 dark:border-gray-800 flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                    <div class="flex items-center gap-2 text-sky-700 dark:text-sky-400">
                        <svg class="w-4 h-4 shrink-0 text-sky-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
                        <span>Pilih <strong>"Buat Tagihan Baru"</strong> untuk membuat tagihan komite baru.</span>
                    </div>
                    <span x-text="'Menampilkan 1 - ' + filteredBillingRows().length + ' data'"></span>
                </div>
            </section>

            {{-- STEP 10: CATATAN PENTING PENGATURAN TAGIHAN --}}
            <div class="rounded-xl border border-sky-200/80 bg-sky-50/50 p-4 dark:border-sky-900/50 dark:bg-sky-950/20 text-xs">
                <div class="font-semibold text-sky-900 dark:text-sky-200 mb-2">Catatan Penting Pengaturan Tagihan:</div>
                <ol class="list-decimal list-inside space-y-1 text-sky-800 dark:text-sky-300">
                    <li>Tagihan yang sudah digenerate akan otomatis muncul di portal orang tua / murid.</li>
                    <li>Denda keterlambatan dihitung otomatis oleh sistem per hari setelah jatuh tempo.</li>
                    <li>Cicilan hanya berlaku untuk tagihan yang mengaktifkan opsi cicilan.</li>
                    <li>Perubahan nominal setelah tagihan digenerate hanya berlaku untuk periode berikutnya.</li>
                </ol>
            </div>
        </div>

        {{-- VIEW MODE: DETAIL TAGIHAN DI MURID --}}
        <div x-show="billingView === 'detail'" x-cloak class="space-y-4">
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-xs dark:border-gray-800 dark:bg-gray-900">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <button type="button" @click="backToList()" class="inline-flex items-center gap-1.5 text-xs text-brand-600 hover:text-brand-700 font-semibold mb-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                            <span>Kembali ke Daftar Tagihan</span>
                        </button>
                        <h2 class="text-base font-bold text-gray-900 dark:text-white"
                            x-text="'Detail Tagihan - ' + (selectedBilling?.name || 'SPP Komite') + ' (' + (selectedBilling?.academic_year_name || '') + ')'"></h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Status pembayaran murid untuk tagihan ini.</p>
                    </div>

                    <div class="flex items-center gap-2">
                        <span class="px-2.5 py-1 rounded-md text-xs font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800"
                            x-text="'Paid: ' + (selectedBilling?.paid_count || 0)"></span>
                        <span class="px-2.5 py-1 rounded-md text-xs font-semibold bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300 border border-amber-200 dark:border-amber-800"
                            x-text="'Unpaid: ' + (selectedBilling?.unpaid_count || 0)"></span>
                    </div>
                </div>

                {{-- FILTERS DETAIL --}}
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-4 pt-4 border-t border-gray-100 dark:border-gray-800">
                    <div>
                        <label class="block text-[11px] font-medium text-gray-600 dark:text-gray-400 mb-1">Status Pembayaran</label>
                        <select x-model="detailStatusFilter"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-1.5 text-xs text-gray-800 dark:text-gray-200 focus:outline-hidden focus:ring-1 focus:ring-brand-500">
                            <option value="">Semua Status</option>
                            <option value="Paid">Paid (Lunas)</option>
                            <option value="Unpaid">Unpaid (Belum Bayar)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[11px] font-medium text-gray-600 dark:text-gray-400 mb-1">Rombel / Kelas</label>
                        <select x-model="detailClassFilter"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-1.5 text-xs text-gray-800 dark:text-gray-200 focus:outline-hidden focus:ring-1 focus:ring-brand-500">
                            <option value="">Semua Rombel</option>
                            @foreach($classes as $c)
                                <option value="{{ $c->name }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-[11px] font-medium text-gray-600 dark:text-gray-400 mb-1">Pencarian Murid</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            </span>
                            <input type="text" x-model="detailSearchQuery" placeholder="Cari nama murid..."
                                class="w-full pl-9 pr-3 py-1.5 text-xs rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200 placeholder-gray-400 focus:outline-hidden focus:ring-1 focus:ring-brand-500">
                        </div>
                    </div>
                </div>
            </div>

            {{-- DETAIL TABLE --}}
            <section class="rounded-xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-gray-900 overflow-hidden" aria-label="Status Tagihan Murid">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-gray-700 dark:text-gray-300">
                        <thead class="bg-gray-50/75 dark:bg-gray-800/60 text-[11px] font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider border-b border-gray-200 dark:border-gray-800">
                            <tr>
                                <th class="py-3 px-4 w-12 text-center">No</th>
                                <th class="py-3 px-4 w-28">NIS</th>
                                <th class="py-3 px-4">Nama Murid</th>
                                <th class="py-3 px-4 w-28">Rombel</th>
                                <th class="py-3 px-4 w-32">Nominal</th>
                                <th class="py-3 px-4 w-24 text-center">Status</th>
                                <th class="py-3 px-4 w-32 text-center">Tanggal Bayar</th>
                                <th class="py-3 px-4 w-20 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                            <template x-for="(s, sIdx) in filteredDetailStudents()" :key="s.id">
                                <tr class="hover:bg-gray-50/70 dark:hover:bg-gray-800/30 transition-colors">
                                    <td class="py-3 px-4 text-center text-gray-400" x-text="sIdx + 1"></td>
                                    <td class="py-3 px-4 font-mono text-gray-600 dark:text-gray-400" x-text="s.nis"></td>
                                    <td class="py-3 px-4 font-semibold text-gray-900 dark:text-white" x-text="s.name"></td>
                                    <td class="py-3 px-4 text-gray-600 dark:text-gray-400" x-text="s.class_name"></td>
                                    <td class="py-3 px-4 font-semibold text-gray-900 dark:text-white tabular-nums" x-text="formatRupiah(s.amount)"></td>
                                    <td class="py-3 px-4 text-center">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold"
                                            :class="s.status === 'Paid' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300'"
                                            x-text="s.status"></span>
                                    </td>
                                    <td class="py-3 px-4 text-center text-gray-600 dark:text-gray-400" x-text="s.payment_date"></td>
                                    <td class="py-3 px-4 text-center">
                                        <a href="{{ route('spp.transaksi.index', ['tab' => 'tunggakan']) }}"
                                            class="inline-flex items-center px-2 py-1 rounded text-xs font-semibold text-brand-600 hover:text-brand-800 hover:bg-brand-50 transition-colors">
                                            Lihat di Pembayaran
                                        </a>
                                    </td>
                                </tr>
                            </template>

                            <template x-if="filteredDetailStudents().length === 0">
                                <tr>
                                    <td colspan="8" class="py-8 text-center text-xs text-gray-500 dark:text-gray-400">
                                        Tidak ada murid yang sesuai filter.
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div class="p-3.5 bg-gray-50/60 dark:bg-gray-800/40 border-t border-gray-200 dark:border-gray-800 text-[11px] text-gray-500 dark:text-gray-400">
                    Status tagihan pada tiap murid, baik sudah dibayar (Paid) maupun belum (Unpaid). Jika sudah melewati deadline dan belum dibayar, tagihan tetap tercatat sebagai piutang.
                </div>
            </section>
        </div>
    </div>

    {{-- MODAL BUAT TAGIHAN WIZARD (STEP 4, 5, 6, 7) --}}
    <dialog x-ref="wizardModal" class="account-dialog work max-w-2xl w-full" aria-labelledby="wizard-title"
        @cancel="if (saving) $event.preventDefault()"
        @click="const bounds = $el.getBoundingClientRect(); if (!saving && ($event.clientX < bounds.left || $event.clientX > bounds.right || $event.clientY < bounds.top || $event.clientY > bounds.bottom)) closeCreateWizard()">
        
        <div class="account-dialog-head border-b border-gray-200 dark:border-gray-800 pb-3">
            <div class="min-w-0">
                <h2 id="wizard-title" class="text-base font-semibold text-gray-900 dark:text-white">Buat Tagihan Komite</h2>
                {{-- STEPPER HEADER (STEP 4, 6, 7) --}}
                <div class="flex items-center gap-3 mt-2 text-xs">
                    <div class="flex items-center gap-1.5" :class="wizardStep >= 1 ? 'text-brand-600 font-semibold' : 'text-gray-400'">
                        <span class="w-5 h-5 rounded-full flex items-center justify-center text-[10px]"
                            :class="wizardStep > 1 ? 'bg-emerald-100 text-emerald-700' : (wizardStep === 1 ? 'bg-brand-600 text-white' : 'bg-gray-200 text-gray-600')">
                            <template x-if="wizardStep > 1">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                            </template>
                            <template x-if="wizardStep <= 1">
                                <span>1</span>
                            </template>
                        </span>
                        <span>Informasi Dasar</span>
                    </div>
                    <span class="text-gray-300">&rsaquo;</span>
                    <div class="flex items-center gap-1.5" :class="wizardStep >= 2 ? 'text-brand-600 font-semibold' : 'text-gray-400'">
                        <span class="w-5 h-5 rounded-full flex items-center justify-center text-[10px]"
                            :class="wizardStep > 2 ? 'bg-emerald-100 text-emerald-700' : (wizardStep === 2 ? 'bg-brand-600 text-white' : 'bg-gray-200 text-gray-600')">
                            <template x-if="wizardStep > 2">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                            </template>
                            <template x-if="wizardStep <= 2">
                                <span>2</span>
                            </template>
                        </span>
                        <span>Target Murid</span>
                    </div>
                    <span class="text-gray-300">&rsaquo;</span>
                    <div class="flex items-center gap-1.5" :class="wizardStep === 3 ? 'text-brand-600 font-semibold' : 'text-gray-400'">
                        <span class="w-5 h-5 rounded-full flex items-center justify-center text-[10px]"
                            :class="wizardStep === 3 ? 'bg-brand-600 text-white' : 'bg-gray-200 text-gray-600'">
                            3
                        </span>
                        <span>Konfirmasi</span>
                    </div>
                </div>
            </div>
            <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors p-1 rounded-md" @click="closeCreateWizard()" :disabled="saving" aria-label="Tutup form">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <form x-ref="wizardForm" method="POST" action="{{ route('finance.billing.store') }}" class="my-4 space-y-4 text-xs">
            @csrf

            {{-- STEP 1: INFORMASI DASAR, DENDA & PERIODE --}}
            <div x-show="wizardStep === 1" class="space-y-3.5">
                <div class="work-field">
                    <label for="wizard-income-type" class="block font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Jenis Tagihan <span class="text-red-500">*</span>
                    </label>
                    <select id="wizard-income-type" name="income_type_id" x-model="newBill.income_type_id" @change="updateIncomeTypeName()" required
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-xs text-gray-900 dark:text-white">
                        <option value="">-- Pilih Jenis Tagihan (Komite) --</option>
                        @foreach($komiteIncomeTypes as $it)
                            <option value="{{ $it->id }}">{{ $it->name }}</option>
                        @endforeach
                    </select>
                    <p class="text-[11px] text-gray-400 mt-1">Hanya jenis pemasukan dengan kategori Komite yang ditampilkan</p>
                    <template x-if="formErrors.income_type_id">
                        <p class="text-[11px] text-red-600 font-medium mt-0.5" x-text="formErrors.income_type_id"></p>
                    </template>
                </div>

                <div class="work-field">
                    <label for="wizard-academic-year" class="block font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Tahun Ajaran <span class="text-red-500">*</span>
                    </label>
                    <select id="wizard-academic-year" name="academic_year_id" x-model="newBill.academic_year_id" @change="updateIncomeTypeName()" required
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-xs text-gray-900 dark:text-white">
                        @foreach($academicYears as $ay)
                            <option value="{{ $ay->id }}">{{ $ay->year }} {{ $ay->semester ? '- ' . $ay->semester : '' }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="work-field">
                    <label for="wizard-name" class="block font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Nama Tagihan <span class="text-red-500">*</span>
                    </label>
                    <input id="wizard-name" name="name" type="text" x-model="newBill.name" required placeholder="Contoh: SPP Komite"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-xs text-gray-900 dark:text-white">
                    <template x-if="formErrors.name">
                        <p class="text-[11px] text-red-600 font-medium mt-0.5" x-text="formErrors.name"></p>
                    </template>
                </div>

                <div class="work-field">
                    <label for="wizard-amount" class="block font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Nominal <span class="text-red-500">*</span>
                    </label>
                    <div class="relative flex items-center">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-xs font-semibold text-gray-500 pointer-events-none select-none z-10">Rp</span>
                        <input id="wizard-amount" name="amount" type="text" inputmode="numeric" data-mask="currency" x-model="newBill.amount" required placeholder="300.000"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 pr-16 !pl-11 py-2 text-xs text-gray-900 dark:text-white tabular-nums mask-currency" style="padding-left: 2.75rem !important;">
                        <span class="absolute inset-y-0 right-0 pr-3 flex items-center text-xs text-gray-400 pointer-events-none select-none">Rupiah</span>
                    </div>
                </div>

                <div class="work-field">
                    <label class="block font-medium text-gray-700 dark:text-gray-300 mb-1">Bisa Cicil?</label>
                    <div class="flex gap-4 mb-2">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="allow_installment" value="ya" x-model="newBill.allow_installment" class="text-brand-600 focus:ring-brand-500">
                            <span>Ya</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="allow_installment" value="tidak" x-model="newBill.allow_installment" class="text-brand-600 focus:ring-brand-500">
                            <span>Tidak</span>
                        </label>
                    </div>

                    <div x-show="newBill.allow_installment === 'ya'" class="mt-2">
                        <label for="wizard-min-installment" class="block font-medium text-gray-700 dark:text-gray-300 mb-1">Minimal Cicilan *</label>
                        <div class="relative flex items-center">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-xs font-semibold text-gray-500 pointer-events-none select-none z-10">Rp</span>
                            <input id="wizard-min-installment" name="minimum_installment" type="text" inputmode="numeric" data-mask="currency" x-model="newBill.minimum_installment" placeholder="100.000"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 pr-16 !pl-11 py-2 text-xs text-gray-900 dark:text-white tabular-nums mask-currency" style="padding-left: 2.75rem !important;">
                            <span class="absolute inset-y-0 right-0 pr-3 flex items-center text-xs text-gray-400 pointer-events-none select-none">Rupiah</span>
                        </div>
                    </div>
                </div>

                {{-- Pengaturan Denda dan Periode --}}
                <div class="p-3.5 rounded-xl border border-gray-100 bg-gray-50/80 dark:border-gray-800 dark:bg-gray-800/40 space-y-3">
                    <div class="work-field">
                        <label class="block font-medium text-gray-700 dark:text-gray-300 mb-1">Ada denda keterlambatan?</label>
                        <div class="flex gap-4">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="has_late_fee" value="ya" x-model="newBill.has_late_fee" class="text-brand-600 focus:ring-brand-500">
                                <span>Ya</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="has_late_fee" value="tidak" x-model="newBill.has_late_fee" class="text-brand-600 focus:ring-brand-500">
                                <span>Tidak</span>
                            </label>
                        </div>
                    </div>

                    <div x-show="newBill.has_late_fee === 'ya'" class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2 border-t border-gray-200 dark:border-gray-700/60">
                        <div>
                            <label for="wizard-late-day" class="block font-medium text-gray-700 dark:text-gray-300 mb-1">Nominal Denda per Hari *</label>
                            <div class="relative flex items-center">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-xs font-semibold text-gray-500 pointer-events-none select-none z-10">Rp</span>
                                <input id="wizard-late-day" name="late_fee_per_day" type="text" inputmode="numeric" data-mask="currency" x-model="newBill.late_fee_per_day" placeholder="5.000"
                                    class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 pr-16 !pl-10 py-1.5 text-xs text-gray-900 dark:text-white tabular-nums mask-currency" style="padding-left: 2.5rem !important;">
                                <span class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-xs text-gray-400 pointer-events-none select-none">Rupiah</span>
                            </div>
                        </div>
                        <div>
                            <label for="wizard-late-max" class="block font-medium text-gray-700 dark:text-gray-300 mb-1">Nominal Denda Maksimal *</label>
                            <div class="relative flex items-center">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-xs font-semibold text-gray-500 pointer-events-none select-none z-10">Rp</span>
                                <input id="wizard-late-max" name="late_fee_maximum" type="text" inputmode="numeric" data-mask="currency" x-model="newBill.late_fee_maximum" placeholder="100.000"
                                    class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 pr-16 !pl-10 py-1.5 text-xs text-gray-900 dark:text-white tabular-nums mask-currency" style="padding-left: 2.5rem !important;">
                                <span class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-xs text-gray-400 pointer-events-none select-none">Rupiah</span>
                            </div>
                        </div>
                    </div>

                    <div class="pt-2 border-t border-gray-200 dark:border-gray-700/60">
                        <label class="block font-medium text-gray-700 dark:text-gray-300 mb-1">Jenis Penagihan *</label>
                        <div class="flex gap-4">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="billing_frequency" value="Sekali" x-model="newBill.billing_frequency" class="text-brand-600 focus:ring-brand-500">
                                <span>Sekali</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="billing_frequency" value="Bulanan" x-model="newBill.billing_frequency" class="text-brand-600 focus:ring-brand-500">
                                <span>Bulanan</span>
                            </label>
                        </div>
                    </div>

                    <div x-show="newBill.billing_frequency === 'Bulanan'" class="p-3 bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700 space-y-2">
                        <span class="font-semibold text-gray-900 dark:text-white block">Pengaturan Jadwal (Bulanan)</span>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label for="wizard-bday" class="block text-[11px] text-gray-600 dark:text-gray-400 mb-1">Tanggal tiap bulan *</label>
                                <select id="wizard-bday" name="billing_day" x-model="newBill.billing_day"
                                    class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-1.5 text-xs">
                                    @for($d = 1; $d <= 31; $d++)
                                        <option value="{{ $d }}">{{ $d }}</option>
                                    @endfor
                                </select>
                                <p class="text-[10px] text-gray-400 mt-0.5" x-text="'Tagihan akan dibuat setiap tanggal ' + newBill.billing_day + ' setiap bulan'"></p>
                            </div>
                            <div>
                                <label for="wizard-dday" class="block text-[11px] text-gray-600 dark:text-gray-400 mb-1">Deadline pembayaran *</label>
                                <select id="wizard-dday" name="due_day" x-model="newBill.due_day"
                                    class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-1.5 text-xs">
                                    @for($d = 1; $d <= 31; $d++)
                                        <option value="{{ $d }}">{{ $d }}</option>
                                    @endfor
                                </select>
                                <p class="text-[10px] text-gray-400 mt-0.5" x-text="'Batas pembayaran setiap tanggal ' + newBill.due_day + ' setiap bulan'"></p>
                            </div>
                        </div>
                    </div>

                    <div x-show="newBill.billing_frequency === 'Sekali'" class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2">
                        <div>
                            <label for="wizard-start" class="block text-[11px] text-gray-600 dark:text-gray-400 mb-1">Tanggal Mulai</label>
                            <input id="wizard-start" name="start_date" type="date" x-model="newBill.start_date"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-1.5 text-xs">
                        </div>
                        <div>
                            <label for="wizard-due" class="block text-[11px] text-gray-600 dark:text-gray-400 mb-1">Deadline Pembayaran</label>
                            <input id="wizard-due" name="due_date" type="date" x-model="newBill.due_date"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-1.5 text-xs">
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t border-gray-100 dark:border-gray-800">
                    <button type="button" class="work-btn" @click="closeCreateWizard()">Batal</button>
                    <button type="button" class="work-btn work-btn-primary" @click="goToStep2()">Lanjutkan</button>
                </div>
            </div>

            {{-- STEP 2: PILIH TARGET TAGIHAN --}}
            <div x-show="wizardStep === 2" x-cloak class="space-y-4">
                <div class="work-field">
                    <label class="block font-medium text-gray-700 dark:text-gray-300 mb-1">Target Tagihan <span class="text-red-500">*</span></label>
                    <div class="space-y-2">
                        <label class="flex items-start gap-2.5 p-3 rounded-lg border border-gray-200 dark:border-gray-700 cursor-pointer"
                            :class="newBill.target_type === 'school' ? 'bg-brand-50/60 border-brand-300 dark:bg-brand-950/30' : ''">
                            <input type="radio" name="target_type" value="school" x-model="newBill.target_type" class="mt-0.5 text-brand-600 focus:ring-brand-500">
                            <div>
                                <strong class="font-semibold text-gray-900 dark:text-white">Satu Sekolah</strong>
                                <p class="text-gray-500 dark:text-gray-400 text-[11px]">Terapkan ke seluruh murid aktif di sekolah.</p>
                            </div>
                        </label>

                        <label class="flex items-start gap-2.5 p-3 rounded-lg border border-gray-200 dark:border-gray-700 cursor-pointer"
                            :class="newBill.target_type === 'pilihan' ? 'bg-brand-50/60 border-brand-300 dark:bg-brand-950/30' : ''">
                            <input type="radio" name="target_type" value="pilihan" x-model="newBill.target_type" class="mt-0.5 text-brand-600 focus:ring-brand-500">
                            <div>
                                <strong class="font-semibold text-gray-900 dark:text-white">Pilihan</strong>
                                <p class="text-gray-500 dark:text-gray-400 text-[11px]">Pilih jurusan/angkatan/rombel/murid tertentu.</p>
                            </div>
                        </label>
                    </div>
                </div>

                <div x-show="newBill.target_type === 'pilihan'" class="p-3.5 rounded-xl border border-gray-100 bg-gray-50/80 dark:border-gray-800 dark:bg-gray-800/40 space-y-3">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label for="wizard-dept" class="block font-medium text-gray-700 dark:text-gray-300 mb-1">Jurusan</label>
                            <select id="wizard-dept" name="target_department_id" x-model="newBill.target_department_id"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-1.5 text-xs">
                                <option value="">Semua Jurusan</option>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="wizard-gen" class="block font-medium text-gray-700 dark:text-gray-300 mb-1">Angkatan</label>
                            <input id="wizard-gen" name="target_generation" type="text" x-model="newBill.target_generation" placeholder="Semua Angkatan (Contoh: 2024)"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-1.5 text-xs">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label for="wizard-class" class="block font-medium text-gray-700 dark:text-gray-300 mb-1">Rombel / Kelas</label>
                            <select id="wizard-class" name="target_class_id" x-model="newBill.target_class_id"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-1.5 text-xs">
                                <option value="">Semua Rombel</option>
                                @foreach($classes as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="wizard-student" class="block font-medium text-gray-700 dark:text-gray-300 mb-1">Murid</label>
                            <select id="wizard-student" name="target_student_id" x-model="newBill.target_student_id"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-1.5 text-xs">
                                <option value="">Semua Murid</option>
                                @foreach($students as $st)
                                    <option value="{{ $st->id }}">{{ $st->name }} ({{ $st->nis ?? '-' }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 text-[11px] text-sky-800 dark:text-sky-300 bg-sky-50 dark:bg-sky-950/40 p-2.5 rounded-lg border border-sky-100 dark:border-sky-900/40">
                        <svg class="w-4 h-4 text-sky-600 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
                        <span x-text="'Tagihan akan dibuat untuk murid target: ' + getTargetSummaryText() + '.'"></span>
                    </div>
                </div>

                <div class="flex justify-between items-center pt-3 border-t border-gray-100 dark:border-gray-800">
                    <button type="button" class="work-btn" @click="wizardStep = 1">Kembali</button>
                    <button type="button" class="work-btn work-btn-primary" @click="goToStep3()">Lanjutkan</button>
                </div>
            </div>

            {{-- STEP 3: KONFIRMASI DAN GENERATE TAGIHAN --}}
            <div x-show="wizardStep === 3" x-cloak class="space-y-4">
                <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4 bg-gray-50/70 dark:bg-gray-800/50 space-y-2">
                    <h3 class="font-bold text-gray-900 dark:text-white text-xs uppercase tracking-wider mb-2">Ringkasan Tagihan</h3>
                    
                    <div class="grid grid-cols-2 gap-y-1.5 text-xs">
                        <span class="text-gray-500">Jenis Tagihan</span>
                        <span class="font-semibold text-gray-900 dark:text-white" x-text="': ' + (newBill.income_type_name || newBill.name)"></span>

                        <span class="text-gray-500">Tahun Ajaran</span>
                        <span class="font-medium text-gray-800 dark:text-gray-200" x-text="': ' + newBill.academic_year_name"></span>

                        <span class="text-gray-500">Nominal</span>
                        <span class="font-bold text-gray-900 dark:text-white tabular-nums" x-text="': ' + formatRupiah(newBill.amount)"></span>

                        <span class="text-gray-500">Cicilan</span>
                        <span class="text-gray-800 dark:text-gray-200" x-text="newBill.allow_installment === 'ya' ? ': Ya (Minimal ' + formatRupiah(newBill.minimum_installment) + ')' : ': Tidak'"></span>

                        <span class="text-gray-500">Denda</span>
                        <span class="text-gray-800 dark:text-gray-200" x-text="newBill.has_late_fee === 'ya' ? ': ' + formatRupiah(newBill.late_fee_per_day) + ' per hari (Maks. ' + formatRupiah(newBill.late_fee_maximum) + ')' : ': Tidak ada'"></span>

                        <span class="text-gray-500">Periode</span>
                        <span class="text-gray-800 dark:text-gray-200" x-text="newBill.billing_frequency === 'Bulanan' ? ': Bulanan (Tgl ' + newBill.billing_day + ', deadline tgl ' + newBill.due_day + ')' : ': Sekali'"></span>

                        <span class="text-gray-500">Target</span>
                        <span class="font-medium text-gray-800 dark:text-gray-200" x-text="': ' + getTargetSummaryText()"></span>

                        <span class="text-gray-500">Jumlah Murid</span>
                        <span class="font-bold text-brand-600 dark:text-brand-400" x-text="': ' + calculateTargetStudents() + ' murid'"></span>
                    </div>
                </div>

                <div class="rounded-xl border border-sky-100 bg-sky-50 dark:border-sky-900/40 dark:bg-sky-950/30 p-3.5 flex items-start gap-2.5">
                    <svg class="w-4 h-4 text-sky-600 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
                    <p class="text-[11px] text-sky-900 dark:text-sky-200 leading-relaxed">
                        Sistem akan otomatis membuat tagihan sesuai pengaturan untuk setiap bulan selama tahun ajaran <span x-text="newBill.academic_year_name"></span>.
                    </p>
                </div>

                <div class="flex justify-between items-center pt-3 border-t border-gray-100 dark:border-gray-800">
                    <button type="button" class="work-btn" @click="wizardStep = 2" :disabled="saving">Kembali</button>
                    <button type="button" class="work-btn work-btn-primary bg-emerald-600 hover:bg-emerald-700 border-emerald-600 text-white" @click="submitWizard()" :disabled="saving">
                        <span x-show="!saving">Generate Tagihan</span>
                        <span x-show="saving" x-cloak>Menyimpan &amp; Generate…</span>
                    </button>
                </div>
            </div>
        </form>
    </dialog>

    {{-- STEP 8: MODAL NOTIFIKASI BUAT TAGIHAN BERHASIL --}}
    <dialog x-ref="successModal" class="account-dialog work max-w-sm w-full text-center"
        x-init="if (showSuccessModal) { $nextTick(() => { $refs.successModal.showModal(); }); }">
        <div class="py-3 px-2 flex flex-col items-center">
            <div class="w-14 h-14 rounded-full bg-emerald-100 dark:bg-emerald-900/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mb-3.5">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <h3 class="text-base font-bold text-gray-900 dark:text-white mb-1">
                {{ session('success_title', 'Tagihan berhasil dibuat') }}
            </h3>
            <p class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed mb-4">
                {{ session('success_message', 'Tagihan komite untuk ' . (session('target_student_count') ?? 'seluruh') . ' murid telah berhasil dibuat dan disimpan ke dalam sistem.') }}
            </p>

            <div class="w-full text-left p-3 rounded-xl border border-emerald-100 bg-emerald-50/70 dark:border-emerald-900/40 dark:bg-emerald-950/20 mb-5 space-y-1">
                <div class="flex items-center gap-2 text-emerald-800 dark:text-emerald-300 font-semibold text-xs">
                    <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.77-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.299.045-.677.063-1.092-.069-.252-.08-.575-.187-.988-.365-1.739-.751-2.874-2.502-2.961-2.617-.087-.116-.708-.94-.708-1.793s.448-1.273.607-1.446c.159-.173.346-.217.462-.217l.332.006c.106.005.249-.04.39.298.144.347.491 1.2.534 1.287.043.087.072.188.014.304-.058.116-.087.188-.173.289l-.26.304c-.087.086-.177.18-.076.354.101.174.449.741.964 1.201.662.591 1.221.774 1.394.86.174.086.275.072.376-.044.101-.116.433-.506.549-.68.116-.173.231-.145.39-.087s1.011.477 1.184.564.289.13.332.202c.043.073.043.419-.101.824z"/></svg>
                    <span>Notifikasi WhatsApp</span>
                </div>
                <div class="text-[11px] text-emerald-900/90 dark:text-emerald-200/90 pl-6 leading-relaxed">
                    <p>Notifikasi akan dikirim ke orang tua:</p>
                    <p>&bull; H-7 sebelum deadline pembayaran</p>
                    <p>&bull; Saat deadline pembayaran</p>
                </div>
            </div>

            <button type="button" @click="$refs.successModal.close()" class="w-full work-btn work-btn-primary py-2 text-xs font-semibold justify-center">
                Lihat Daftar Tagihan
            </button>
        </div>
    </dialog>

    {{-- MODAL UBAH ITEM TAGIHAN --}}
    <dialog x-ref="editBillingModal" class="account-dialog work" aria-labelledby="edit-billing-modal-title"
        @close="saving = false" @cancel="if (saving) $event.preventDefault()"
        @click="const bounds = $el.getBoundingClientRect(); if (!saving && ($event.clientX < bounds.left || $event.clientX > bounds.right || $event.clientY < bounds.top || $event.clientY > bounds.bottom)) $el.close()">
        <div class="account-dialog-head">
            <div class="min-w-0">
                <h2 id="edit-billing-modal-title" class="text-base font-semibold text-gray-900 dark:text-white">Ubah Tagihan</h2>
                <p class="work-muted text-xs mt-0.5">Perbarui informasi dan status tagihan.</p>
            </div>
            <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors p-1 rounded-md" @click="$refs.editBillingModal.close()" :disabled="saving" aria-label="Tutup form">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <form method="POST" :action="editingItem ? editingItem.update_url : ''" @submit="saving = true" :aria-busy="saving" class="space-y-3.5 my-3 text-xs">
            @csrf
            @method('PUT')

            <div class="work-field">
                <label for="edit-name" class="block font-medium text-gray-700 dark:text-gray-300 mb-1">Nama Tagihan <span class="text-red-500">*</span></label>
                <input id="edit-name" name="name" x-model="editName" type="text" maxlength="160" required
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-1.5 text-xs text-gray-900 dark:text-white">
            </div>

            <div class="work-field">
                <label for="edit-amount" class="block font-medium text-gray-700 dark:text-gray-300 mb-1">Nominal <span class="text-red-500">*</span></label>
                <div class="relative flex items-center">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-xs font-semibold text-gray-500 pointer-events-none select-none z-10">Rp</span>
                    <input id="edit-amount" name="amount" x-model="editAmount" type="text" inputmode="numeric" data-mask="currency" required placeholder="0"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 pr-3 !pl-11 py-1.5 text-xs text-gray-900 dark:text-white tabular-nums mask-currency" style="padding-left: 2.75rem !important;">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="work-field">
                    <label for="edit-freq" class="block font-medium text-gray-700 dark:text-gray-300 mb-1">Periode</label>
                    <select id="edit-freq" name="billing_frequency" x-model="editFrequency"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-1.5 text-xs text-gray-900 dark:text-white">
                        <option value="Bulanan">Bulanan</option>
                        <option value="Sekali">Sekali</option>
                    </select>
                </div>
                <div class="work-field">
                    <label for="edit-status" class="block font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
                    <select id="edit-status" name="status" x-model="editStatus"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-1.5 text-xs text-gray-900 dark:text-white">
                        <option value="active">Aktif</option>
                        <option value="nonaktif">Nonaktif</option>
                        <option value="closed">Selesai</option>
                    </select>
                </div>
            </div>

            <div class="account-dialog-actions mt-4 pt-3 border-t border-gray-100 dark:border-gray-800 flex justify-end gap-2">
                <button type="button" class="work-btn" @click="$refs.editBillingModal.close()" :disabled="saving">Batal</button>
                <button type="submit" class="work-btn work-btn-primary" :disabled="saving">
                    <span x-show="!saving">Simpan Perubahan</span>
                    <span x-show="saving" x-cloak>Menyimpan…</span>
                </button>
            </div>
        </form>
    </dialog>
</div>
@endsection
