@extends('layouts.app')

@section('content')
@php
    $expenseColumns = [
        ['key' => 'name', 'label' => 'Nama Pengeluaran', 'bold' => true],
        ['key' => 'code', 'label' => 'Kode'],
        ['key' => 'source_funding', 'label' => 'Sumber Dana', 'type' => 'badge'],
        ['key' => 'bos_component', 'label' => 'Komponen BOS'],
        ['key' => 'requires_approval_label', 'label' => 'Approval Kepala Sekolah', 'type' => 'badge'],
        ['key' => 'document_status', 'label' => 'Dokumen'],
        ['key' => 'aksi', 'label' => 'Aksi', 'sortable' => false, 'onlyEdit' => true],
    ];

    $expenseRows = $expenseTypes->map(function ($type) {
        $reqApproval = (bool) $type->requires_approval;
        return [
            'id' => $type->id,
            'name' => $type->name,
            'code' => $type->code,
            'source_funding' => $type->source_funding ?? 'Komite',
            'bos_component' => $type->bos_component ?: '-',
            'requires_approval' => $reqApproval ? '1' : '0',
            'requires_approval_label' => $reqApproval ? 'Perlu Approval' : 'Langsung',
            'document_status' => $type->supporting_document_path ? 'Ada PDF' : '-',
            'document_url' => $type->supporting_document_path ? asset('storage/' . $type->supporting_document_path) : null,
            'update_url' => route('finance.expense-types.update', $type),
            'only_edit' => true,
        ];
    });

    $firstError = array_key_first($errors->getMessages());
@endphp

<x-common.page-breadcrumb pageTitle="Jenis Pengeluaran" label="Master Data Keuangan" />

