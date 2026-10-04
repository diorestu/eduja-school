@extends('layouts.app')

@section('content')
@php
    $incomeColumns = [
        ['key' => 'name', 'label' => 'Nama', 'bold' => true],
        ['key' => 'code', 'label' => 'Kode'],
        ['key' => 'category', 'label' => 'Kategori', 'type' => 'badge'],
        ['key' => 'aksi', 'label' => 'Aksi', 'sortable' => false, 'onlyEdit' => true],
    ];

    $incomeRows = $incomeTypes->map(function ($type) {
        return [
            'id' => $type->id,
            'name' => $type->name,
            'code' => $type->code,
            'category' => ucfirst($type->category ?? 'Lainnya'),
            'category_raw' => $type->category,
            'uses_allocation' => $type->uses_allocation ? '1' : '0',
            'requires_approval' => $type->requires_approval ? '1' : '0',
            'allocations' => $type->fundAllocations->map(fn ($alloc) => [
                'name' => $alloc->name,
                'method' => $alloc->method ?? 'persentase',
                'amount' => (float) $alloc->amount,
                'account_id' => $alloc->account_id,
            ])->values()->all(),
            'update_url' => route('finance.income-types.update', $type),
            'only_edit' => true,
        ];
    });

    $firstError = array_key_first($errors->getMessages());
@endphp

<x-common.page-breadcrumb pageTitle="Jenis Pemasukan" label="Master Data Keuangan" />

