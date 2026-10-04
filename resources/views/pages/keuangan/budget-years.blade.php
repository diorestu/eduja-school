@extends('layouts.app')

@section('content')
@php
    $yearColumns = [
        ['key' => 'name', 'label' => 'Nama Tahun Anggaran', 'bold' => true],
        ['key' => 'start_date', 'label' => 'Tanggal Mulai'],
        ['key' => 'end_date', 'label' => 'Tanggal Selesai'],
        ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
        ['key' => 'aksi', 'label' => 'Aksi', 'sortable' => false, 'onlyEdit' => true],
    ];

    $yearRows = $budgetYears->map(function ($year) {
        $isActive = ($year->status ?? 'active') === 'active';
        return [
            'id' => $year->id,
            'name' => $year->name,
            'start_date' => $year->start_date ? \Carbon\Carbon::parse($year->start_date)->translatedFormat('d M Y') : '-',
            'end_date' => $year->end_date ? \Carbon\Carbon::parse($year->end_date)->translatedFormat('d M Y') : '-',
            'status' => $isActive ? 'Aktif' : 'Tutup',
            'status_raw' => $year->status ?? 'active',
            'start_date_raw' => $year->start_date ? \Carbon\Carbon::parse($year->start_date)->format('Y-m-d') : '',
            'end_date_raw' => $year->end_date ? \Carbon\Carbon::parse($year->end_date)->format('Y-m-d') : '',
            'update_url' => route('finance.budget-years.update', $year),
            'only_edit' => true,
        ];
    });

    $firstError = array_key_first($errors->getMessages());
@endphp

<x-common.page-breadcrumb pageTitle="Tahun Anggaran" label="Perencanaan Anggaran" />

