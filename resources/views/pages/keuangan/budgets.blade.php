@extends('layouts.app')

@section('content')
@php
    $budgetColumns = [
        ['key' => 'budget_year_name', 'label' => 'Tahun Anggaran', 'bold' => true],
        ['key' => 'source_funding', 'label' => 'Sumber Dana', 'type' => 'badge'],
        ['key' => 'program_name', 'label' => 'Program & Kegiatan', 'bold' => true, 'subKey' => 'activity_name'],
        ['key' => 'amount', 'label' => 'Nominal Anggaran', 'type' => 'currency', 'minimumFractionDigits' => 0],
        ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
        ['key' => 'aksi', 'label' => 'Aksi', 'sortable' => false, 'onlyEdit' => true],
    ];

    $budgetRows = $budgetPlans->map(function ($plan) {
        $statusLabels = [
            'pending' => 'Pending',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            'draft' => 'Draft',
        ];

        return [
            'id' => $plan->id,
            'budget_year_id' => $plan->budget_year_id,
            'budget_year_name' => $plan->budgetYear?->name ?? 'Semua Periode',
            'source_funding' => strtoupper($plan->source_funding),
            'program_name' => $plan->program_name,
            'activity_name' => $plan->activity_name,
            'amount' => (float) $plan->amount,
            'status' => $statusLabels[$plan->status] ?? ucfirst($plan->status),
            'status_raw' => $plan->status ?? 'pending',
            'revision_url' => route('finance.budgets.revisions.store', $plan),
            'update_url' => route('finance.budgets.update', $plan),
            'revisions_count' => $plan->revisions->count(),
            'only_edit' => true,
        ];
    });

    $firstError = array_key_first($errors->getMessages());
@endphp

<x-common.page-breadcrumb pageTitle="Susun & Revisi Anggaran" label="Perencanaan Anggaran" />

