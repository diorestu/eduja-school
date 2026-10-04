@extends('layouts.app')

@section('content')
@php
    $billingColumns = [
        ['key' => 'name', 'label' => 'Nama Tagihan', 'bold' => true],
        ['key' => 'income_type_name', 'label' => 'Jenis Pemasukan', 'type' => 'badge'],
        ['key' => 'amount', 'label' => 'Nominal Tagihan', 'type' => 'currency', 'minimumFractionDigits' => 0],
        ['key' => 'billing_frequency', 'label' => 'Frekuensi'],
        ['key' => 'due_date', 'label' => 'Jatuh Tempo'],
        ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
        ['key' => 'aksi', 'label' => 'Aksi', 'sortable' => false, 'onlyEdit' => true],
    ];

    $billingRows = $billingItems->map(function ($item) {
        $statusLabels = [
            'active' => 'Aktif',
            'draft' => 'Draft',
            'closed' => 'Selesai',
        ];

        return [
            'id' => $item->id,
            'name' => $item->name,
            'income_type_id' => $item->income_type_id,
            'income_type_name' => $item->incomeType?->name ?? 'Komite',
            'amount' => (float) $item->amount,
            'billing_frequency' => $item->billing_frequency ?? 'Bulanan',
            'due_date' => $item->due_date ? \Carbon\Carbon::parse($item->due_date)->translatedFormat('d M Y') : 'Setiap Bulan',
            'due_date_raw' => $item->due_date ? \Carbon\Carbon::parse($item->due_date)->format('Y-m-d') : '',
            'target_type' => $item->target_type ?? 'school',
            'status' => $statusLabels[$item->status] ?? ucfirst($item->status),
            'status_raw' => $item->status ?? 'active',
            'update_url' => route('finance.billing.update', $item),
            'only_edit' => true,
        ];
    });

    $firstError = array_key_first($errors->getMessages());
@endphp

<x-common.page-breadcrumb pageTitle="Tagihan Siswa & Komite" label="Tagihan" />

