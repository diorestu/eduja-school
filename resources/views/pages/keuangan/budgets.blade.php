@extends('layouts.app')

@section('content')
@php
    $defaultYear = $budgetYears->firstWhere('status', 'active') ?? $budgetYears->first();
    $defaultYearId = $defaultYear?->id ?? '';

    $statusLabels = [
        'pending' => 'Menunggu',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak',
        'draft' => 'Draft',
    ];

    $budgetRows = $budgetPlans->map(function ($plan, $index) use ($statusLabels) {
        $statusKey = strtolower($plan->status ?? 'pending');
        $isApproved = in_array($statusKey, ['approved', 'disetujui']);

        return [
            'id' => $plan->id,
            'no' => $index + 1,
            'budget_year_id' => (string) $plan->budget_year_id,
            'budget_year_name' => $plan->budgetYear?->name ?? 'Semua Periode',
            'source_funding' => $plan->source_funding,
            'program_name' => $plan->program_name,
            'activity_name' => $plan->activity_name,
            'amount' => (float) $plan->amount,
            'status' => $statusLabels[$statusKey] ?? ucfirst($statusKey),
            'status_raw' => $statusKey,
            'revision_url' => route('finance.budgets.revisions.store', $plan),
            'update_url' => route('finance.budgets.update', $plan),
            'delete_url' => route('finance.budgets.destroy', $plan),
            'revisions_count' => $plan->revisions->count(),
            'only_edit' => true,
            'allow_delete' => ! $isApproved,
        ];
    });

    $budgetColumns = [
        ['key' => 'no', 'label' => 'No'],
        ['key' => 'program_name', 'label' => 'Nama Program', 'bold' => true, 'subKey' => 'activity_name'],
        ['key' => 'source_funding', 'label' => 'Sumber Dana', 'type' => 'badge'],
        ['key' => 'amount', 'label' => 'Total Anggaran', 'type' => 'currency', 'minimumFractionDigits' => 0],
        ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
        ['key' => 'aksi', 'label' => 'Aksi', 'sortable' => false, 'onlyEdit' => true, 'allowDelete' => true],
    ];

    $allSources = array_values(array_unique(array_filter(array_merge(
        ['Semua', 'BOS Reguler', 'Komite', 'BOS Kinerja', 'BOP', 'DAK', 'Lainnya'],
        $budgetPlans->pluck('source_funding')->all()
    ))));
@endphp

{{-- Breadcrumb compliant with tests & screenshot --}}
<div class="mb-4">
    <x-common.page-breadcrumb pageTitle="Susun & Revisi Anggaran" label="Perencanaan Anggaran" />
</div>