<div class="work work-stack"
    x-data="{
        saving: false,
        activePlan: null,
        mode: 'revision', // 'revision' or 'edit'
        editYearId: '',
        editSource: 'BOS',
        editProgram: '',
        editActivity: '',
        editAmount: '',
        revisionAmount: '',
        revisionReason: '',
        openEdit(row) {
            this.activePlan = row;
            this.mode = row.status_raw === 'draft' ? 'edit' : 'revision';
            this.editYearId = row.budget_year_id || '';
            this.editSource = row.source_funding || 'BOS';
            this.editProgram = row.program_name || '';
            this.editActivity = row.activity_name || '';
            this.editAmount = row.amount || '';
            this.revisionAmount = row.amount || '';
            this.revisionReason = '';
            this.$nextTick(() => {
                this.$refs.actionModal.showModal();
            });
        },
        formatRupiah(val) {
            if (!val && val !== 0) return 'Rp 0';
            return 'Rp ' + Number(val).toLocaleString('id-ID');
        }
    }"
    @table-edit="openEdit($event.detail)"
    x-init="
        @if($errors->any() && !old('_method') && !old('new_amount')) $nextTick(() => $refs.budgetModal.showModal()); @endif
    ">

    @if(session('success'))
        <div role="status" class="work-notice">{{ session('success') }}</div>
    @endif

    {{-- METRICS COUNTER --}}
    @if(count($metrics))
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4 mb-2">
            @foreach($metrics as $metric)
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-xs dark:border-gray-800 dark:bg-gray-900">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $metric['label'] }}</p>
                    <p class="mt-1 text-xl font-bold tracking-tight text-gray-900 dark:text-white">{{ $metric['value'] }}</p>
                </div>
            @endforeach
        </div>
    @endif

    <div class="work-head mb-0">
        <p class="work-muted">Penyusunan rencana alokasi anggaran belanja sekolah serta pengajuan revisi anggaran.</p>
        <button type="button" class="work-btn work-btn-primary" @click="$refs.budgetModal.showModal()">
            <svg class="w-4 h-4 mr-1.5 -ml-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Rencana Anggaran
        </button>
    </div>

    <x-common.data-table
        :rows="$budgetRows"
        :columns="$budgetColumns"
        caption="Daftar Rencana Anggaran"
        search-label="Cari program atau kegiatan"
        row-label="rencana anggaran"
        subtitle="Rencana anggaran dan revisi belanja sekolah"
        :show-actions="false"
        :show-avatar="false"
        :exportable="false"
        empty-message="Belum ada rencana anggaran."
        empty-hint="Pilih Rencana Anggaran untuk menyusun mata anggaran baru."
    />

    {{-- MODAL TAMBAH RENCANA ANGGARAN (SUSUN ANGGARAN) --}}
    <dialog x-ref="budgetModal" class="account-dialog work" aria-labelledby="budget-modal-title" aria-describedby="budget-modal-help"
        @close="saving = false" @cancel="if (saving) $event.preventDefault()"
        @click="const bounds = $el.getBoundingClientRect(); if (!saving && ($event.clientX < bounds.left || $event.clientX > bounds.right || $event.clientY < bounds.top || $event.clientY > bounds.bottom)) $el.close()">
        <div class="account-dialog-head">
            <div class="min-w-0">
                <h2 id="budget-modal-title" class="text-lg font-semibold text-gray-900 dark:text-white">Susun Rencana Anggaran</h2>
                <p id="budget-modal-help" class="work-muted text-xs mt-0.5">Rencana anggaran baru akan dikirimkan ke Kepala Sekolah untuk approval.</p>
            </div>
            <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors p-1 rounded-md" @click="$refs.budgetModal.close()" :disabled="saving" aria-label="Tutup form">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <form method="POST" action="{{ route('finance.budgets.store') }}" @submit="saving = true" :aria-busy="saving">
            @csrf

            @if($errors->any() && !old('_method') && !old('new_amount'))
                <div role="alert" class="p-3 bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 rounded-lg text-xs">
                    <p class="font-medium mb-1">Periksa isian berikut sebelum menyimpan:</p>
                    <ul class="list-disc pl-4 space-y-0.5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="work-field">
                    <label for="budget-year-id">Tahun Anggaran <span class="text-red-500">*</span></label>
                    <select id="budget-year-id" name="budget_year_id" required>
                        <option value="" disabled @selected(!old('budget_year_id'))>-- Pilih Periode --</option>
                        @foreach($budgetYears as $year)
                            <option value="{{ $year->id }}" @selected(old('budget_year_id') == $year->id)>
                                {{ $year->name }} ({{ ucfirst($year->status) }})
                            </option>
                        @endforeach
                    </select>
                    @error('budget_year_id')<p class="work-muted work-error text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>

                <div class="work-field">
                    <label for="budget-source">Sumber Pendanaan <span class="text-red-500">*</span></label>
                    <select id="budget-source" name="source_funding" required>
                        <option value="BOS" @selected(old('source_funding', 'BOS') === 'BOS')>BOS (Bantuan Operasional Sekolah)</option>
                        <option value="Komite" @selected(old('source_funding') === 'Komite')>Komite Sekolah / SPP</option>
                        <option value="Yayasan" @selected(old('source_funding') === 'Yayasan')>Yayasan</option>
                        <option value="Hibah" @selected(old('source_funding') === 'Hibah')>Hibah / Donasi</option>
                        <option value="Lainnya" @selected(old('source_funding') === 'Lainnya')>Lainnya</option>
                    </select>
                    @error('source_funding')<p class="work-muted work-error text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="work-field">
                <label for="budget-program">Nama Program <span class="text-red-500">*</span></label>
                <input id="budget-program" name="program_name" value="{{ old('program_name') }}" type="text" maxlength="160" required placeholder="Contoh: Pengembangan Standar Proses & Kurikulum">
                @error('program_name')<p class="work-muted work-error text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
            </div>

            <div class="work-field">
                <label for="budget-activity">Nama Kegiatan <span class="text-red-500">*</span></label>
                <input id="budget-activity" name="activity_name" value="{{ old('activity_name') }}" type="text" maxlength="160" required placeholder="Contoh: Pengadaan Modul Pembelajaran & Media Ajar">
                @error('activity_name')<p class="work-muted work-error text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
            </div>

            <div class="work-field">
                <label for="budget-amount">Nominal Anggaran (Rp) <span class="text-red-500">*</span></label>
                <input id="budget-amount" name="amount" value="{{ old('amount') }}" type="number" min="0" step="1000" inputmode="decimal" required placeholder="Contoh: 15000000">
                <p class="work-muted text-xs mt-1">Masukkan nominal rencana anggaran dalam rupiah.</p>
                @error('amount')<p class="work-muted work-error text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
            </div>

            <div class="account-dialog-actions mt-4">
                <button type="button" class="work-btn" @click="$refs.budgetModal.close()" :disabled="saving">Batal</button>
                <button type="submit" class="work-btn work-btn-primary" :disabled="saving">
                    <span x-show="!saving">Simpan Rencana Anggaran</span>
                    <span x-show="saving" x-cloak>Menyimpan…</span>
                </button>
            </div>
        </form>
    </dialog>

    {{-- MODAL AKSI ROW (AJUKAN REVISI / PERBARUI ANGGARAN) --}}
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
            <button type="button" @click="mode = 'revision'" class="flex-1 py-1.5 px-3 rounded-md transition" :class="mode === 'revision' ? 'bg-white dark:bg-gray-900 text-gray-900 dark:text-white shadow-xs' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'">
                Ajukan Revisi Nominal
            </button>
            <button type="button" @click="mode = 'edit'" class="flex-1 py-1.5 px-3 rounded-md transition" :class="mode === 'edit' ? 'bg-white dark:bg-gray-900 text-gray-900 dark:text-white shadow-xs' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'">
                Edit Detail Program
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
                    <label for="revision-amount">Nominal Anggaran Baru (Rp) <span class="text-red-500">*</span></label>
                    <input id="revision-amount" name="new_amount" x-model="revisionAmount" type="number" min="0" step="1000" inputmode="decimal" required placeholder="Contoh: 18000000">
                </div>

                <div class="work-field">
                    <label for="revision-reason">Alasan Revisi Anggaran <span class="text-red-500">*</span></label>
                    <textarea id="revision-reason" name="reason" x-model="revisionReason" rows="3" maxlength="1000" required placeholder="Jelaskan kebutuhan penyesuaian anggaran belanja kegiatan ini…"></textarea>
                    <p class="work-muted text-xs mt-1">Alasan revisi akan ditinjau oleh Kepala Sekolah.</p>
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
        <div x-show="mode === 'edit'" x-cloak>
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
                            <option value="Yayasan">Yayasan</option>
                            <option value="Hibah">Hibah</option>
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
                    <label for="edit-amount">Nominal Anggaran (Rp) <span class="text-red-500">*</span></label>
                    <input id="edit-amount" name="amount" x-model="editAmount" type="number" min="0" step="1000" inputmode="decimal" required>
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
</div>
@endsection