<div class="work work-stack"
    x-data="{
        usesAllocation: @js(old('uses_allocation', '0')),
        requiresApproval: @js(old('requires_approval', '0')),
        saving: false,
        editingIncome: null,
        editUsesAllocation: '0',
        editRequiresApproval: '0',
        allocations: @js(old('allocations', [
            ['name' => '', 'method' => 'persentase', 'amount' => '', 'account_id' => '']
        ])),
        editAllocations: [],
        addAllocation() {
            this.allocations.push({ name: '', method: 'persentase', amount: '', account_id: '' });
        },
        removeAllocation(index) {
            if (this.allocations.length > 1) {
                this.allocations.splice(index, 1);
            }
        },
        addEditAllocation() {
            this.editAllocations.push({ name: '', method: 'persentase', amount: '', account_id: '' });
        },
        removeEditAllocation(index) {
            if (this.editAllocations.length > 1) {
                this.editAllocations.splice(index, 1);
            }
        },
        getTotalPercentage(list) {
            const total = (list || []).filter(item => item.method === 'persentase').reduce((sum, item) => sum + (Number(item.amount) || 0), 0);
            return Math.round(total * 100) / 100;
        },
        getTotalNominal(list) {
            return (list || []).filter(item => item.method === 'nominal').reduce((sum, item) => sum + (Number(item.amount) || 0), 0);
        },
        hasPercentage(list) {
            return (list || []).some(item => item.method === 'persentase');
        },
        hasNominal(list) {
            return (list || []).some(item => item.method === 'nominal');
        },
        formatRupiah(num) {
            return 'Rp ' + Number(num || 0).toLocaleString('id-ID');
        },
        openEdit(row) {
            this.editingIncome = row;
            this.editUsesAllocation = row.uses_allocation ? '1' : '0';
            this.editRequiresApproval = row.requires_approval ? '1' : '0';
            this.editAllocations = (row.allocations && row.allocations.length > 0)
                ? JSON.parse(JSON.stringify(row.allocations))
                : [{ name: '', method: 'persentase', amount: '', account_id: '' }];
            this.$nextTick(() => {
                this.$refs.editIncomeTypeModal.showModal();
            });
        }
    }"
    @table-edit="openEdit($event.detail)"
    x-init="
        @if($errors->any() && !old('_method')) $nextTick(() => $refs.incomeTypeModal.showModal()); @endif
        @if(session('success_modal')) $nextTick(() => $refs.successModal.showModal()); @endif
    ">

    @if(session('success') && !session('success_modal'))
        <div role="status" class="work-notice">{{ session('success') }}</div>
    @endif

    <div class="work-head mb-0">
        <p class="work-muted">Mengelola jenis pemasukan yang akan digunakan di sistem.</p>
        <button type="button" class="work-btn work-btn-primary" @click="$refs.incomeTypeModal.showModal()">
            <svg class="w-4 h-4 mr-1.5 -ml-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Jenis Pemasukan
        </button>
    </div>

    <x-common.data-table
        :rows="$incomeRows"
        :columns="$incomeColumns"
        caption="Daftar jenis pemasukan"
        search-label="Cari jenis pemasukan"
        row-label="jenis pemasukan"
        subtitle="Daftar jenis pemasukan sekolah"
        :show-actions="false"
        :show-avatar="false"
        :exportable="false"
        empty-message="Belum ada jenis pemasukan."
        empty-hint="Pilih Jenis Pemasukan untuk menambahkan master jenis pemasukan pertama."
    />

    {{-- MODAL TAMBAH JENIS PEMASUKAN --}}
    <dialog x-ref="incomeTypeModal" class="account-dialog work" style="max-width: 48rem; width: 100%;" aria-labelledby="income-modal-title" aria-describedby="income-modal-help"
        @close="saving = false" @cancel="if (saving) $event.preventDefault()"
        @click="const bounds = $el.getBoundingClientRect(); if (!saving && ($event.clientX < bounds.left || $event.clientX > bounds.right || $event.clientY < bounds.top || $event.clientY > bounds.bottom)) $el.close()">
        <div class="account-dialog-head">
            <div class="min-w-0">
                <h2 id="income-modal-title" class="text-lg font-semibold text-gray-900 dark:text-white">Tambah Jenis Pemasukan</h2>
                <p id="income-modal-help" class="work-muted text-xs mt-0.5">Lengkapi form berikut untuk menambahkan jenis pemasukan baru.</p>
            </div>
            <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors p-1 rounded-md" @click="$refs.incomeTypeModal.close()" :disabled="saving" aria-label="Tutup form">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <form method="POST" action="{{ route('finance.income-types.store') }}" @submit="saving = true" :aria-busy="saving">
            @csrf

            @if($errors->any() && !old('_method'))
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
                {{-- Nama --}}
                <div class="work-field">
                    <label for="income-name">Nama <span aria-hidden="true" class="text-red-500">*</span></label>
                    <input id="income-name" name="name" value="{{ old('name') }}" type="text" maxlength="120" required placeholder="Contoh: SPP"
                        @if(($firstError && $firstError === 'name') || !$firstError) autofocus @endif
                        @if($errors->has('name')) aria-invalid="true" aria-describedby="error-name" @endif>
                    @error('name')<p id="error-name" class="work-muted work-error text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                {{-- Kode (Otomatis) --}}
                <div class="work-field">
                    <label for="income-code">Kode</label>
                    <input id="income-code" name="code" value="{{ old('code', $nextCode) }}" type="text" maxlength="40" readonly
                        class="bg-gray-100 dark:bg-gray-800/80 text-gray-600 dark:text-gray-300 cursor-not-allowed font-mono text-sm"
                        aria-describedby="code-help">
                    <p id="code-help" class="work-muted text-xs mt-1">Kode dibuat otomatis oleh sistem</p>
                    @error('code')<p id="error-code" class="work-muted work-error text-xs mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            {{-- Kategori --}}
            <div class="work-field">
                <label for="income-category">Kategori <span aria-hidden="true" class="text-red-500">*</span></label>
                <select id="income-category" name="category" required
                    @if($firstError === 'category') autofocus @endif
                    @if($errors->has('category')) aria-invalid="true" aria-describedby="error-category" @endif>
                    <option value="" disabled @selected(!old('category'))>Pilih kategori</option>
                    @foreach($categories as $category)
                        <option value="{{ $category }}" @selected(old('category') === $category)>{{ $category }}</option>
                    @endforeach
                </select>
                @error('category')<p id="error-category" class="work-muted work-error text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            {{-- Menggunakan Alokasi Dana --}}
            <div class="work-field">
                <label class="block text-sm font-medium mb-1.5 text-gray-700 dark:text-gray-300">Menggunakan Alokasi Dana</label>
                <div class="flex items-center gap-2.5">
                    <button type="button" @click="usesAllocation = '1'"
                        :class="{ 'is-active': usesAllocation == '1' }"
                        class="toggle-choice-btn toggle-choice-yes">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span>Ya</span>
                    </button>
                    <button type="button" @click="usesAllocation = '0'"
                        :class="{ 'is-active': usesAllocation == '0' }"
                        class="toggle-choice-btn toggle-choice-no">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        <span>Tidak</span>
                    </button>
                    <input type="hidden" name="uses_allocation" :value="usesAllocation">
                </div>
            </div>

            {{-- FORM ALOKASI DANA KETIKA DIPILIH YA --}}
            <div x-show="usesAllocation == '1'" x-cloak class="p-3.5 bg-gray-50 dark:bg-gray-800/60 rounded-xl border border-gray-200 dark:border-gray-700/80 space-y-3">
                <div class="flex items-center justify-between pb-2 border-b border-gray-200 dark:border-gray-700">
                    <div>
                        <h4 class="text-xs font-bold uppercase tracking-wider text-gray-800 dark:text-gray-200">Form Alokasi Dana</h4>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400">Atur porsi pembagian dana ke dompet penyimpanan.</p>
                    </div>
                    <button type="button" @click="addAllocation()" class="work-btn text-xs py-1 px-2.5 h-auto text-primary-600 dark:text-primary-400 border-primary-200 dark:border-primary-800 bg-white dark:bg-gray-800 hover:bg-primary-50">
                        <svg class="w-3.5 h-3.5 mr-1 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Tambah Alokasi
                    </button>
                </div>

                <div class="space-y-2.5">
                    <template x-for="(alloc, index) in allocations" :key="index">
                        <div class="p-2.5 bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700/60 shadow-2xs space-y-2">
                            <div class="grid grid-cols-1 sm:grid-cols-12 gap-2.5 items-center">
                                {{-- Nama Alokasi --}}
                                <div class="sm:col-span-4">
                                    <label class="block text-[11px] font-medium text-gray-600 dark:text-gray-300 mb-1">Nama Alokasi <span class="text-red-500">*</span></label>
                                    <input type="text" x-model="alloc.name" :name="'allocations[' + index + '][name]'" required placeholder="Misal: Kas Utama / Tabungan" class="text-xs py-1.5 px-2.5 min-h-[36px]">
                                </div>

                                {{-- Metode: Persentase / Angka --}}
                                <div class="sm:col-span-3">
                                    <label class="block text-[11px] font-medium text-gray-600 dark:text-gray-300 mb-1">Metode <span class="text-red-500">*</span></label>
                                    <select x-model="alloc.method" :name="'allocations[' + index + '][method]'" required class="text-xs py-1.5 px-2 min-h-[36px]">
                                        <option value="persentase">Persentase (%)</option>
                                        <option value="nominal">Angka / Nominal (Rp)</option>
                                    </select>
                                </div>

                                {{-- Nominal / Angka --}}
                                <div class="sm:col-span-2">
                                    <label class="block text-[11px] font-medium text-gray-600 dark:text-gray-300 mb-1" x-text="alloc.method === 'persentase' ? 'Porsi (%)' : 'Nominal'"></label>
                                    <div class="relative">
                                        <input type="number" min="0" :step="alloc.method === 'persentase' ? '0.01' : '1000'" :max="alloc.method === 'persentase' ? '100' : '999999999999'" x-model.number="alloc.amount" :name="'allocations[' + index + '][amount]'" required :placeholder="alloc.method === 'persentase' ? '50' : '500000'" class="text-xs py-1.5 pl-2 pr-6 min-h-[36px]">
                                        <span class="absolute right-2 top-1/2 -translate-y-1/2 text-[10px] font-bold text-gray-400" x-text="alloc.method === 'persentase' ? '%' : 'Rp'"></span>
                                    </div>
                                </div>

                                {{-- Dompet Penyimpanan --}}
                                <div class="sm:col-span-3 flex items-end gap-1.5">
                                    <div class="flex-1 min-w-0">
                                        <label class="block text-[11px] font-medium text-gray-600 dark:text-gray-300 mb-1 truncate">Dompet Penyimpanan <span class="text-red-500">*</span></label>
                                        <select x-model="alloc.account_id" :name="'allocations[' + index + '][account_id]'" required class="text-xs py-1.5 px-2 min-h-[36px]">
                                            <option value="" disabled>-- Pilih Dompet --</option>
                                            @foreach($accounts as $account)
                                                <option value="{{ $account->id }}">{{ $account->name }} ({{ $account->type }})</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <template x-if="allocations.length > 1">
                                        <button type="button" @click="removeAllocation(index)" class="p-1.5 text-gray-400 hover:text-red-500 transition rounded-md" title="Hapus baris alokasi" aria-label="Hapus baris alokasi">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- 1 ROW YANG MENAMPILKAN TOTAL NOMINAL ATAU TOTAL PERSENTASE --}}
                <div class="p-3 bg-white dark:bg-gray-900 rounded-lg border border-primary-200 dark:border-primary-800/60 flex flex-wrap items-center justify-between gap-2 text-xs">
                    <div class="font-bold text-gray-800 dark:text-gray-200 flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                        <span>Total Alokasi:</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <template x-if="hasPercentage(allocations)">
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-md font-semibold text-xs border"
                                :class="getTotalPercentage(allocations) === 100 ? 'bg-emerald-50 text-emerald-800 border-emerald-300 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-700' : 'bg-amber-50 text-amber-900 border-amber-300 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-700'">
                                <span>Total Persentase:</span>
                                <strong x-text="getTotalPercentage(allocations) + '%'"></strong>
                                <span x-show="getTotalPercentage(allocations) === 100" class="text-emerald-600 dark:text-emerald-400 font-bold">✓</span>
                                <span x-show="getTotalPercentage(allocations) !== 100" class="text-amber-600 dark:text-amber-400 text-[11px]" x-text="'(Belum 100%)'"></span>
                            </span>
                        </template>
                        <template x-if="hasNominal(allocations)">
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-md font-semibold text-xs border bg-blue-50 text-blue-900 border-blue-300 dark:bg-blue-950/60 dark:text-blue-300 dark:border-blue-700">
                                <span>Total Nominal:</span>
                                <strong x-text="formatRupiah(getTotalNominal(allocations))"></strong>
                            </span>
                        </template>
                        <template x-if="!hasPercentage(allocations) && !hasNominal(allocations)">
                            <span class="text-gray-400 italic">Belum ada nominal / persentase</span>
                        </template>
                    </div>
                </div>
            </div>

            {{-- Perlu Approval Kepala Sekolah --}}
            <div class="work-field">
                <label class="block text-sm font-medium mb-1.5 text-gray-700 dark:text-gray-300">Perlu Approval Kepala Sekolah</label>
                <div class="flex items-center gap-2.5">
                    <button type="button" @click="requiresApproval = '1'"
                        :class="{ 'is-active': requiresApproval == '1' }"
                        class="toggle-choice-btn toggle-choice-yes">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span>Ya</span>
                    </button>
                    <button type="button" @click="requiresApproval = '0'"
                        :class="{ 'is-active': requiresApproval == '0' }"
                        class="toggle-choice-btn toggle-choice-no">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        <span>Tidak</span>
                    </button>
                    <input type="hidden" name="requires_approval" :value="requiresApproval">
                </div>
            </div>

            {{-- Tombol Batal & Simpan --}}
            <div class="work-actions mt-4 flex items-center justify-end gap-3 pt-2">
                <button type="button" class="work-btn" @click="$refs.incomeTypeModal.close()" :disabled="saving">
                    <svg class="w-4 h-4 mr-1 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    Batal
                </button>
                <button type="submit" :disabled="saving" class="work-btn work-btn-primary">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span x-text="saving ? 'Menyimpan…' : 'Simpan'">Simpan</span>
                </button>
            </div>
        </form>
    </dialog>

    {{-- MODAL SUKSES --}}
    <dialog x-ref="successModal" class="account-dialog work text-center" style="max-width: 26rem; padding: 2rem 1.5rem;"
        aria-labelledby="success-modal-title"
        @click="const bounds = $el.getBoundingClientRect(); if ($event.clientX < bounds.left || $event.clientX > bounds.right || $event.clientY < bounds.top || $event.clientY > bounds.bottom) $el.close()">
        <div class="flex flex-col items-center">
            <div class="w-16 h-16 rounded-full bg-emerald-100 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mb-4 shadow-sm">
                <svg class="w-9 h-9" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
            </div>
            <h3 id="success-modal-title" class="text-lg font-bold text-gray-900 dark:text-white mb-2">Jenis Pemasukan Disimpan</h3>
            <p class="text-sm text-gray-600 dark:text-gray-300 mb-6 leading-relaxed">
                {{ session('success') ?? 'Data jenis pemasukan berhasil disimpan ke dalam sistem.' }}
            </p>
            <button type="button" @click="$refs.successModal.close()" class="work-btn work-btn-primary w-full justify-center py-2.5 text-sm font-semibold">
                OK
            </button>
        </div>
    </dialog>

    {{-- MODAL UBAH JENIS PEMASUKAN --}}
    <dialog x-ref="editIncomeTypeModal" class="account-dialog work" style="max-width: 48rem; width: 100%;" aria-labelledby="edit-income-modal-title"
        @close="saving = false" @cancel="if (saving) $event.preventDefault()"
        @click="const bounds = $el.getBoundingClientRect(); if (!saving && ($event.clientX < bounds.left || $event.clientX > bounds.right || $event.clientY < bounds.top || $event.clientY > bounds.bottom)) $el.close()">
        <div class="account-dialog-head">
            <div class="min-w-0">
                <h2 id="edit-income-modal-title" class="text-lg font-semibold text-gray-900 dark:text-white">Ubah Jenis Pemasukan</h2>
                <p class="work-muted text-xs mt-0.5">Perbarui master data jenis pemasukan.</p>
            </div>
            <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors p-1 rounded-md" @click="$refs.editIncomeTypeModal.close()" :disabled="saving" aria-label="Tutup form">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <form method="POST" :action="editingIncome ? editingIncome.update_url : ''" @submit="saving = true" :aria-busy="saving">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                {{-- Nama --}}
                <div class="work-field">
                    <label for="edit-income-name">Nama <span aria-hidden="true" class="text-red-500">*</span></label>
                    <input id="edit-income-name" name="name" :value="editingIncome ? editingIncome.name : ''" type="text" maxlength="120" required placeholder="Contoh: SPP">
                </div>

                {{-- Kode (Readonly) --}}
                <div class="work-field">
                    <label for="edit-income-code">Kode</label>
                    <input id="edit-income-code" name="code" :value="editingIncome ? editingIncome.code : ''" type="text" maxlength="40" readonly
                        class="bg-gray-100 dark:bg-gray-800/80 text-gray-600 dark:text-gray-300 cursor-not-allowed font-mono text-sm">
                    <p class="work-muted text-xs mt-1">Kode jenis pemasukan tidak dapat diubah</p>
                </div>
            </div>

            {{-- Kategori --}}
            <div class="work-field">
                <label for="edit-income-category">Kategori <span aria-hidden="true" class="text-red-500">*</span></label>
                <select id="edit-income-category" name="category" required>
                    @foreach($categories as $category)
                        <option value="{{ $category }}" :selected="editingIncome && String(editingIncome.category_raw).toLowerCase() === '{{ strtolower($category) }}'">{{ $category }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Menggunakan Alokasi Dana --}}
            <div class="work-field">
                <label class="block text-sm font-medium mb-1.5 text-gray-700 dark:text-gray-300">Menggunakan Alokasi Dana</label>
                <div class="flex items-center gap-2.5">
                    <button type="button" @click="editUsesAllocation = '1'"
                        :class="{ 'is-active': editUsesAllocation == '1' }"
                        class="toggle-choice-btn toggle-choice-yes">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span>Ya</span>
                    </button>
                    <button type="button" @click="editUsesAllocation = '0'"
                        :class="{ 'is-active': editUsesAllocation == '0' }"
                        class="toggle-choice-btn toggle-choice-no">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        <span>Tidak</span>
                    </button>
                    <input type="hidden" name="uses_allocation" :value="editUsesAllocation">
                </div>
            </div>

            {{-- FORM ALOKASI DANA DI MODAL EDIT --}}
            <div x-show="editUsesAllocation == '1'" x-cloak class="p-3.5 bg-gray-50 dark:bg-gray-800/60 rounded-xl border border-gray-200 dark:border-gray-700/80 space-y-3">
                <div class="flex items-center justify-between pb-2 border-b border-gray-200 dark:border-gray-700">
                    <div>
                        <h4 class="text-xs font-bold uppercase tracking-wider text-gray-800 dark:text-gray-200">Form Alokasi Dana</h4>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400">Atur porsi pembagian dana ke dompet penyimpanan.</p>
                    </div>
                    <button type="button" @click="addEditAllocation()" class="work-btn text-xs py-1 px-2.5 h-auto text-primary-600 dark:text-primary-400 border-primary-200 dark:border-primary-800 bg-white dark:bg-gray-800 hover:bg-primary-50">
                        <svg class="w-3.5 h-3.5 mr-1 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Tambah Alokasi
                    </button>
                </div>

                <div class="space-y-2.5">
                    <template x-for="(alloc, index) in editAllocations" :key="index">
                        <div class="p-2.5 bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700/60 shadow-2xs space-y-2">
                            <div class="grid grid-cols-1 sm:grid-cols-12 gap-2.5 items-center">
                                {{-- Nama Alokasi --}}
                                <div class="sm:col-span-4">
                                    <label class="block text-[11px] font-medium text-gray-600 dark:text-gray-300 mb-1">Nama Alokasi <span class="text-red-500">*</span></label>
                                    <input type="text" x-model="alloc.name" :name="'allocations[' + index + '][name]'" required placeholder="Misal: Kas Utama / Tabungan" class="text-xs py-1.5 px-2.5 min-h-[36px]">
                                </div>

                                {{-- Metode: Persentase / Angka --}}
                                <div class="sm:col-span-3">
                                    <label class="block text-[11px] font-medium text-gray-600 dark:text-gray-300 mb-1">Metode <span class="text-red-500">*</span></label>
                                    <select x-model="alloc.method" :name="'allocations[' + index + '][method]'" required class="text-xs py-1.5 px-2 min-h-[36px]">
                                        <option value="persentase">Persentase (%)</option>
                                        <option value="nominal">Angka / Nominal (Rp)</option>
                                    </select>
                                </div>

                                {{-- Nominal / Angka --}}
                                <div class="sm:col-span-2">
                                    <label class="block text-[11px] font-medium text-gray-600 dark:text-gray-300 mb-1" x-text="alloc.method === 'persentase' ? 'Porsi (%)' : 'Nominal'"></label>
                                    <div class="relative">
                                        <input type="number" min="0" :step="alloc.method === 'persentase' ? '0.01' : '1000'" :max="alloc.method === 'persentase' ? '100' : '999999999999'" x-model.number="alloc.amount" :name="'allocations[' + index + '][amount]'" required :placeholder="alloc.method === 'persentase' ? '50' : '500000'" class="text-xs py-1.5 pl-2 pr-6 min-h-[36px]">
                                        <span class="absolute right-2 top-1/2 -translate-y-1/2 text-[10px] font-bold text-gray-400" x-text="alloc.method === 'persentase' ? '%' : 'Rp'"></span>
                                    </div>
                                </div>

                                {{-- Dompet Penyimpanan --}}
                                <div class="sm:col-span-3 flex items-end gap-1.5">
                                    <div class="flex-1 min-w-0">
                                        <label class="block text-[11px] font-medium text-gray-600 dark:text-gray-300 mb-1 truncate">Dompet Penyimpanan <span class="text-red-500">*</span></label>
                                        <select x-model="alloc.account_id" :name="'allocations[' + index + '][account_id]'" required class="text-xs py-1.5 px-2 min-h-[36px]">
                                            <option value="" disabled>-- Pilih Dompet --</option>
                                            @foreach($accounts as $account)
                                                <option value="{{ $account->id }}">{{ $account->name }} ({{ $account->type }})</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <template x-if="editAllocations.length > 1">
                                        <button type="button" @click="removeEditAllocation(index)" class="p-1.5 text-gray-400 hover:text-red-500 transition rounded-md" title="Hapus baris alokasi" aria-label="Hapus baris alokasi">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- 1 ROW YANG MENAMPILKAN TOTAL NOMINAL ATAU TOTAL PERSENTASE --}}
                <div class="p-3 bg-white dark:bg-gray-900 rounded-lg border border-primary-200 dark:border-primary-800/60 flex flex-wrap items-center justify-between gap-2 text-xs">
                    <div class="font-bold text-gray-800 dark:text-gray-200 flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                        <span>Total Alokasi:</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <template x-if="hasPercentage(editAllocations)">
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-md font-semibold text-xs border"
                                :class="getTotalPercentage(editAllocations) === 100 ? 'bg-emerald-50 text-emerald-800 border-emerald-300 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-700' : 'bg-amber-50 text-amber-900 border-amber-300 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-700'">
                                <span>Total Persentase:</span>
                                <strong x-text="getTotalPercentage(editAllocations) + '%'"></strong>
                                <span x-show="getTotalPercentage(editAllocations) === 100" class="text-emerald-600 dark:text-emerald-400 font-bold">✓</span>
                                <span x-show="getTotalPercentage(editAllocations) !== 100" class="text-amber-600 dark:text-amber-400 text-[11px]" x-text="'(Belum 100%)'"></span>
                            </span>
                        </template>
                        <template x-if="hasNominal(editAllocations)">
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-md font-semibold text-xs border bg-blue-50 text-blue-900 border-blue-300 dark:bg-blue-950/60 dark:text-blue-300 dark:border-blue-700">
                                <span>Total Nominal:</span>
                                <strong x-text="formatRupiah(getTotalNominal(editAllocations))"></strong>
                            </span>
                        </template>
                        <template x-if="!hasPercentage(editAllocations) && !hasNominal(editAllocations)">
                            <span class="text-gray-400 italic">Belum ada nominal / persentase</span>
                        </template>
                    </div>
                </div>
            </div>

            {{-- Perlu Approval Kepala Sekolah --}}
            <div class="work-field">
                <label class="block text-sm font-medium mb-1.5 text-gray-700 dark:text-gray-300">Perlu Approval Kepala Sekolah</label>
                <div class="flex items-center gap-2.5">
                    <button type="button" @click="editRequiresApproval = '1'"
                        :class="{ 'is-active': editRequiresApproval == '1' }"
                        class="toggle-choice-btn toggle-choice-yes">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span>Ya</span>
                    </button>
                    <button type="button" @click="editRequiresApproval = '0'"
                        :class="{ 'is-active': editRequiresApproval == '0' }"
                        class="toggle-choice-btn toggle-choice-no">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        <span>Tidak</span>
                    </button>
                    <input type="hidden" name="requires_approval" :value="editRequiresApproval">
                </div>
            </div>

            {{-- Tombol Batal & Simpan --}}
            <div class="work-actions mt-4 flex items-center justify-end gap-3 pt-2">
                <button type="button" class="work-btn" @click="$refs.editIncomeTypeModal.close()" :disabled="saving">
                    <svg class="w-4 h-4 mr-1 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    Batal
                </button>
                <button type="submit" :disabled="saving" class="work-btn work-btn-primary">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span x-text="saving ? 'Menyimpan…' : 'Simpan Perubahan'">Simpan Perubahan</span>
                </button>
            </div>
        </form>
    </dialog>
</div>
@endsection