<div class="work work-stack"
    x-data="{
        saving: false,
        filterYear: @js((string) $defaultYearId),
        filterSource: 'Semua',
        allRows: @js($budgetRows),

        // Multi-step modal state
        step: 1,
        sourceType: 'BOS', // 'Komite' or 'BOS'
        yearId: @js((string) $defaultYearId),
        bosSource: 'BOS Reguler',
        bosComponent: 'Pembelajaran',
        programs: [
            {
                name: '',
                activities: [
                    { name: '', amount: '' }
                ]
            }
        ],
        validationErrors: [],
        hasAttemptedSubmit: false,

        // Edit & Delete modal state
        activePlan: null,
        deletingPlan: null,
        mode: 'edit', // 'edit' or 'revision'
        editYearId: '',
        editSource: 'BOS',
        editProgram: '',
        editActivity: '',
        editAmount: '',
        revisionAmount: '',
        revisionReason: '',

        // Success dialog
        showSuccessModal: @js(session('success_modal') ? true : false),

        get filteredRows() {
            return this.allRows.filter(row => {
                const matchYear = !this.filterYear || String(row.budget_year_id) === String(this.filterYear);
                const matchSource = this.filterSource === 'Semua' || String(row.source_funding).toLowerCase().includes(this.filterSource.toLowerCase());
                return matchYear && matchSource;
            });
        },

        openCreateModal() {
            this.step = 1;
            this.sourceType = 'BOS';
            this.yearId = @js((string) $defaultYearId);
            this.bosSource = 'BOS Reguler';
            this.bosComponent = 'Pembelajaran';
            this.programs = [
                {
                    name: '',
                    activities: [
                        { name: '', amount: '' }
                    ]
                }
            ];
            this.validationErrors = [];
            this.hasAttemptedSubmit = false;
            this.$refs.budgetModal.showModal();
        },

        nextStep() {
            if (!this.yearId) {
                alert('Silakan pilih Tahun Anggaran terlebih dahulu.');
                return;
            }
            this.step = 2;
        },

        prevStep() {
            this.step = 1;
        },

        addProgram() {
            this.programs.push({
                name: '',
                activities: [
                    { name: '', amount: '' }
                ]
            });
        },

        removeProgram(index) {
            if (this.programs.length > 1) {
                this.programs.splice(index, 1);
            }
        },

        addActivity(progIndex) {
            this.programs[progIndex].activities.push({
                name: '',
                amount: ''
            });
        },

        removeActivity(progIndex, actIndex) {
            if (this.programs[progIndex].activities.length > 1) {
                this.programs[progIndex].activities.splice(actIndex, 1);
            }
        },

        programTotal(prog) {
            return (prog.activities || []).reduce((sum, act) => {
                const val = Number(String(act.amount || '').replace(/\D/g, '')) || 0;
                return sum + val;
            }, 0);
        },

        grandTotal() {
            return (this.programs || []).reduce((sum, prog) => sum + this.programTotal(prog), 0);
        },

        formatRupiah(val) {
            if (!val && val !== 0) return 'Rp 0';
            const clean = Number(String(val).replace(/\D/g, '')) || 0;
            return 'Rp ' + clean.toLocaleString('id-ID');
        },

        validateForm() {
            this.validationErrors = [];
            let hasEmptyProgram = false;
            let hasEmptyActivity = false;
            let hasEmptyAmount = false;

            this.programs.forEach(p => {
                if (!p.name || !p.name.trim()) {
                    hasEmptyProgram = true;
                }
                if (!p.activities || p.activities.length === 0) {
                    hasEmptyActivity = true;
                } else {
                    p.activities.forEach(a => {
                        if (!a.name || !a.name.trim()) hasEmptyActivity = true;
                        const val = Number(String(a.amount || '').replace(/\D/g, '')) || 0;
                        if (!a.amount || val <= 0) hasEmptyAmount = true;
                    });
                }
            });

            if (hasEmptyProgram) this.validationErrors.push('Nama program tidak boleh kosong');
            if (hasEmptyActivity) this.validationErrors.push('Minimal 1 kegiatan dalam setiap program dan nama kegiatan wajib diisi');
            if (hasEmptyAmount) this.validationErrors.push('Nominal kegiatan harus diisi');

            return this.validationErrors.length === 0;
        },

        submitMultiBudget() {
            this.hasAttemptedSubmit = true;
            if (!this.validateForm()) {
                return;
            }
            this.saving = true;
            this.$refs.multiBudgetForm.submit();
        },

        openEdit(row) {
            this.activePlan = row;
            this.mode = row.status_raw === 'draft' || row.status_raw === 'ditolak' ? 'edit' : 'revision';
            this.editYearId = row.budget_year_id || '';
            this.editSource = row.source_funding || 'BOS';
            this.editProgram = row.program_name || '';
            this.editActivity = row.activity_name || '';
            this.editAmount = window.formatCurrencyMask ? window.formatCurrencyMask(row.amount) : (row.amount || '');
            this.revisionAmount = window.formatCurrencyMask ? window.formatCurrencyMask(row.amount) : (row.amount || '');
            this.revisionReason = '';
            this.$nextTick(() => {
                this.$refs.actionModal.showModal();
            });
        },

        openDelete(row) {
            this.deletingPlan = row;
            this.$nextTick(() => {
                this.$refs.deleteBudgetModal.showModal();
            });
        }
    }"
    @table-edit="openEdit($event.detail)"
    @table-delete="openDelete($event.detail)"
    x-init="
        @if($errors->any() && !old('_method') && !old('new_amount')) $nextTick(() => $refs.budgetModal.showModal()); @endif
        @if(session('success_modal')) $nextTick(() => $refs.successNotificationModal.showModal()); @endif
    ">

    @if(session('success') && !session('success_modal'))
        <div role="status" class="work-notice">{{ session('success') }}</div>
    @endif

    {{-- TOP BANNER / HEADER LIKE SCREENSHOT --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-2 border-b border-gray-100 dark:border-gray-800">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-gray-900 dark:text-white">Susun Anggaran</h1>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Menyusun rencana anggaran sekolah per sumber dana dengan mudah.</p>
        </div>
        <div class="flex items-center gap-3">
            <div class="hidden md:flex items-center gap-2 p-2.5 bg-blue-50/80 dark:bg-blue-950/30 border border-blue-100 dark:border-blue-900/50 rounded-xl text-xs text-blue-800 dark:text-blue-300 max-w-md">
                <svg class="w-4 h-4 text-blue-600 dark:text-blue-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span class="leading-tight">Susun program dan kegiatan sesuai kebutuhan sekolah. Data akan dikirim untuk disetujui oleh Kepala Sekolah.</span>
            </div>
            <button type="button" class="work-btn work-btn-primary shrink-0" @click="openCreateModal()">
                <svg class="w-4 h-4 mr-1.5 -ml-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Susun Anggaran
            </button>
        </div>
    </div>

    {{-- FILTER BAR (STEP 3) --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-4 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl shadow-xs">
        <div>
            <label for="filter-year" class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Tahun Anggaran</label>
            <select id="filter-year" x-model="filterYear" class="w-full text-xs font-medium rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-gray-900 dark:text-white focus:ring-2 focus:ring-brand-500 focus:outline-none">
                <option value="">Semua Tahun Anggaran</option>
                @foreach($budgetYears as $year)
                    <option value="{{ $year->id }}">
                        {{ $year->name }} {{ ($year->status ?? 'active') === 'active' ? '(Aktif)' : '(Tutup)' }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="filter-source" class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Sumber Dana</label>
            <select id="filter-source" x-model="filterSource" class="w-full text-xs font-medium rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-gray-900 dark:text-white focus:ring-2 focus:ring-brand-500 focus:outline-none">
                @foreach($allSources as $src)
                    <option value="{{ $src }}">{{ $src }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- DATA TABLE LIST SUSUN ANGGARAN (STEP 3, 9, 10) --}}
    <x-common.data-table
        :rows="$budgetRows"
        :columns="$budgetColumns"
        caption="Daftar Susun Anggaran"
        search-label="Cari program atau kegiatan..."
        row-label="program anggaran"
        subtitle="Rencana anggaran belanja sekolah per sumber dana"
        :show-actions="false"
        :show-avatar="false"
        :exportable="false"
        empty-message="Belum ada susunan anggaran."
        empty-hint="Pilih Susun Anggaran untuk membuat rencana anggaran program dan kegiatan sekolah."
    />

    {{-- CALLOUT INFO BOX BELOW TABLE (STEP 3 & 10) --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="p-4 bg-blue-50/70 dark:bg-blue-950/20 border border-blue-100 dark:border-blue-900/40 rounded-2xl text-xs space-y-1.5">
            <div class="flex items-center gap-2 font-semibold text-blue-900 dark:text-blue-200">
                <svg class="w-4 h-4 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Status anggaran:</span>
            </div>
            <ul class="text-blue-800/90 dark:text-blue-300/90 space-y-1 pl-6 list-disc">
                <li><strong class="text-blue-900 dark:text-blue-100">Disetujui</strong> : anggaran dapat digunakan untuk transaksi belanja.</li>
                <li><strong class="text-blue-900 dark:text-blue-100">Menunggu</strong> : menunggu persetujuan dari Kepala Sekolah.</li>
                <li><strong class="text-blue-900 dark:text-blue-100">Ditolak</strong> : perlu dilakukan perbaikan atau revisi sesuai catatan.</li>
            </ul>
        </div>
        <div class="p-4 bg-amber-50/70 dark:bg-amber-950/20 border border-amber-100 dark:border-amber-900/40 rounded-2xl text-xs space-y-1.5">
            <div class="flex items-center gap-2 font-semibold text-amber-900 dark:text-amber-200">
                <svg class="w-4 h-4 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <span>Ketentuan Hapus Anggaran:</span>
            </div>
            <p class="text-amber-800/90 dark:text-amber-300/90 pl-6">
                Susunan anggaran <strong>tidak dapat dihapus</strong> jika sudah ada realisasi transaksi pengeluaran belanja yang menggunakan anggaran tersebut. Tombol hapus akan dinonaktifkan otomatis oleh sistem.
            </p>
        </div>
    </div>

    {{-- ========================================================================= --}}
    {{-- MODAL SUSUN ANGGARAN BARU (STEP 4, 5, 6, 7) --}}
    {{-- ========================================================================= --}}
    <dialog x-ref="budgetModal" class="account-dialog work" style="max-width: 48rem; width: 100%;" aria-labelledby="budget-modal-title"
        @close="saving = false" @cancel="if (saving) $event.preventDefault()"
        @click="const bounds = $el.getBoundingClientRect(); if (!saving && ($event.clientX < bounds.left || $event.clientX > bounds.right || $event.clientY < bounds.top || $event.clientY > bounds.bottom)) $el.close()">
        
        <div class="account-dialog-head">
            <div class="min-w-0">
                <h2 id="budget-modal-title" class="text-lg font-bold text-gray-900 dark:text-white" x-text="step === 1 ? 'Susun Anggaran Baru' : 'Detail Program dan Kegiatan'"></h2>
                <p class="work-muted text-xs mt-0.5" x-text="step === 1 ? 'Langkah 1 dari 2: Tentukan sumber dana dan tahun anggaran' : 'Langkah 2 dari 2: Masukkan rincian nama program, kegiatan, dan nominal'"></p>
            </div>
            <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors p-1 rounded-md" @click="$refs.budgetModal.close()" :disabled="saving" aria-label="Tutup modal">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <form x-ref="multiBudgetForm" method="POST" action="{{ route('finance.budgets.store') }}">
            @csrf

            {{-- STEP 1: INFORMASI DASAR ANGGARAN (STEP 4 IN SCREENSHOT) --}}
            <div x-show="step === 1" class="space-y-5 py-2">
                {{-- 1. Sumber Dana (Radio Buttons: Komite / BOS) --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-2">
                        Sumber Dana <span class="text-red-500">*</span>
                    </label>
                    <div class="flex items-center gap-6">
                        <label class="inline-flex items-center gap-2.5 cursor-pointer text-xs font-medium text-gray-800 dark:text-gray-200">
                            <input type="radio" name="source_type" value="Komite" x-model="sourceType" class="w-4 h-4 text-brand-600 focus:ring-brand-500">
                            <span>Komite</span>
                        </label>
                        <label class="inline-flex items-center gap-2.5 cursor-pointer text-xs font-medium text-gray-800 dark:text-gray-200">
                            <input type="radio" name="source_type" value="BOS" x-model="sourceType" class="w-4 h-4 text-brand-600 focus:ring-brand-500">
                            <span>BOS</span>
                        </label>
                    </div>
                </div>

                {{-- 2. Tahun Anggaran --}}
                <div>
                    <label for="modal-year-id" class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                        Tahun Anggaran <span class="text-red-500">*</span>
                    </label>
                    <select id="modal-year-id" name="budget_year_id" x-model="yearId" required
                        class="w-full text-sm rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3.5 py-2 text-gray-900 dark:text-white focus:ring-2 focus:ring-brand-500 focus:outline-none">
                        <option value="" disabled>-- Pilih Tahun Anggaran Aktif --</option>
                        @foreach($budgetYears as $year)
                            <option value="{{ $year->id }}">
                                {{ $year->name }} {{ ($year->status ?? 'active') === 'active' ? '(Aktif)' : '(Tutup)' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- 3 & 4. Dropdown BOS (Muncul jika Sumber Dana BOS) --}}
                <div x-show="sourceType === 'BOS'" x-transition class="space-y-4 p-4 bg-blue-50/50 dark:bg-blue-950/20 border border-blue-100 dark:border-blue-900/40 rounded-2xl">
                    <div>
                        <label for="modal-bos-source" class="block text-xs font-semibold text-blue-900 dark:text-blue-300 mb-1">
                            Sumber Dana BOS <span class="text-red-500">*</span>
                        </label>
                        <select id="modal-bos-source" name="bos_source" x-model="bosSource"
                            class="w-full text-sm rounded-xl border border-blue-200 dark:border-blue-800 bg-white dark:bg-gray-800 px-3 py-2 text-gray-900 dark:text-white focus:ring-2 focus:ring-brand-500 focus:outline-none">
                            @foreach($bosSources ?? ['BOS Reguler', 'BOS Kinerja', 'BOP', 'DAK', 'Lainnya'] as $bs)
                                <option value="{{ $bs }}">{{ $bs }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="modal-bos-component" class="block text-xs font-semibold text-blue-900 dark:text-blue-300 mb-1">
                            Komponen BOS <span class="text-red-500">*</span>
                        </label>
                        <select id="modal-bos-component" name="bos_component" x-model="bosComponent"
                            class="w-full text-sm rounded-xl border border-blue-200 dark:border-blue-800 bg-white dark:bg-gray-800 px-3 py-2 text-gray-900 dark:text-white focus:ring-2 focus:ring-brand-500 focus:outline-none">
                            @foreach($bosComponents ?? ['Pembelajaran', 'Belanja pegawai', 'Belanja barang', 'Belanja modal', 'Pemeliharaan', 'Lainnya'] as $bc)
                                <option value="{{ $bc }}">{{ $bc }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Hidden input for source_funding passed to backend --}}
                <input type="hidden" name="source_funding" :value="sourceType === 'BOS' ? bosSource : 'Komite'">

                <div class="account-dialog-actions mt-6 pt-3 border-t border-gray-100 dark:border-gray-800">
                    <button type="button" class="work-btn work-btn-secondary" @click="$refs.budgetModal.close()">
                        Batal
                    </button>
                    <button type="button" class="work-btn work-btn-primary" @click="nextStep()">
                        Lanjutkan
                    </button>
                </div>
            </div>

            {{-- STEP 2: INPUT PROGRAM DAN KEGIATAN (STEP 5, 6, 7 IN SCREENSHOT) --}}
            <div x-show="step === 2" class="space-y-5 py-2">
                {{-- VALIDASI DATA ALERT BANNER (STEP 7 IN SCREENSHOT) --}}
                <div x-show="hasAttemptedSubmit && validationErrors.length > 0" x-transition
                    class="p-4 rounded-xl bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-900 text-red-700 dark:text-red-300 text-xs">
                    <div class="flex items-start gap-2.5">
                        <div class="flex h-5 w-5 items-center justify-center rounded-full bg-red-600 text-white shrink-0 font-bold text-xs">!</div>
                        <div>
                            <p class="font-bold text-sm text-red-800 dark:text-red-200">Data belum lengkap</p>
                            <p class="mt-0.5 font-medium">Mohon lengkapi data berikut:</p>
                            <ul class="list-disc pl-5 mt-1 space-y-0.5">
                                <template x-for="err in validationErrors" :key="err">
                                    <li x-text="err"></li>
                                </template>
                            </ul>
                        </div>
                    </div>
                </div>

                {{-- LIST OF PROGRAMS --}}
                <div class="space-y-6">
                    <template x-for="(prog, pIdx) in programs" :key="pIdx">
                        <div class="p-4 border border-gray-200 dark:border-gray-800 rounded-2xl bg-gray-50/50 dark:bg-gray-800/40 space-y-3">
                            <div class="flex items-center justify-between pb-1">
                                <span class="text-xs font-bold text-gray-800 dark:text-gray-200" x-text="'Program ' + (programs.length > 1 ? (pIdx + 1) : '')"></span>
                                <template x-if="programs.length > 1">
                                    <button type="button" @click="removeProgram(pIdx)" class="text-xs text-rose-600 hover:text-rose-700 flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        Hapus Program
                                    </button>
                                </template>
                            </div>

                            {{-- Nama Program --}}
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                                    Nama Program <span class="text-red-500">*</span>
                                </label>
                                <input type="text" :name="'programs[' + pIdx + '][name]'" x-model="prog.name" required
                                    placeholder="Contoh: Program Pembelajaran"
                                    :class="hasAttemptedSubmit && (!prog.name || !prog.name.trim()) ? 'border-red-500 focus:ring-red-500' : 'border-gray-300 dark:border-gray-700 focus:ring-brand-500'"
                                    class="w-full text-xs rounded-xl border bg-white dark:bg-gray-800 px-3 py-2 text-gray-900 dark:text-white focus:outline-none focus:ring-2">
                                <template x-if="hasAttemptedSubmit && (!prog.name || !prog.name.trim())">
                                    <p class="text-[11px] text-red-600 mt-1">Nama program wajib diisi.</p>
                                </template>
                            </div>

                            {{-- Daftar Kegiatan Table --}}
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
                                    Daftar Kegiatan
                                </label>
                                <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700/80 bg-white dark:bg-gray-800">
                                    <table class="w-full text-xs text-left">
                                        <thead class="bg-gray-100/70 dark:bg-gray-700/50 text-gray-600 dark:text-gray-300 border-b border-gray-200 dark:border-gray-700">
                                            <tr>
                                                <th class="px-2.5 py-2 w-10 text-center">No</th>
                                                <th class="px-2.5 py-2">Nama Kegiatan <span class="text-red-500">*</span></th>
                                                <th class="px-2.5 py-2 w-44">Nominal <span class="text-red-500">*</span></th>
                                                <th class="px-2.5 py-2 w-12 text-center">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                                            <template x-for="(act, aIdx) in prog.activities" :key="aIdx">
                                                <tr>
                                                    <td class="px-2.5 py-2 text-center text-gray-500" x-text="aIdx + 1"></td>
                                                    <td class="px-2.5 py-2">
                                                        <input type="text" :name="'programs[' + pIdx + '][activities][' + aIdx + '][name]'" x-model="act.name" required
                                                            placeholder="Nama Kegiatan"
                                                            :class="hasAttemptedSubmit && (!act.name || !act.name.trim()) ? 'border-red-500' : 'border-gray-200 dark:border-gray-700'"
                                                            class="w-full text-xs rounded-lg border bg-gray-50 dark:bg-gray-900 px-2.5 py-1.5 text-gray-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-brand-500">
                                                        <template x-if="hasAttemptedSubmit && (!act.name || !act.name.trim())">
                                                            <p class="text-[10px] text-red-600 mt-0.5">Nama kegiatan wajib diisi.</p>
                                                        </template>
                                                    </td>
                                                    <td class="px-2.5 py-2">
                                                        <div class="relative flex items-center">
                                                            <span class="absolute inset-y-0 left-0 pl-2.5 flex items-center text-[11px] font-semibold text-gray-500 dark:text-gray-400 pointer-events-none select-none z-10">Rp</span>
                                                            <input type="text" inputmode="numeric" data-mask="currency" :name="'programs[' + pIdx + '][activities][' + aIdx + '][amount]'" x-model="act.amount" required
                                                                placeholder="0"
                                                                :class="hasAttemptedSubmit && (!act.amount || Number(String(act.amount).replace(/\D/g, '')) <= 0) ? 'border-red-500' : 'border-gray-200 dark:border-gray-700'"
                                                                class="w-full text-xs rounded-lg border bg-gray-50 dark:bg-gray-900 !pl-8 pr-2.5 py-1.5 text-gray-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-brand-500 text-right tabular-nums mask-currency"
                                                                style="padding-left: 2rem !important;">
                                                        </div>
                                                    </td>
                                                    <td class="px-2.5 py-2 text-center">
                                                        <button type="button" @click="removeActivity(pIdx, aIdx)" :disabled="prog.activities.length <= 1"
                                                            class="text-gray-400 hover:text-rose-600 disabled:opacity-30 disabled:hover:text-gray-400 p-1 rounded transition">
                                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                        </button>
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            {{-- Actions within program card --}}
                            <div class="flex items-center justify-between pt-1">
                                <button type="button" @click="addActivity(pIdx)" class="work-btn work-btn-secondary text-xs py-1 px-2.5">
                                    <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                    Tambah Kegiatan
                                </button>
                                <div class="text-xs font-semibold text-gray-700 dark:text-gray-300">
                                    Total Program Ini: <span class="text-brand-600 dark:text-brand-400 tabular-nums font-bold" x-text="formatRupiah(programTotal(prog))"></span>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Button Tambah Program (Step 6 in screenshot) --}}
                <div class="pt-1">
                    <button type="button" @click="addProgram()" class="work-btn work-btn-secondary text-xs">
                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Tambah Program
                    </button>
                </div>

                {{-- Global Total Card --}}
                <div class="p-3 bg-blue-50/80 dark:bg-blue-950/30 border border-blue-100 dark:border-blue-900/50 rounded-xl flex items-center justify-between text-xs">
                    <div class="flex items-center gap-2 font-medium text-blue-900 dark:text-blue-200">
                        <svg class="w-4 h-4 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>Total Anggaran Keseluruhan</span>
                    </div>
                    <span class="text-sm font-bold text-blue-900 dark:text-blue-100 tabular-nums" x-text="formatRupiah(grandTotal())"></span>
                </div>

                <div class="account-dialog-actions mt-6 pt-3 border-t border-gray-100 dark:border-gray-800">
                    <button type="button" class="work-btn work-btn-secondary" @click="prevStep()" :disabled="saving">
                        Kembali
                    </button>
                    <button type="button" class="work-btn work-btn-primary" @click="submitMultiBudget()" :disabled="saving">
                        <span x-show="!saving">Simpan</span>
                        <span x-show="saving" x-cloak class="flex items-center gap-1.5">
                            <svg class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                            Menyimpan…
                        </span>
                    </button>
                </div>
            </div>
        </form>
    </dialog>

    {{-- ========================================================================= --}}
    {{-- MODAL KONFIRMASI SIMPAN (STEP 8 IN SCREENSHOT) --}}
    {{-- ========================================================================= --}}
    <dialog x-ref="successNotificationModal" class="account-dialog work" style="max-width: 24rem; width: 100%;" aria-labelledby="success-modal-heading"
        @click="const bounds = $el.getBoundingClientRect(); if ($event.clientX < bounds.left || $event.clientX > bounds.right || $event.clientY < bounds.top || $event.clientY > bounds.bottom) $el.close()">
        <div class="p-6 text-center space-y-4">
            <div class="w-14 h-14 mx-auto rounded-full bg-emerald-100 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
            </div>
            <div>
                <h3 id="success-modal-heading" class="text-base font-bold text-gray-900 dark:text-white">Susunan anggaran disimpan</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    Data susunan anggaran berhasil disimpan. Permintaan persetujuan telah dikirim ke Kepala Sekolah.
                </p>
            </div>
            <div class="pt-2">
                <button type="button" class="work-btn work-btn-primary w-full justify-center" @click="$refs.successNotificationModal.close()">
                    OK
                </button>
            </div>
        </div>
    </dialog>

    {{-- ========================================================================= --}}
    {{-- MODAL AKSI ROW (AJUKAN REVISI / PERBARUI ANGGARAN) --}}
    {{-- ========================================================================= --}}
    <dialog x-ref="actionModal" class="account-dialog work" aria-labelledby="action-modal-title" aria-describedby="action-modal-help"
        @close="saving = false" @cancel="if (saving) $event.preventDefault()"
        @click="const bounds = $el.getBoundingClientRect(); if (!saving && ($event.clientX < bounds.left || $event.clientX > bounds.right || $event.clientY < bounds.top || $event.clientY > bounds.bottom)) $el.close()">
        <div class="account-dialog-head">
            <div class="min-w-0">
                <h2 id="action-modal-title" class="text-lg font-semibold text-gray-900 dark:text-white" x-text="mode === 'revision' ? 'Ajukan Revisi Anggaran' : 'Perbarui Rencana Anggaran'"></h2>
                <p id="action-modal-help" class="work-muted text-xs mt-0.5" x-text="mode === 'revision' ? 'Revisi anggaran akan masuk antrian approval Kepala Sekolah.' : 'Perbarui rincian rencana anggaran.'"></p>
            </div>
            <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors p-1 rounded-md" @click="$refs.actionModal.close()" :disabled="saving" aria-label="Tutup form">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        {{-- MODE TOGGLE (REVISI ATAU EDIT DETAIL) --}}
        <div class="flex items-center gap-2 p-1 bg-gray-100 dark:bg-gray-800 rounded-lg text-xs font-semibold mb-4">
            <button type="button" @click="mode = 'edit'" class="flex-1 py-1.5 px-3 rounded-md transition" :class="mode === 'edit' ? 'bg-white dark:bg-gray-900 text-gray-900 dark:text-white shadow-xs' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'">
                Edit Detail Program
            </button>
            <button type="button" @click="mode = 'revision'" class="flex-1 py-1.5 px-3 rounded-md transition" :class="mode === 'revision' ? 'bg-white dark:bg-gray-900 text-gray-900 dark:text-white shadow-xs' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'">
                Ajukan Revisi Nominal
            </button>
        </div>

        {{-- FORM AJUKAN REVISI --}}
        <div x-show="mode === 'revision'">
            <div class="mb-4 p-3 bg-gray-50 dark:bg-gray-800/50 rounded-lg border border-gray-200 dark:border-gray-700/60 text-xs space-y-1">
                <div class="flex justify-between">
                    <span class="text-gray-500 dark:text-gray-400">Tahun:</span>
                    <span class="font-medium text-gray-900 dark:text-white" x-text="activePlan?.budget_year_name"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500 dark:text-gray-400">Program:</span>
                    <span class="font-medium text-gray-900 dark:text-white truncate max-w-[220px]" x-text="activePlan?.program_name"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500 dark:text-gray-400">Kegiatan:</span>
                    <span class="font-medium text-gray-900 dark:text-white truncate max-w-[220px]" x-text="activePlan?.activity_name"></span>
                </div>
                <div class="flex justify-between border-t border-gray-200 dark:border-gray-700 pt-1 mt-1">
                    <span class="text-gray-500 dark:text-gray-400">Nominal Saat Ini:</span>
                    <span class="font-semibold text-gray-900 dark:text-white" x-text="formatRupiah(activePlan?.amount)"></span>
                </div>
            </div>

            <form method="POST" :action="activePlan ? activePlan.revision_url : ''" @submit="saving = true" :aria-busy="saving">
                @csrf

                <div class="work-field">
                    <label for="rev-amount">Nominal Anggaran Baru <span class="text-red-500">*</span></label>
                    <div class="relative flex items-center">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-xs font-semibold text-gray-500 dark:text-gray-400 pointer-events-none select-none z-10">Rp</span>
                        <input id="rev-amount" name="new_amount" x-model="revisionAmount" type="text" inputmode="numeric" data-mask="currency" required placeholder="Contoh: 18.000.000" class="w-full !pl-11 pr-3 py-2 text-xs rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white tabular-nums mask-currency" style="padding-left: 2.75rem !important;">
                    </div>
                </div>

                <div class="work-field">
                    <label for="rev-reason">Alasan Revisi Anggaran <span class="text-red-500">*</span></label>
                    <textarea id="rev-reason" name="reason" x-model="revisionReason" rows="3" maxlength="1000" required placeholder="Jelaskan dasar kebutuhan penyesuaian pagu anggaran ini…"></textarea>
                    <p class="work-muted text-xs mt-1">Alasan revisi akan ditelaah oleh Kepala Sekolah.</p>
                </div>

                <div class="account-dialog-actions mt-4">
                    <button type="button" class="work-btn" @click="$refs.actionModal.close()" :disabled="saving">Batal</button>
                    <button type="submit" class="work-btn work-btn-primary" :disabled="saving">
                        <span x-show="!saving">Ajukan Revisi</span>
                        <span x-show="saving" x-cloak>Mengajukan…</span>
                    </button>
                </div>
            </form>
        </div>

        {{-- FORM EDIT DETAIL PROGRAM --}}
        <div x-show="mode === 'edit'">
            <form method="POST" :action="activePlan ? activePlan.update_url : ''" @submit="saving = true" :aria-busy="saving">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="work-field">
                        <label for="edit-year-id">Tahun Anggaran <span class="text-red-500">*</span></label>
                        <select id="edit-year-id" name="budget_year_id" x-model="editYearId" required>
                            @foreach($budgetYears as $year)
                                <option value="{{ $year->id }}">{{ $year->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="work-field">
                        <label for="edit-source">Sumber Pendanaan <span class="text-red-500">*</span></label>
                        <select id="edit-source" name="source_funding" x-model="editSource" required>
                            <option value="BOS">BOS</option>
                            <option value="Komite">Komite</option>
                            <option value="BOS Reguler">BOS Reguler</option>
                            <option value="BOS Kinerja">BOS Kinerja</option>
                            <option value="BOP">BOP</option>
                            <option value="DAK">DAK</option>
                            <option value="Lainnya">Lainnya</option>
                        </select>
                    </div>
                </div>

                <div class="work-field">
                    <label for="edit-program">Nama Program <span class="text-red-500">*</span></label>
                    <input id="edit-program" name="program_name" x-model="editProgram" type="text" maxlength="160" required>
                </div>

                <div class="work-field">
                    <label for="edit-activity">Nama Kegiatan <span class="text-red-500">*</span></label>
                    <input id="edit-activity" name="activity_name" x-model="editActivity" type="text" maxlength="160" required>
                </div>

                <div class="work-field">
                    <label for="edit-amount">Nominal Anggaran <span class="text-red-500">*</span></label>
                    <div class="relative flex items-center">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-xs font-semibold text-gray-500 dark:text-gray-400 pointer-events-none select-none z-10">Rp</span>
                        <input id="edit-amount" name="amount" x-model="editAmount" type="text" inputmode="numeric" data-mask="currency" required placeholder="Contoh: 25.000.000" class="w-full !pl-11 pr-3 py-2 text-xs rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white tabular-nums mask-currency" style="padding-left: 2.75rem !important;">
                    </div>
                </div>

                <div class="account-dialog-actions mt-4">
                    <button type="button" class="work-btn" @click="$refs.actionModal.close()" :disabled="saving">Batal</button>
                    <button type="submit" class="work-btn work-btn-primary" :disabled="saving">
                        <span x-show="!saving">Simpan Perubahan</span>
                        <span x-show="saving" x-cloak>Menyimpan…</span>
                    </button>
                </div>
            </form>
        </div>
    </dialog>

    {{-- ========================================================================= --}}
    {{-- MODAL HAPUS ANGGARAN --}}
    {{-- ========================================================================= --}}
    <dialog x-ref="deleteBudgetModal" class="account-dialog work" style="max-width: 28rem; width: 100%;" aria-labelledby="delete-budget-title"
        @close="saving = false" @cancel="if (saving) $event.preventDefault()"
        @click="const bounds = $el.getBoundingClientRect(); if (!saving && ($event.clientX < bounds.left || $event.clientX > bounds.right || $event.clientY < bounds.top || $event.clientY > bounds.bottom)) $el.close()">
        <div class="account-dialog-head">
            <div class="min-w-0">
                <h2 id="delete-budget-title" class="text-base font-semibold text-gray-900 dark:text-white">Hapus Susunan Anggaran</h2>
                <p class="work-muted text-xs mt-0.5">Tindakan ini tidak dapat dibatalkan.</p>
            </div>
            <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors p-1 rounded-md" @click="$refs.deleteBudgetModal.close()" :disabled="saving" aria-label="Tutup dialog">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <p class="text-xs text-gray-600 dark:text-gray-400 my-4">
            Apakah Anda yakin ingin menghapus susunan anggaran kegiatan <strong class="text-gray-900 dark:text-white" x-text="deletingPlan?.activity_name || deletingPlan?.program_name"></strong>?
            Susunan anggaran yang sudah memiliki realisasi transaksi belanja tidak dapat dihapus.
        </p>

        <form method="POST" :action="deletingPlan ? deletingPlan.delete_url : '#'" @submit="saving = true" :aria-busy="saving">
            @csrf
            @method('DELETE')
            <div class="account-dialog-actions mt-4">
                <button type="button" class="work-btn work-btn-secondary" @click="$refs.deleteBudgetModal.close()" :disabled="saving">Batal</button>
                <button type="submit" class="work-btn bg-rose-600 hover:bg-rose-700 text-white" :disabled="saving">
                    <span x-show="!saving">Hapus Anggaran</span>
                    <span x-show="saving" x-cloak>Menghapus…</span>
                </button>
            </div>
        </form>
    </dialog>
</div>
@endsection