<div class="work work-stack"
    x-data="{
        saving: false,
        editingItem: null,
        editName: '',
        editIncomeTypeId: '',
        editAmount: '',
        editFrequency: 'Bulanan',
        editDueDate: '',
        editStatus: 'active',
        openEdit(row) {
            this.editingItem = row;
            this.editName = row.name || '';
            this.editIncomeTypeId = row.income_type_id || '';
            this.editAmount = row.amount || '';
            this.editFrequency = row.billing_frequency || 'Bulanan';
            this.editDueDate = row.due_date_raw || '';
            this.editStatus = row.status_raw || 'active';
            this.$nextTick(() => {
                this.$refs.editBillingModal.showModal();
            });
        }
    }"
    @table-edit="openEdit($event.detail)"
    x-init="
        @if($errors->any() && !old('_method')) $nextTick(() => $refs.billingModal.showModal()); @endif
    ">

    @if(session('success'))
        <div role="status" class="work-notice">{{ session('success') }}</div>
    @endif

    {{-- METRICS COUNTER --}}
    @if(count($metrics))
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-3 mb-2">
            @foreach($metrics as $metric)
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-xs dark:border-gray-800 dark:bg-gray-900">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $metric['label'] }}</p>
                    <p class="mt-1 text-xl font-bold tracking-tight tabular-nums text-gray-900 dark:text-white">{{ $metric['value'] }}</p>
                </div>
            @endforeach
        </div>
    @endif

    <div class="work-head mb-0">
        <p class="work-muted">Daftar komponen tagihan komite, SPP, dan biaya sekolah lainnya yang dibebankan kepada siswa.</p>
        <button type="button" class="work-btn work-btn-primary" @click="$refs.billingModal.showModal()">
            <svg class="w-4 h-4 mr-1.5 -ml-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Item Tagihan
        </button>
    </div>

    <x-common.data-table
        :rows="$billingRows"
        :columns="$billingColumns"
        caption="Daftar Komponen Tagihan"
        search-label="Cari nama tagihan..."
        row-label="item tagihan"
        subtitle="Item tagihan yang berlaku di sekolah aktif"
        :show-actions="false"
        :show-avatar="false"
        :exportable="false"
        empty-message="Belum ada item tagihan."
        empty-hint="Pilih Item Tagihan untuk membuat skema tagihan pertama."
    />

    {{-- MODAL TAMBAH ITEM TAGIHAN --}}
    <dialog x-ref="billingModal" class="account-dialog work" aria-labelledby="billing-modal-title" aria-describedby="billing-modal-help"
        @close="saving = false" @cancel="if (saving) $event.preventDefault()"
        @click="const bounds = $el.getBoundingClientRect(); if (!saving && ($event.clientX < bounds.left || $event.clientX > bounds.right || $event.clientY < bounds.top || $event.clientY > bounds.bottom)) $el.close()">
        <div class="account-dialog-head">
            <div class="min-w-0">
                <h2 id="billing-modal-title" class="text-lg font-semibold text-gray-900 dark:text-white">Tambah Item Tagihan</h2>
                <p id="billing-modal-help" class="work-muted text-xs mt-0.5">Tentukan nama, nominal, dan frekuensi tagihan siswa.</p>
            </div>
            <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors p-1 rounded-md" @click="$refs.billingModal.close()" :disabled="saving" aria-label="Tutup form">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <form method="POST" action="{{ route('finance.billing.store') }}" @submit="saving = true" :aria-busy="saving">
            @csrf

            <div class="work-field">
                <label for="bill-name">Nama Tagihan <span class="text-red-500">*</span></label>
                <input id="bill-name" name="name" value="{{ old('name') }}" type="text" maxlength="160" required placeholder="Contoh: SPP Bulanan Kelas X">
                @error('name')<p class="work-muted work-error text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="work-field">
                    <label for="bill-income-type">Jenis Pemasukan</label>
                    <select id="bill-income-type" name="income_type_id">
                        <option value="">-- Hubungkan Jenis Pemasukan --</option>
                        @foreach($incomeTypes as $it)
                            <option value="{{ $it->id }}" @selected(old('income_type_id') == $it->id)>{{ $it->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="work-field">
                    <label for="bill-amount">Nominal Tagihan (Rp) <span class="text-red-500">*</span></label>
                    <input id="bill-amount" name="amount" value="{{ old('amount') }}" type="number" min="0" step="1000" inputmode="decimal" required placeholder="Contoh: 350000">
                    @error('amount')<p class="work-muted work-error text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="work-field">
                    <label for="bill-frequency">Frekuensi Penagihan</label>
                    <select id="bill-frequency" name="billing_frequency">
                        <option value="Bulanan" @selected(old('billing_frequency', 'Bulanan') === 'Bulanan')>Bulanan</option>
                        <option value="Semesteran" @selected(old('billing_frequency') === 'Semesteran')>Semesteran</option>
                        <option value="Tahunan" @selected(old('billing_frequency') === 'Tahunan')>Tahunan</option>
                        <option value="Sekali Bayar" @selected(old('billing_frequency') === 'Sekali Bayar')>Sekali Bayar</option>
                    </select>
                </div>

                <div class="work-field">
                    <label for="bill-due-date">Tanggal Jatuh Tempo</label>
                    <input id="bill-due-date" name="due_date" value="{{ old('due_date') }}" type="date">
                </div>
            </div>

            <div class="account-dialog-actions mt-4">
                <button type="button" class="work-btn" @click="$refs.billingModal.close()" :disabled="saving">Batal</button>
                <button type="submit" class="work-btn work-btn-primary" :disabled="saving">
                    <span x-show="!saving">Simpan Item Tagihan</span>
                    <span x-show="saving" x-cloak>Menyimpan…</span>
                </button>
            </div>
        </form>
    </dialog>

    {{-- MODAL UBAH ITEM TAGIHAN --}}
    <dialog x-ref="editBillingModal" class="account-dialog work" aria-labelledby="edit-billing-modal-title" aria-describedby="edit-billing-modal-help"
        @close="saving = false" @cancel="if (saving) $event.preventDefault()"
        @click="const bounds = $el.getBoundingClientRect(); if (!saving && ($event.clientX < bounds.left || $event.clientX > bounds.right || $event.clientY < bounds.top || $event.clientY > bounds.bottom)) $el.close()">
        <div class="account-dialog-head">
            <div class="min-w-0">
                <h2 id="edit-billing-modal-title" class="text-lg font-semibold text-gray-900 dark:text-white">Ubah Item Tagihan</h2>
                <p id="edit-billing-modal-help" class="work-muted text-xs mt-0.5">Perbarui informasi item tagihan.</p>
            </div>
            <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors p-1 rounded-md" @click="$refs.editBillingModal.close()" :disabled="saving" aria-label="Tutup form">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <form method="POST" :action="editingItem ? editingItem.update_url : ''" @submit="saving = true" :aria-busy="saving">
            @csrf
            @method('PUT')

            <div class="work-field">
                <label for="edit-bill-name">Nama Tagihan <span class="text-red-500">*</span></label>
                <input id="edit-bill-name" name="name" x-model="editName" type="text" maxlength="160" required>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="work-field">
                    <label for="edit-bill-income-type">Jenis Pemasukan</label>
                    <select id="edit-bill-income-type" name="income_type_id" x-model="editIncomeTypeId">
                        <option value="">-- Hubungkan Jenis Pemasukan --</option>
                        @foreach($incomeTypes as $it)
                            <option value="{{ $it->id }}">{{ $it->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="work-field">
                    <label for="edit-bill-amount">Nominal Tagihan (Rp) <span class="text-red-500">*</span></label>
                    <input id="edit-bill-amount" name="amount" x-model="editAmount" type="number" min="0" step="1000" inputmode="decimal" required>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="work-field">
                    <label for="edit-bill-frequency">Frekuensi</label>
                    <select id="edit-bill-frequency" name="billing_frequency" x-model="editFrequency">
                        <option value="Bulanan">Bulanan</option>
                        <option value="Semesteran">Semesteran</option>
                        <option value="Tahunan">Tahunan</option>
                        <option value="Sekali Bayar">Sekali Bayar</option>
                    </select>
                </div>

                <div class="work-field">
                    <label for="edit-bill-status">Status Tagihan <span class="text-red-500">*</span></label>
                    <select id="edit-bill-status" name="status" x-model="editStatus" required>
                        <option value="active">Aktif</option>
                        <option value="draft">Draft</option>
                        <option value="closed">Selesai</option>
                    </select>
                </div>
            </div>

            <div class="account-dialog-actions mt-4">
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
