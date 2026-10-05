@extends('layouts.app')

@section('content')
@php
    $bosColumns = [
        ['key' => 'no', 'label' => 'No', 'sortable' => false],
        ['key' => 'source_funding', 'label' => 'Sumber Dana', 'bold' => true],
        ['key' => 'name', 'label' => 'Komponen', 'bold' => false],
        ['key' => 'aksi', 'label' => 'Aksi', 'sortable' => false, 'onlyEdit' => true, 'allowDelete' => true],
    ];

    $bosRows = $categories->values()->map(function ($cat, $index) {
        return [
            'id' => $cat->id,
            'no' => $index + 1,
            'source_funding' => $cat->source_funding,
            'name' => $cat->name,
            'code' => $cat->code,
            'only_edit' => true,
            'allow_delete' => true,
            'update_url' => route('bos.anggaran.update', $cat),
            'delete_url' => route('bos.anggaran.destroy', $cat),
        ];
    });

    $defaultSourcesList = ['BOS Reguler', 'BOS Kinerja', 'BOP', 'DAK'];
    $allSources = array_values(array_unique(array_merge($defaultSourcesList, $sources ?? [])));

    $defaultComponentsList = ['Belanja pegawai', 'Belanja barang', 'Belanja modal', 'Pembelajaran', 'Pemeliharaan'];
    $allComponents = array_values(array_unique(array_merge($defaultComponentsList, $components ?? [])));
@endphp

<x-common.page-breadcrumb pageTitle="BOS" label="Master Data Keuangan" />

