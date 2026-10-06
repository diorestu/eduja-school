@extends('layouts.app')

@section('content')
@php
    $walletColumns = [
        ['key' => 'name', 'label' => 'Nama Dompet', 'bold' => true],
        ['key' => 'nominal', 'label' => 'Nominal', 'type' => 'currency', 'minimumFractionDigits' => 0],
        ['key' => 'source_label', 'label' => 'Asal Alokasi Dana', 'bold' => true, 'subKey' => 'notes_short'],
        ['key' => 'status_label', 'label' => 'Status', 'type' => 'badge'],
        ['key' => 'aksi', 'label' => 'Aksi', 'sortable' => false, 'onlyEdit' => true],
    ];

    $walletRows = $wallets->map(function ($wallet) {
        $linkedIncomes = $wallet->fundAllocations->map(fn ($fa) => $fa->incomeType?->name)->filter()->unique()->values();
        $sourceText = $wallet->source;
        if (empty($sourceText) && $linkedIncomes->isNotEmpty()) {
            $sourceText = $linkedIncomes->implode(', ');
        }

        return [
            'id' => $wallet->id,
            'name' => $wallet->name,
            'nominal' => (float) $wallet->nominal,
            'source' => $wallet->source ?? '',
            'source_label' => filled($sourceText) ? $sourceText : 'Alokasi Bebas / Khusus',
            'status' => $wallet->status,
            'status_label' => in_array(strtolower($wallet->status), ['active', 'aktif']) ? 'Aktif' : 'Nonaktif',
            'notes' => $wallet->notes ?? '',
            'notes_short' => filled($wallet->notes) ? \Illuminate\Support\Str::limit($wallet->notes, 40) : null,
            'update_url' => route('finance.virtual-wallets.update', $wallet),
            'only_edit' => true,
        ];
    });

    $firstError = array_key_first($errors->getMessages());
@endphp

<x-common.page-breadcrumb pageTitle="Dompet Virtual" label="Master Data Keuangan" />