<div class="work work-stack"
    x-data="{
        sourceFunding: @js(old('source_funding', 'Komite')),
        requiresApproval: @js(old('requires_approval', '0')),
        bosComponent: @js(old('bos_component', '')),
        saving: false,
        editingExpense: null,
        editSourceFunding: 'Komite',
        editRequiresApproval: '0',
        editBosComponent: '',
        isBos(source) {
            if (!source) return false;
            const s = source.toLowerCase();
            return s.includes('bos') || s === 'bop' || s === 'dak';
        },
        openEdit(row) {
            this.editingExpense = row;
            this.editSourceFunding = row.source_funding || 'Komite';
            this.editRequiresApproval = row.requires_approval ? '1' : '0';
            this.editBosComponent = row.bos_component !== '-' ? row.bos_component : '';
            this.$nextTick(() => {
                this.$refs.editExpenseTypeModal.showModal();
            });
        }
    }"
    @table-edit="openEdit($event.detail)"
    x-init="
        @if($errors->any() && !old('_method')) $nextTick(() => $refs.expenseTypeModal.showModal()); @endif
    ">

    @if(session('success'))
        <div role="status" class="work-notice">{{ session('success') }}</div>
    @endif

    {{-- METRICS SUMMARY --}}
    @if(isset($metrics) && count($metrics))
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
        <p class="work-muted">Master jenis pengeluaran, alokasi sumber dana (Komite/BOS), dan aturan approval kepala sekolah.</p>
        <button type="button" class="work-btn work-btn-primary" @click="$refs.expenseTypeModal.showModal()">
            <svg class="w-4 h-4 mr-1.5 -ml-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Jenis Pengeluaran
        </button>
    </div>

    <x-common.data-table
        :rows="$expenseRows"
        :columns="$expenseColumns"
        caption="Daftar jenis pengeluaran"
        search-label="Cari jenis pengeluaran"
        row-label="jenis pengeluaran"
        subtitle="Daftar jenis belanja & pengeluaran sekolah"
        :show-actions="false"
        :show-avatar="false"
        :exportable="false"
        empty-message="Belum ada jenis pengeluaran."
        empty-hint="Pilih Jenis Pengeluaran untuk menambahkan master jenis pengeluaran pertama."
    />

    {{-- MODAL TAMBAH JENIS PENGELUARAN --}}
    <dialog x-ref="expenseTypeModal" class="account-dialog work" style="max-width: 44rem; width: 100%;" aria-labelledby="expense-modal-title" aria-describedby="expense-modal-help"
        @close="saving = false" @cancel="if (saving) $event.preventDefault()"
        @click="const bounds = $el.getBoundingClientRect(); if (!saving && ($event.clientX < bounds.left || $event.clientX > bounds.right || $event.clientY < bounds.top || $event.clientY > bounds.bottom)) $el.close()">
        <div class="account-dialog-head">
            <div class="min-w-0">
                <h2 id="expense-modal-title" class="text-lg font-semibold text-gray-900 dark:text-white">Tambah Jenis Pengeluaran</h2>
                <p id="expense-modal-help" class="work-muted text-xs mt-0.5">Lengkapi form berikut untuk mendaftarkan jenis pengeluaran baru.</p>
            </div>
            <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors p-1 rounded-md" @click="$refs.expenseTypeModal.close()" :disabled="saving" aria-label="Tutup form">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <form method="POST" action="{{ route('finance.expense-types.store') }}" enctype="multipart/form-data" @submit="saving = true" :aria-busy="saving">
            @csrf

            @if($errors->any() && !old('_method'))
                <div role="alert" class="p-3 bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 rounded-lg text-xs mb-4">
                    <p class="font-medium mb-1">Periksa isian berikut sebelum menyimpan:</p>
                    <ul class="list-disc pl-4 space-y-0.5">
                        @foreach($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="space-y-4">
                {{-- 1. Nama --}}
                <div>
                    <label for="create_name" class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                        Nama Pengeluaran <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="create_name" name="name" value="{{ old('name') }}" required
                        placeholder="Contoh: Honor GTT/PTT, Alat Tulis Kantor, Konsumsi Rapat"
                        class="w-full text-sm rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-gray-900 dark:text-white focus:ring-2 focus:ring-brand-500 focus:outline-none">
                </div>

                {{-- 2. Kode (Sistem Otomatis) --}}
                <div>
                    <label for="create_code" class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                        Kode Pengeluaran <span class="text-gray-400 font-normal">(Sistem Otomatis)</span>
                    </label>
                    <input type="text" id="create_code" name="code" value="{{ old('code', $nextCode ?? 'JPK-0001') }}" readonly
                        class="w-full text-sm rounded-lg border border-gray-200 dark:border-gray-800 bg-gray-100 dark:bg-gray-800/60 px-3 py-2 text-gray-600 dark:text-gray-400 cursor-not-allowed">
                </div>

                {{-- 3. Sumber Dana --}}
                <div>
                    <label for="create_source_funding" class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                        Sumber Dana <span class="text-red-500">*</span>
                    </label>
                    <select id="create_source_funding" name="source_funding" x-model="sourceFunding" required
                        class="w-full text-sm rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-gray-900 dark:text-white focus:ring-2 focus:ring-brand-500 focus:outline-none">
                        @foreach($sourceFundings as $sf)
                            <option value="{{ $sf }}">{{ $sf }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- 4. Komponen BOS (Muncul jika pilih BOS) --}}
                <div x-show="isBos(sourceFunding)" x-transition class="p-3 bg-blue-50/60 dark:bg-blue-950/20 border border-blue-100 dark:border-blue-900/40 rounded-xl space-y-2">
                    <label for="create_bos_component" class="block text-xs font-semibold text-blue-900 dark:text-blue-300">
                        Komponen BOS <span class="text-blue-600 font-normal">(Sesuai Petunjuk Teknis BOS)</span>
                    </label>
                    <select id="create_bos_component" name="bos_component" x-model="bosComponent"
                        class="w-full text-sm rounded-lg border border-blue-200 dark:border-blue-800 bg-white dark:bg-gray-800 px-3 py-2 text-gray-900 dark:text-white focus:ring-2 focus:ring-brand-500 focus:outline-none">
                        <option value="">-- Pilih Komponen BOS --</option>
                        @foreach($bosComponents as $bc)
                            <option value="{{ $bc }}">{{ $bc }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- 5. Perlu Approval Kepala Sekolah (Y/N) --}}
                <div class="pt-1">
                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
                        Perlu Approval Kepala Sekolah?
                    </label>
                    <div class="flex items-center gap-3">
                        <label class="inline-flex items-center gap-2 cursor-pointer text-xs font-medium text-gray-700 dark:text-gray-300">
                            <input type="radio" name="requires_approval" value="1" x-model="requiresApproval" class="text-brand-600 focus:ring-brand-500">
                            <span>Ya (Wajib Disetujui Kepala Sekolah)</span>
                        </label>
                        <label class="inline-flex items-center gap-2 cursor-pointer text-xs font-medium text-gray-700 dark:text-gray-300">
                            <input type="radio" name="requires_approval" value="0" x-model="requiresApproval" class="text-brand-600 focus:ring-brand-500">
                            <span>Tidak (Langsung Dieksekusi Bendahara)</span>
                        </label>
                    </div>
                </div>

                {{-- 6. Dokumen Pendukung (Upload PDF) --}}
                <div>
                    <label for="create_supporting_doc" class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                        Dokumen Pendukung <span class="text-gray-400 font-normal">(Opsional, format PDF maks 10MB)</span>
                    </label>
                    <input type="file" id="create_supporting_doc" name="supporting_document" accept=".pdf,application/pdf"
                        class="w-full text-xs text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-gray-100 dark:file:bg-gray-800 file:text-gray-700 dark:file:text-gray-200 hover:file:bg-gray-200 cursor-pointer">
                </div>
            </div>

            <div class="account-dialog-actions mt-6">
                <button type="button" class="work-btn work-btn-secondary" @click="$refs.expenseTypeModal.close()" :disabled="saving">
                    Batal
                </button>
                <button type="submit" class="work-btn work-btn-primary" :disabled="saving">
                    <span x-show="!saving">Simpan</span>
                    <span x-show="saving" class="flex items-center gap-1.5" style="display: none;">
                        <svg class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                        Menyimpan...
                    </span>
                </button>
            </div>
        </form>
    </dialog>

    {{-- MODAL EDIT JENIS PENGELUARAN --}}
    <dialog x-ref="editExpenseTypeModal" class="account-dialog work" style="max-width: 44rem; width: 100%;" aria-labelledby="edit-expense-modal-title"
        @close="saving = false" @cancel="if (saving) $event.preventDefault()"
        @click="const bounds = $el.getBoundingClientRect(); if (!saving && ($event.clientX < bounds.left || $event.clientX > bounds.right || $event.clientY < bounds.top || $event.clientY > bounds.bottom)) $el.close()">
        <div class="account-dialog-head">
            <div class="min-w-0">
                <h2 id="edit-expense-modal-title" class="text-lg font-semibold text-gray-900 dark:text-white">Edit Jenis Pengeluaran</h2>
                <p class="work-muted text-xs mt-0.5">Ubah informasi master jenis pengeluaran.</p>
            </div>
            <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors p-1 rounded-md" @click="$refs.editExpenseTypeModal.close()" :disabled="saving" aria-label="Tutup form">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <form method="POST" :action="editingExpense ? editingExpense.update_url : '#'" enctype="multipart/form-data" @submit="saving = true" :aria-busy="saving">
            @csrf
            @method('PUT')

            <div class="space-y-4">
                {{-- 1. Nama --}}
                <div>
                    <label for="edit_name" class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                        Nama Pengeluaran <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="edit_name" name="name" :value="editingExpense?.name" required
                        class="w-full text-sm rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-gray-900 dark:text-white focus:ring-2 focus:ring-brand-500 focus:outline-none">
                </div>

                {{-- 2. Kode --}}
                <div>
                    <label for="edit_code" class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                        Kode Pengeluaran
                    </label>
                    <input type="text" id="edit_code" name="code" :value="editingExpense?.code" readonly
                        class="w-full text-sm rounded-lg border border-gray-200 dark:border-gray-800 bg-gray-100 dark:bg-gray-800/60 px-3 py-2 text-gray-600 dark:text-gray-400 cursor-not-allowed">
                </div>

                {{-- 3. Sumber Dana --}}
                <div>
                    <label for="edit_source_funding" class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                        Sumber Dana <span class="text-red-500">*</span>
                    </label>
                    <select id="edit_source_funding" name="source_funding" x-model="editSourceFunding" required
                        class="w-full text-sm rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-gray-900 dark:text-white focus:ring-2 focus:ring-brand-500 focus:outline-none">
                        @foreach($sourceFundings as $sf)
                            <option value="{{ $sf }}">{{ $sf }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- 4. Komponen BOS --}}
                <div x-show="isBos(editSourceFunding)" x-transition class="p-3 bg-blue-50/60 dark:bg-blue-950/20 border border-blue-100 dark:border-blue-900/40 rounded-xl space-y-2">
                    <label for="edit_bos_component" class="block text-xs font-semibold text-blue-900 dark:text-blue-300">
                        Komponen BOS <span class="text-blue-600 font-normal">(Sesuai Petunjuk Teknis BOS)</span>
                    </label>
                    <select id="edit_bos_component" name="bos_component" x-model="editBosComponent"
                        class="w-full text-sm rounded-lg border border-blue-200 dark:border-blue-800 bg-white dark:bg-gray-800 px-3 py-2 text-gray-900 dark:text-white focus:ring-2 focus:ring-brand-500 focus:outline-none">
                        <option value="">-- Pilih Komponen BOS --</option>
                        @foreach($bosComponents as $bc)
                            <option value="{{ $bc }}">{{ $bc }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- 5. Perlu Approval --}}
                <div class="pt-1">
                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
                        Perlu Approval Kepala Sekolah?
                    </label>
                    <div class="flex items-center gap-3">
                        <label class="inline-flex items-center gap-2 cursor-pointer text-xs font-medium text-gray-700 dark:text-gray-300">
                            <input type="radio" name="requires_approval" value="1" x-model="editRequiresApproval" class="text-brand-600 focus:ring-brand-500">
                            <span>Ya (Wajib Disetujui Kepala Sekolah)</span>
                        </label>
                        <label class="inline-flex items-center gap-2 cursor-pointer text-xs font-medium text-gray-700 dark:text-gray-300">
                            <input type="radio" name="requires_approval" value="0" x-model="editRequiresApproval" class="text-brand-600 focus:ring-brand-500">
                            <span>Tidak (Langsung Dieksekusi Bendahara)</span>
                        </label>
                    </div>
                </div>

                {{-- 6. Dokumen Pendukung --}}
                <div>
                    <label for="edit_supporting_doc" class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                        Ganti Dokumen Pendukung <span class="text-gray-400 font-normal">(Format PDF maks 10MB)</span>
                    </label>
                    <input type="file" id="edit_supporting_doc" name="supporting_document" accept=".pdf,application/pdf"
                        class="w-full text-xs text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-gray-100 dark:file:bg-gray-800 file:text-gray-700 dark:file:text-gray-200 hover:file:bg-gray-200 cursor-pointer">
                    <template x-if="editingExpense && editingExpense.document_url">
                        <p class="text-xs text-brand-600 dark:text-brand-400 mt-1">
                            <a :href="editingExpense.document_url" target="_blank" class="hover:underline">Lihat PDF tersimpan</a>
                        </p>
                    </template>
                </div>
            </div>

            <div class="account-dialog-actions mt-6">
                <button type="button" class="work-btn work-btn-secondary" @click="$refs.editExpenseTypeModal.close()" :disabled="saving">
                    Batal
                </button>
                <button type="submit" class="work-btn work-btn-primary" :disabled="saving">
                    <span x-show="!saving">Simpan Perubahan</span>
                    <span x-show="saving" class="flex items-center gap-1.5" style="display: none;">
                        <svg class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                        Menyimpan...
                    </span>
                </button>
            </div>
        </form>
    </dialog>
</div>
@endsection