<div class="work work-stack"
    x-data="{
        sourceFunding: '',
        componentName: '',
        customSource: '',
        customComponent: '',
        submitted: false,
        submitting: false,
        editingCategory: null,
        deletingCategory: null,

        validateAndSubmit(e) {
            this.submitted = true;
            const hasSource = this.sourceFunding && (this.sourceFunding !== 'Lainnya' || this.customSource.trim() !== '');
            const hasComponent = this.componentName && (this.componentName !== 'Lainnya' || this.customComponent.trim() !== '');

            if (!hasSource || !hasComponent) {
                e.preventDefault();
                return false;
            }

            this.submitting = true;
            return true;
        },

        openAdd() {
            this.sourceFunding = '';
            this.componentName = '';
            this.customSource = '';
            this.customComponent = '';
            this.submitted = false;
            this.submitting = false;
            this.$refs.bosModal.showModal();
        },

        openEdit(row) {
            this.editingCategory = {
                id: row.id,
                source_funding: row.source_funding,
                name: row.name,
                custom_source: '',
                custom_component: '',
                is_custom_source: !@js($allSources).includes(row.source_funding),
                is_custom_component: !@js($allComponents).includes(row.name),
            };
            this.$nextTick(() => {
                this.$refs.editBosModal.showModal();
            });
        },

        openDelete(row) {
            this.deletingCategory = row;
            this.$nextTick(() => {
                this.$refs.deleteBosModal.showModal();
            });
        }
    }"
    @table-edit="openEdit($event.detail)"
    @table-delete="openDelete($event.detail)"
    x-init="
        @if(session('success_modal'))
            $nextTick(() => $refs.successModal.showModal());
        @endif
        @if($errors->any() && !old('_method'))
            $nextTick(() => $refs.bosModal.showModal());
        @endif
    ">

    @if(session('success') && !session('success_modal'))
        <div role="status" class="work-notice">{{ session('success') }}</div>
    @endif

    {{-- METRIC SUMMARY CARDS --}}
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4 mb-2">
        @foreach($metrics as $metric)
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-xs dark:border-gray-800 dark:bg-gray-900">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $metric['label'] }}</p>
                <p class="mt-1 text-xl font-bold tracking-tight tabular-nums text-gray-900 dark:text-white">{{ $metric['value'] }}</p>
                @if(!empty($metric['sub']))
                    <p class="text-[11px] text-emerald-600 dark:text-emerald-400 font-medium mt-0.5">{{ $metric['sub'] }}</p>
                @endif
            </div>
        @endforeach
    </div>

    {{-- HEADER ACTION --}}
    <div class="work-head mb-0">
        <div>
            <h2 class="text-base font-bold text-gray-900 dark:text-white">Data Sumber Dana BOS</h2>
            <p class="work-muted text-xs mt-0.5">Kelola sumber dana dan komponen alokasi BOS dengan mudah.</p>
        </div>
        <button type="button" class="work-btn work-btn-primary" @click="openAdd()">
            <svg class="w-4 h-4 mr-1.5 -ml-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Tambah BOS
        </button>
    </div>

    {{-- DATA TABLE --}}
    <x-common.data-table
        :rows="$bosRows"
        :columns="$bosColumns"
        caption="Data Sumber Dana BOS"
        search-label="Cari sumber dana atau komponen..."
        row-label="data BOS"
        subtitle="Daftar sumber dana dan komponen yang terdaftar pada sistem"
        :show-actions="false"
        :show-avatar="false"
        :exportable="true"
        export-label="Export Data BOS"
        empty-message="Belum ada data sumber dana BOS."
        empty-hint="Pilih Tambah BOS untuk menambahkan data pertama."
    />

    {{-- MODAL TAMBAH DATA BOS (STEP 5 & STEP 6) --}}
    <dialog x-ref="bosModal" class="account-dialog work relative max-w-lg" aria-labelledby="bos-modal-title"
        @close="submitting = false; submitted = false;" @cancel="if (submitting) $event.preventDefault()"
        @click="const bounds = $el.getBoundingClientRect(); if (!submitting && ($event.clientX < bounds.left || $event.clientX > bounds.right || $event.clientY < bounds.top || $event.clientY > bounds.bottom)) $el.close()">
        
        {{-- HEADER MODAL --}}
        <div class="account-dialog-head">
            <div class="min-w-0">
                <h2 id="bos-modal-title" class="text-lg font-bold text-gray-900 dark:text-white">Tambah Data BOS</h2>
            </div>
            <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors p-1 rounded-md" @click="$refs.bosModal.close()" :disabled="submitting" aria-label="Tutup form">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        {{-- FORM BODY --}}
        <form method="POST" action="{{ route('bos.anggaran.store') }}" @submit="if(!validateAndSubmit($event)) $event.preventDefault()">
            @csrf

            {{-- STEP 6: VALIDASI DATA ERROR BANNER --}}
            <div x-cloak x-show="submitted && (!sourceFunding || !componentName || (sourceFunding === 'Lainnya' && !customSource.trim()) || (componentName === 'Lainnya' && !customComponent.trim()))"
                class="mb-4 rounded-xl border border-rose-200 bg-rose-50 p-4 text-xs text-rose-800 dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-300">
                <div class="flex items-start gap-3">
                    <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-rose-200 text-rose-700 dark:bg-rose-900 dark:text-rose-200 font-bold text-xs">!</span>
                    <div>
                        <p class="font-bold text-sm text-rose-900 dark:text-rose-200">Data belum lengkap</p>
                        <p class="mt-0.5 text-xs text-rose-700 dark:text-rose-300">Mohon lengkapi data berikut:</p>
                        <ul class="list-disc list-inside mt-1.5 space-y-0.5 text-xs font-medium">
                            <template x-if="!sourceFunding || (sourceFunding === 'Lainnya' && !customSource.trim())">
                                <li>Sumber Dana</li>
                            </template>
                            <template x-if="!componentName || (componentName === 'Lainnya' && !customComponent.trim())">
                                <li>Komponen BOS</li>
                            </template>
                        </ul>
                    </div>
                </div>
            </div>

            @if($errors->any())
                <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 p-3 text-xs text-rose-800 dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-300">
                    {{ $errors->first() }}
                </div>
            @endif

            {{-- 1. SUMBER DANA --}}
            <div class="work-field mb-3.5">
                <label for="bos-source-funding" class="block text-sm font-semibold text-gray-800 dark:text-gray-200 mb-1">
                    Sumber Dana <span aria-hidden="true" class="text-rose-500">*</span>
                </label>
                <select id="bos-source-funding" name="source_funding" x-model="sourceFunding"
                    :class="{'border-rose-500 ring-1 ring-rose-500': submitted && (!sourceFunding || (sourceFunding === 'Lainnya' && !customSource.trim()))}"
                    class="w-full text-sm rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3.5 py-2.5">
                    <option value="" disabled selected>Pilih sumber dana</option>
                    @foreach($allSources as $src)
                        <option value="{{ $src }}">{{ $src }}</option>
                    @endforeach
                    <option value="Lainnya">+ Sumber Dana Lainnya...</option>
                </select>
                <p x-show="!submitted || (sourceFunding && (sourceFunding !== 'Lainnya' || customSource.trim()))" class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    Pilih sumber dana BOS yang tersedia.
                </p>
                <p x-cloak x-show="submitted && !sourceFunding" class="text-xs text-rose-600 dark:text-rose-400 font-medium mt-1">
                    Kolom ini wajib diisi.
                </p>

                {{-- Input Custom Sumber Dana jika 'Lainnya' dipilih --}}
                <div x-cloak x-show="sourceFunding === 'Lainnya'" class="mt-2">
                    <input type="text" name="custom_source" x-model="customSource" placeholder="Ketik nama sumber dana baru..."
                        :class="{'border-rose-500 ring-1 ring-rose-500': submitted && sourceFunding === 'Lainnya' && !customSource.trim()}"
                        class="w-full text-sm rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3.5 py-2">
                    <p x-cloak x-show="submitted && sourceFunding === 'Lainnya' && !customSource.trim()" class="text-xs text-rose-600 dark:text-rose-400 font-medium mt-1">
                        Nama sumber dana wajib diisi.
                    </p>
                </div>
            </div>

            {{-- 2. KOMPONEN BOS --}}
            <div class="work-field mb-4">
                <label for="bos-component-name" class="block text-sm font-semibold text-gray-800 dark:text-gray-200 mb-1">
                    Komponen BOS <span aria-hidden="true" class="text-rose-500">*</span>
                </label>
                <select id="bos-component-name" name="name" x-model="componentName"
                    :class="{'border-rose-500 ring-1 ring-rose-500': submitted && (!componentName || (componentName === 'Lainnya' && !customComponent.trim()))}"
                    class="w-full text-sm rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3.5 py-2.5">
                    <option value="" disabled selected>Pilih komponen</option>
                    @foreach($allComponents as $cmp)
                        <option value="{{ $cmp }}">{{ $cmp }}</option>
                    @endforeach
                    <option value="Lainnya">+ Komponen Lainnya...</option>
                </select>
                <p x-show="!submitted || (componentName && (componentName !== 'Lainnya' || customComponent.trim()))" class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    Pilih komponen penggunaan dana BOS.
                </p>
                <p x-cloak x-show="submitted && !componentName" class="text-xs text-rose-600 dark:text-rose-400 font-medium mt-1">
                    Kolom ini wajib diisi.
                </p>

                {{-- Input Custom Komponen jika 'Lainnya' dipilih --}}
                <div x-cloak x-show="componentName === 'Lainnya'" class="mt-2">
                    <input type="text" name="custom_component" x-model="customComponent" placeholder="Ketik nama komponen baru..."
                        :class="{'border-rose-500 ring-1 ring-rose-500': submitted && componentName === 'Lainnya' && !customComponent.trim()}"
                        class="w-full text-sm rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3.5 py-2">
                    <p x-cloak x-show="submitted && componentName === 'Lainnya' && !customComponent.trim()" class="text-xs text-rose-600 dark:text-rose-400 font-medium mt-1">
                        Nama komponen wajib diisi.
                    </p>
                </div>
            </div>

            {{-- 3. INFO BOX --}}
            <div class="mb-5 rounded-xl border border-sky-200 bg-sky-50/70 p-3.5 text-xs text-sky-900 dark:border-sky-900/60 dark:bg-sky-950/40 dark:text-sky-300 flex items-start gap-2.5">
                <svg class="w-4 h-4 text-sky-600 dark:text-sky-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p>Sumber dana dan komponen BOS dapat ditambahkan oleh admin jika diperlukan.</p>
            </div>

            {{-- BUTTONS --}}
            <div class="work-actions flex items-center justify-end gap-2.5 pt-2 border-t border-gray-100 dark:border-gray-800">
                <button type="button" class="work-btn" @click="$refs.bosModal.close()" :disabled="submitting">Batal</button>
                <button type="submit" class="work-btn work-btn-primary" :disabled="submitting">
                    Simpan
                </button>
            </div>
        </form>

        {{-- STEP 7: OVERLAY MENYIMPAN DATA --}}
        <div x-cloak x-show="submitting"
            class="absolute inset-0 z-50 flex flex-col items-center justify-center rounded-2xl bg-white/90 dark:bg-gray-900/90 backdrop-blur-xs">
            <div class="h-10 w-10 animate-spin rounded-full border-3 border-brand-500 border-t-transparent"></div>
            <p class="mt-3 text-sm font-bold text-gray-900 dark:text-white">Menyimpan data...</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">Mohon tunggu sebentar.</p>
        </div>
    </dialog>

    {{-- STEP 8: NOTIFIKASI BERHASIL POPUP MODAL --}}
    <dialog x-ref="successModal" class="account-dialog work text-center max-w-sm" aria-labelledby="success-modal-title"
        @click="const bounds = $el.getBoundingClientRect(); if ($event.clientX < bounds.left || $event.clientX > bounds.right || $event.clientY < bounds.top || $event.clientY > bounds.bottom) $el.close()">
        <div class="p-6 flex flex-col items-center justify-center">
            <div class="w-14 h-14 rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-400 flex items-center justify-center mb-4">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <h3 id="success-modal-title" class="text-base font-bold text-gray-900 dark:text-white">
                Jenis pemasukan disimpan
            </h3>
            <p class="text-xs text-gray-600 dark:text-gray-300 mt-1.5 leading-relaxed">
                Data jenis pemasukan BOS berhasil disimpan ke dalam sistem.
            </p>
            <button type="button" @click="$refs.successModal.close()" class="mt-5 w-full py-2.5 px-4 bg-brand-600 hover:bg-brand-700 text-white font-semibold text-xs rounded-xl shadow-xs transition">
                OK
            </button>
        </div>
    </dialog>

    {{-- MODAL EDIT DATA BOS --}}
    <dialog x-ref="editBosModal" class="account-dialog work relative max-w-lg" aria-labelledby="edit-bos-modal-title"
        @click="const bounds = $el.getBoundingClientRect(); if ($event.clientX < bounds.left || $event.clientX > bounds.right || $event.clientY < bounds.top || $event.clientY > bounds.bottom) $el.close()">
        <div class="account-dialog-head">
            <div class="min-w-0">
                <h2 id="edit-bos-modal-title" class="text-lg font-bold text-gray-900 dark:text-white">Edit Data BOS</h2>
            </div>
            <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors p-1 rounded-md" @click="$refs.editBosModal.close()" aria-label="Tutup form">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form method="POST" :action="'/bos/anggaran/' + (editingCategory?.id || '')">
            @csrf
            @method('PUT')

            <div class="work-field mb-3.5">
                <label class="block text-sm font-semibold text-gray-800 dark:text-gray-200 mb-1">
                    Sumber Dana <span aria-hidden="true" class="text-rose-500">*</span>
                </label>
                <select name="source_funding" x-model="editingCategory.source_funding" required
                    class="w-full text-sm rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3.5 py-2.5">
                    @foreach($allSources as $src)
                        <option value="{{ $src }}">{{ $src }}</option>
                    @endforeach
                    <option value="Lainnya">+ Sumber Dana Lainnya...</option>
                </select>
                <div x-cloak x-show="editingCategory?.source_funding === 'Lainnya'" class="mt-2">
                    <input type="text" name="custom_source" x-model="editingCategory.custom_source" placeholder="Nama sumber dana baru..."
                        class="w-full text-sm rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3.5 py-2">
                </div>
            </div>

            <div class="work-field mb-4">
                <label class="block text-sm font-semibold text-gray-800 dark:text-gray-200 mb-1">
                    Komponen BOS <span aria-hidden="true" class="text-rose-500">*</span>
                </label>
                <select name="name" x-model="editingCategory.name" required
                    class="w-full text-sm rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3.5 py-2.5">
                    @foreach($allComponents as $cmp)
                        <option value="{{ $cmp }}">{{ $cmp }}</option>
                    @endforeach
                    <option value="Lainnya">+ Komponen Lainnya...</option>
                </select>
                <div x-cloak x-show="editingCategory?.name === 'Lainnya'" class="mt-2">
                    <input type="text" name="custom_component" x-model="editingCategory.custom_component" placeholder="Nama komponen baru..."
                        class="w-full text-sm rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3.5 py-2">
                </div>
            </div>

            <div class="work-actions flex items-center justify-end gap-2.5 pt-2 border-t border-gray-100 dark:border-gray-800">
                <button type="button" class="work-btn" @click="$refs.editBosModal.close()">Batal</button>
                <button type="submit" class="work-btn work-btn-primary">Perbarui</button>
            </div>
        </form>
    </dialog>

    {{-- MODAL HAPUS DATA BOS --}}
    <dialog x-ref="deleteBosModal" class="account-dialog work max-w-sm text-center" aria-labelledby="delete-bos-title"
        @click="const bounds = $el.getBoundingClientRect(); if ($event.clientX < bounds.left || $event.clientX > bounds.right || $event.clientY < bounds.top || $event.clientY > bounds.bottom) $el.close()">
        <form method="POST" :action="'/bos/anggaran/' + (deletingCategory?.id || '')" class="p-5">
            @csrf
            @method('DELETE')
            <div class="w-12 h-12 rounded-full bg-rose-100 text-rose-600 dark:bg-rose-950 dark:text-rose-400 flex items-center justify-center mx-auto mb-3">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            </div>
            <h3 id="delete-bos-title" class="text-sm font-bold text-gray-900 dark:text-white">Hapus Data Sumber Dana BOS?</h3>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                Data <span class="font-bold text-gray-700 dark:text-gray-300" x-text="deletingCategory?.source_funding + ' - ' + deletingCategory?.name"></span> akan dihapus dari daftar anggaran.
            </p>
            <div class="mt-4 flex items-center justify-center gap-2">
                <button type="button" class="work-btn" @click="$refs.deleteBosModal.close()">Batal</button>
                <button type="submit" class="work-btn bg-rose-600 hover:bg-rose-700 text-white border-rose-600">Hapus</button>
            </div>
        </form>
    </dialog>
</div>
@endsection