<div class="work work-stack"
    x-data="{
        saving: false,
        editingWallet: null,
        openEdit(row) {
            this.editingWallet = {
                id: row.id,
                name: row.name,
                nominal: window.formatCurrencyMask ? window.formatCurrencyMask(row.nominal) : row.nominal,
                source: row.source || '',
                status: (row.status === 'inactive' || row.status === 'Nonaktif') ? 'inactive' : 'active',
                notes: row.notes || ''
            };
            this.$nextTick(() => {
                this.$refs.editWalletModal.showModal();
            });
        }
    }"
    @table-edit="openEdit($event.detail)"
    x-init="@if($errors->any() && !old('_method')) $nextTick(() => $refs.walletModal.showModal()) @endif">

    @if(session('success'))
        <div role="status" class="work-notice">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs font-semibold text-rose-700 dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-300">
            {{ session('error') }}
        </div>
    @endif

    {{-- METRIC SUMMARY CARDS --}}
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4 mb-2">
        @foreach($metrics as $metric)
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-xs dark:border-gray-800 dark:bg-gray-900">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $metric['label'] }}</p>
                <p class="mt-1 text-xl font-bold tracking-tight tabular-nums text-gray-900 dark:text-white">{{ $metric['value'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- HEADER ACTION --}}
    <div class="work-head mb-0">
        <p class="work-muted">Kantong-kantong dompet virtual untuk alokasi dana dan keperluan khusus operasional sekolah.</p>
        <button type="button" class="work-btn work-btn-primary" @click="$refs.walletModal.showModal()">
            <svg class="w-4 h-4 mr-1.5 -ml-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Dompet Virtual Baru
        </button>
    </div>

    {{-- DATA TABLE --}}
    <x-common.data-table
        :rows="$walletRows"
        :columns="$walletColumns"
        caption="Daftar Dompet Virtual"
        search-label="Cari dompet atau asal alokasi..."
        row-label="dompet virtual"
        subtitle="Kantong alokasi dana khusus pada sekolah aktif"
        :show-actions="false"
        :show-avatar="false"
        :exportable="true"
        export-label="Export Dompet Virtual"
        empty-message="Belum ada dompet virtual yang dicatat."
        empty-hint="Pilih Dompet Virtual Baru untuk membuat kantong keperluan khusus pertama."
    />

    {{-- MODAL TAMBAH DOMPET VIRTUAL --}}
    <dialog x-ref="walletModal" class="account-dialog work" aria-labelledby="wallet-modal-title" aria-describedby="wallet-modal-help"
        @close="saving = false" @cancel="if (saving) $event.preventDefault()"
        @click="const bounds = $el.getBoundingClientRect(); if (!saving && ($event.clientX < bounds.left || $event.clientX > bounds.right || $event.clientY < bounds.top || $event.clientY > bounds.bottom)) $el.close()">
        <div class="account-dialog-head">
            <div class="min-w-0">
                <h2 id="wallet-modal-title">Tambah Dompet Virtual</h2>
                <p id="wallet-modal-help" class="work-muted mt-1">Siapkan kantong dompet untuk alokasi dana keperluan khusus.</p>
            </div>
            <button type="button" class="work-btn" @click="$refs.walletModal.close()" :disabled="saving" aria-label="Tutup form dompet virtual">Tutup</button>
        </div>
        <form method="POST" action="{{ route('finance.virtual-wallets.store') }}" @submit="saving = true" :aria-busy="saving">
            @csrf
            @if($errors->any())
                <p role="alert" class="work-notice work-error">Dompet virtual belum disimpan. Periksa isian form di bawah.</p>
            @endif

            {{-- Nama Dompet --}}
            <div class="work-field">
                <label for="wallet-name">Nama Dompet <span aria-hidden="true" class="text-red-500">*</span></label>
                <input id="wallet-name" name="name" value="{{ old('name') }}" type="text" maxlength="120" required placeholder="Contoh: Kantong Perawatan Gedung, Kantong Ekstrakurikuler"
                    @if(($firstError && $firstError === 'name') || !$firstError) autofocus @endif
                    @if($errors->has('name')) aria-invalid="true" aria-describedby="error-name" @endif>
                @error('name')<p id="error-name" class="work-muted work-error">{{ $message }}</p>@enderror
            </div>

            {{-- Nominal --}}
            <div class="work-field">
                <label for="wallet-nominal">Nominal / Saldo Awal <span aria-hidden="true" class="text-red-500">*</span></label>
                <div class="relative flex items-center">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-xs font-semibold text-gray-500 pointer-events-none select-none z-10">Rp</span>
                    <input id="wallet-nominal" name="nominal" value="{{ old('nominal') ? number_format((float)old('nominal'), 0, ',', '.') : '0' }}" type="text" inputmode="numeric" data-mask="currency" required placeholder="Contoh: 2.500.000" class="w-full !pl-11 pr-3 py-2 text-xs rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white tabular-nums mask-currency" style="padding-left: 2.75rem !important;"
                        @if($firstError === 'nominal') autofocus @endif
                        @if($errors->has('nominal')) aria-invalid="true" aria-describedby="error-nominal" @endif>
                </div>
                @error('nominal')<p id="error-nominal" class="work-muted work-error">{{ $message }}</p>@enderror
            </div>

            {{-- Asal Alokasi Dana --}}
            <div class="work-field">
                <label for="wallet-source">Asal Alokasi Dana</label>
                <input id="wallet-source" name="source" value="{{ old('source') }}" type="text" maxlength="120" list="source-options" placeholder="Contoh: SPP, BOS Reguler, Komite, Infaq"
                    @if($firstError === 'source') autofocus @endif
                    @if($errors->has('source')) aria-invalid="true" aria-describedby="error-source" @endif>
                <datalist id="source-options">
                    @foreach($incomeTypes as $income)
                        <option value="{{ $income->name }}"></option>
                    @endforeach
                    <option value="SPP Bulanan"></option>
                    <option value="Dana BOS Reguler"></option>
                    <option value="Uang Gedung / Komite"></option>
                    <option value="Infaq & Donasi"></option>
                </datalist>
                <p class="work-muted text-xs">Sumber pemasukan atau alokasi yang diarahkan ke kantong dompet ini.</p>
                @error('source')<p id="error-source" class="work-muted work-error">{{ $message }}</p>@enderror
            </div>

            {{-- Status --}}
            <div class="work-field">
                <label for="wallet-status">Status Dompet <span aria-hidden="true" class="text-red-500">*</span></label>
                <select id="wallet-status" name="status" required>
                    <option value="active" @selected(old('status', 'active') === 'active')>Aktif</option>
                    <option value="inactive" @selected(old('status') === 'inactive')>Nonaktif</option>
                </select>
                @error('status')<p id="error-status" class="work-muted work-error">{{ $message }}</p>@enderror
            </div>

            {{-- Catatan / Keterangan --}}
            <div class="work-field">
                <label for="wallet-notes">Keterangan / Catatan</label>
                <textarea id="wallet-notes" name="notes" rows="2" maxlength="500" placeholder="Catatan peruntukan atau tujuan penggunaan dompet">{{ old('notes') }}</textarea>
                @error('notes')<p id="error-notes" class="work-muted work-error">{{ $message }}</p>@enderror
            </div>

            <div class="work-actions mt-4 flex items-center justify-end gap-2">
                <button type="button" class="work-btn" @click="$refs.walletModal.close()" :disabled="saving">Batal</button>
                <button type="submit" class="work-btn work-btn-primary" :disabled="saving">
                    <span x-show="!saving">Simpan Dompet</span>
                    <span x-show="saving" x-cloak>Menyimpan...</span>
                </button>
            </div>
        </form>
    </dialog>

    {{-- MODAL EDIT DOMPET VIRTUAL --}}
    <dialog x-ref="editWalletModal" class="account-dialog work" aria-labelledby="edit-wallet-modal-title"
        @close="saving = false" @cancel="if (saving) $event.preventDefault()"
        @click="const bounds = $el.getBoundingClientRect(); if (!saving && ($event.clientX < bounds.left || $event.clientX > bounds.right || $event.clientY < bounds.top || $event.clientY > bounds.bottom)) $el.close()">
        <div class="account-dialog-head">
            <div class="min-w-0">
                <h2 id="edit-wallet-modal-title">Edit Dompet Virtual</h2>
                <p class="work-muted mt-1">Perbarui rincian kantong alokasi dana khusus.</p>
            </div>
            <button type="button" class="work-btn" @click="$refs.editWalletModal.close()" :disabled="saving" aria-label="Tutup form edit">Tutup</button>
        </div>
        <form method="POST" :action="'/finance/virtual-wallets/' + (editingWallet?.id || '')" @submit="saving = true" :aria-busy="saving">
            @csrf
            @method('PUT')

            {{-- Nama Dompet --}}
            <div class="work-field">
                <label for="edit-wallet-name">Nama Dompet <span aria-hidden="true" class="text-red-500">*</span></label>
                <input id="edit-wallet-name" name="name" x-model="editingWallet.name" type="text" maxlength="120" required placeholder="Nama dompet">
            </div>

            {{-- Nominal --}}
            <div class="work-field">
                <label for="edit-wallet-nominal">Nominal / Saldo <span aria-hidden="true" class="text-red-500">*</span></label>
                <div class="relative flex items-center">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-xs font-semibold text-gray-500 pointer-events-none select-none z-10">Rp</span>
                    <input id="edit-wallet-nominal" name="nominal" x-model="editingWallet.nominal" type="text" inputmode="numeric" data-mask="currency" required placeholder="0" class="w-full !pl-11 pr-3 py-2 text-xs rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white tabular-nums mask-currency" style="padding-left: 2.75rem !important;">
                </div>
            </div>

            {{-- Asal Alokasi Dana --}}
            <div class="work-field">
                <label for="edit-wallet-source">Asal Alokasi Dana</label>
                <input id="edit-wallet-source" name="source" x-model="editingWallet.source" type="text" maxlength="120" list="edit-source-options" placeholder="Contoh: SPP, BOS, Komite">
                <datalist id="edit-source-options">
                    @foreach($incomeTypes as $income)
                        <option value="{{ $income->name }}"></option>
                    @endforeach
                    <option value="SPP Bulanan"></option>
                    <option value="Dana BOS Reguler"></option>
                    <option value="Uang Gedung / Komite"></option>
                    <option value="Infaq & Donasi"></option>
                </datalist>
            </div>

            {{-- Status --}}
            <div class="work-field">
                <label for="edit-wallet-status">Status Dompet <span aria-hidden="true" class="text-red-500">*</span></label>
                <select id="edit-wallet-status" name="status" x-model="editingWallet.status" required>
                    <option value="active">Aktif</option>
                    <option value="inactive">Nonaktif</option>
                </select>
            </div>

            {{-- Catatan / Keterangan --}}
            <div class="work-field">
                <label for="edit-wallet-notes">Keterangan / Catatan</label>
                <textarea id="edit-wallet-notes" name="notes" x-model="editingWallet.notes" rows="2" maxlength="500"></textarea>
            </div>

            <div class="work-actions mt-4 flex items-center justify-end gap-2">
                <button type="button" class="work-btn" @click="$refs.editWalletModal.close()" :disabled="saving">Batal</button>
                <button type="submit" class="work-btn work-btn-primary" :disabled="saving">
                    <span x-show="!saving">Perbarui Dompet</span>
                    <span x-show="saving" x-cloak>Menyimpan...</span>
                </button>
            </div>
        </form>
    </dialog>
</div>
@endsection