<div class="work work-stack"
    x-data="{
        saving: false,
        editingYear: null,
        editName: '',
        editStartDate: '',
        editEndDate: '',
        editStatus: 'active',
        openEdit(row) {
            this.editingYear = row;
            this.editName = row.name || '';
            this.editStartDate = row.start_date_raw || '';
            this.editEndDate = row.end_date_raw || '';
            this.editStatus = row.status_raw || 'active';
            this.$nextTick(() => {
                this.$refs.editYearModal.showModal();
            });
        }
    }"
    @table-edit="openEdit($event.detail)"
    x-init="
        @if($errors->any() && !old('_method')) $nextTick(() => $refs.yearModal.showModal()); @endif
    ">

    @if(session('success'))
        <div role="status" class="work-notice">{{ session('success') }}</div>
    @endif

    <div class="work-head mb-0">
        <p class="work-muted">Periode tahun anggaran yang tersedia untuk perencanaan kegiatan sekolah.</p>
        <button type="button" class="work-btn work-btn-primary" @click="$refs.yearModal.showModal()">
            <svg class="w-4 h-4 mr-1.5 -ml-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Tahun Anggaran
        </button>
    </div>

    <x-common.data-table
        :rows="$yearRows"
        :columns="$yearColumns"
        caption="Daftar Tahun Anggaran"
        search-label="Cari tahun anggaran"
        row-label="tahun anggaran"
        subtitle="Daftar periode anggaran sekolah"
        :show-actions="false"
        :show-avatar="false"
        :exportable="false"
        empty-message="Belum ada tahun anggaran."
        empty-hint="Pilih Tahun Anggaran untuk membuat periode anggaran pertama."
    />

    {{-- MODAL TAMBAH TAHUN ANGGARAN --}}
    <dialog x-ref="yearModal" class="account-dialog work" aria-labelledby="year-modal-title" aria-describedby="year-modal-help"
        @close="saving = false" @cancel="if (saving) $event.preventDefault()"
        @click="const bounds = $el.getBoundingClientRect(); if (!saving && ($event.clientX < bounds.left || $event.clientX > bounds.right || $event.clientY < bounds.top || $event.clientY > bounds.bottom)) $el.close()">
        <div class="account-dialog-head">
            <div class="min-w-0">
                <h2 id="year-modal-title" class="text-lg font-semibold text-gray-900 dark:text-white">Tambah Tahun Anggaran</h2>
                <p id="year-modal-help" class="work-muted text-xs mt-0.5">Tentukan nama dan rentang tanggal berlakunya tahun anggaran.</p>
            </div>
            <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors p-1 rounded-md" @click="$refs.yearModal.close()" :disabled="saving" aria-label="Tutup form">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <form method="POST" action="{{ route('finance.budget-years.store') }}" @submit="saving = true" :aria-busy="saving">
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

            <div class="work-field">
                <label for="year-name">Nama Tahun Anggaran <span class="text-red-500">*</span></label>
                <input id="year-name" name="name" value="{{ old('name') }}" type="text" maxlength="120" required placeholder="Contoh: Tahun Anggaran 2026 / 2027"
                    @if($firstError === 'name' || !$firstError) autofocus @endif>
                @error('name')<p class="work-muted work-error text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="work-field">
                    <label for="year-start-date">Tanggal Mulai <span class="text-red-500">*</span></label>
                    <input id="year-start-date" name="start_date" value="{{ old('start_date') }}" type="date" required>
                    @error('start_date')<p class="work-muted work-error text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="work-field">
                    <label for="year-end-date">Tanggal Selesai <span class="text-red-500">*</span></label>
                    <input id="year-end-date" name="end_date" value="{{ old('end_date') }}" type="date" required>
                    @error('end_date')<p class="work-muted work-error text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="account-dialog-actions mt-4">
                <button type="button" class="work-btn" @click="$refs.yearModal.close()" :disabled="saving">Batal</button>
                <button type="submit" class="work-btn work-btn-primary" :disabled="saving">
                    <span x-show="!saving">Simpan Tahun Anggaran</span>
                    <span x-show="saving" x-cloak>Menyimpan…</span>
                </button>
            </div>
        </form>
    </dialog>

    {{-- MODAL UBAH TAHUN ANGGARAN --}}
    <dialog x-ref="editYearModal" class="account-dialog work" aria-labelledby="edit-year-modal-title" aria-describedby="edit-year-modal-help"
        @close="saving = false" @cancel="if (saving) $event.preventDefault()"
        @click="const bounds = $el.getBoundingClientRect(); if (!saving && ($event.clientX < bounds.left || $event.clientX > bounds.right || $event.clientY < bounds.top || $event.clientY > bounds.bottom)) $el.close()">
        <div class="account-dialog-head">
            <div class="min-w-0">
                <h2 id="edit-year-modal-title" class="text-lg font-semibold text-gray-900 dark:text-white">Ubah Tahun Anggaran</h2>
                <p id="edit-year-modal-help" class="work-muted text-xs mt-0.5">Perbarui nama, periode waktu, atau status tahun anggaran.</p>
            </div>
            <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors p-1 rounded-md" @click="$refs.editYearModal.close()" :disabled="saving" aria-label="Tutup form">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <form method="POST" :action="editingYear ? editingYear.update_url : ''" @submit="saving = true" :aria-busy="saving">
            @csrf
            @method('PUT')

            <div class="work-field">
                <label for="edit-year-name">Nama Tahun Anggaran <span class="text-red-500">*</span></label>
                <input id="edit-year-name" name="name" x-model="editName" type="text" maxlength="120" required placeholder="Contoh: Tahun Anggaran 2026 / 2027">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="work-field">
                    <label for="edit-year-start-date">Tanggal Mulai <span class="text-red-500">*</span></label>
                    <input id="edit-year-start-date" name="start_date" x-model="editStartDate" type="date" required>
                </div>
                <div class="work-field">
                    <label for="edit-year-end-date">Tanggal Selesai <span class="text-red-500">*</span></label>
                    <input id="edit-year-end-date" name="end_date" x-model="editEndDate" type="date" required>
                </div>
            </div>

            <div class="work-field">
                <label for="edit-year-status">Status Periode <span class="text-red-500">*</span></label>
                <select id="edit-year-status" name="status" x-model="editStatus" required>
                    <option value="active">Aktif</option>
                    <option value="closed">Tutup / Selesai</option>
                </select>
            </div>

            <div class="account-dialog-actions mt-4">
                <button type="button" class="work-btn" @click="$refs.editYearModal.close()" :disabled="saving">Batal</button>
                <button type="submit" class="work-btn work-btn-primary" :disabled="saving">
                    <span x-show="!saving">Simpan Perubahan</span>
                    <span x-show="saving" x-cloak>Menyimpan…</span>
                </button>
            </div>
        </form>
    </dialog>
</div>
@endsection
