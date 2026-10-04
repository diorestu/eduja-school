@extends('layouts.app')

@section('content')
@php
    $accountColumns = [
        ['key' => 'name', 'label' => 'Nama rekening'],
        ['key' => 'type', 'label' => 'Jenis'],
        ['key' => 'bank_name', 'label' => 'Bank'],
        ['key' => 'account_number', 'label' => 'Nomor rekening'],
        ['key' => 'current_balance', 'label' => 'Nominal saat ini', 'type' => 'currency', 'minimumFractionDigits' => 2],
        ['key' => 'is_active', 'label' => 'Status', 'type' => 'boolean', 'trueLabel' => 'Aktif', 'falseLabel' => 'Nonaktif'],
        ['key' => 'aksi', 'label' => 'Aksi', 'sortable' => false, 'onlyEdit' => true],
    ];
    $accountRows = $accounts->map(fn ($account) => [
        'id' => $account->id,
        'name' => $account->name,
        'type' => $account->type,
        'bank_name' => $account->bank_name,
        'account_number' => $account->account_number,
        'current_balance' => $account->current_balance,
        'is_active' => (bool) $account->is_active,
        'update_url' => route('finance.accounts.update', $account),
        'only_edit' => true,
    ]);
    $firstError = array_key_first($errors->getMessages());
@endphp
@php
    $bankGroups = [
        'Bank BUMN / Pemerintah' => [
            'Bank Rakyat Indonesia (BRI)',
            'Bank Mandiri',
            'Bank Negara Indonesia (BNI)',
            'Bank Tabungan Negara (BTN)',
        ],
        'Bank Syariah' => [
            'Bank Syariah Indonesia (BSI)',
            'Bank Muamalat Indonesia',
            'BCA Syariah',
            'Bank Mega Syariah',
            'Bank BTPN Syariah',
        ],
        'Bank Swasta Nasional' => [
            'Bank Central Asia (BCA)',
            'Bank CIMB Niaga',
            'Bank Danamon',
            'Bank Permata',
            'Bank OCBC NISP',
            'Bank Panin',
            'Bank Maybank Indonesia',
            'Bank Mega',
            'Bank Sinarmas',
            'Bank BTPN',
            'Bank Bukopin',
        ],
        'Bank Digital' => [
            'Bank Jago',
            'SeaBank Indonesia',
            'Allo Bank',
            'Blu by BCA Digital',
            'Bank Neo Commerce (BNC)',
            'Jenius (BTPN)',
            'Line Bank (KEB Hana)',
        ],
        'Bank Pembangunan Daerah (BPD)' => [
            'Bank BJB',
            'Bank DKI',
            'Bank Jateng',
            'Bank Jatim',
            'Bank BPD Bali',
            'Bank Sumut',
            'Bank Nagari',
            'Bank Riau Kepri',
            'Bank Sumsel Babel',
            'Bank Lampung',
            'Bank Kalbar',
            'Bank Kalsel',
            'Bank Kalteng',
            'Bank Kaltimtara',
            'Bank Sulselbar',
            'Bank SulutGo',
            'Bank NTB Syariah',
            'Bank NTT',
            'Bank Maluku Malut',
            'Bank Papua',
        ],
        'Lainnya' => [
            'Bank Lainnya',
        ],
    ];
    $currentBank = old('bank_name');
    $allBanks = collect($bankGroups)->flatten()->all();
    $hasCustomBank = !empty($currentBank) && !in_array($currentBank, $allBanks);
@endphp
<x-common.page-breadcrumb pageTitle="Rekening Sekolah" label="Master Data Keuangan" />
<div class="work work-stack"
    x-data="{
        type: @js(old('type', 'Bank')),
        saving: false,
        editingAccount: null,
        editType: 'Bank',
        openEdit(row) {
            this.editingAccount = row;
            this.editType = row.type || 'Bank';
            this.$nextTick(() => {
                this.$refs.editAccountModal.showModal();
            });
        }
    }"
    @table-edit="openEdit($event.detail)"
    x-init="@if($errors->any() && !old('_method')) $nextTick(() => $refs.accountModal.showModal()) @endif">
    @if(session('success'))
        <div role="status" class="work-notice">{{ session('success') }}</div>
    @endif
    <div class="work-head mb-0">
        <p class="work-muted">Rekening tunai dan bank untuk sekolah yang sedang aktif.</p>
        <button type="button" class="work-btn work-btn-primary" @click="$refs.accountModal.showModal()">
            <svg class="w-4 h-4 mr-1.5 -ml-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Rekening Sekolah
        </button>
    </div>
    <x-common.data-table :rows="$accountRows" :columns="$accountColumns" caption="Daftar rekening sekolah" search-label="Cari rekening" row-label="rekening"
        subtitle="Rekening pada sekolah aktif" :show-actions="false" :show-avatar="false" :exportable="false"
        empty-message="Belum ada rekening sekolah." empty-hint="Pilih Rekening Sekolah untuk mencatat rekening pertama." />

    <dialog x-ref="accountModal" class="account-dialog work" aria-labelledby="account-modal-title" aria-describedby="account-modal-help"
        @close="saving = false" @cancel="if (saving) $event.preventDefault()"
        @click="const bounds = $el.getBoundingClientRect(); if (!saving && ($event.clientX < bounds.left || $event.clientX > bounds.right || $event.clientY < bounds.top || $event.clientY > bounds.bottom)) $el.close()">
        <div class="account-dialog-head">
            <div class="min-w-0">
                <h2 id="account-modal-title">Tambah Rekening Sekolah</h2>
                <p id="account-modal-help" class="work-muted mt-1">Catat rekening dan nominal saldo awalnya.</p>
            </div>
            <button type="button" class="work-btn" @click="$refs.accountModal.close()" :disabled="saving" aria-label="Tutup form rekening">Tutup</button>
        </div>
        <form method="POST" action="{{ route('finance.accounts.store') }}" @submit="saving = true" :aria-busy="saving">
            @csrf
            @if($errors->any())
                <p role="alert" class="work-notice work-error">Rekening belum disimpan. Periksa kolom yang ditandai di bawah.</p>
            @endif

            {{-- Nama Rekening --}}
            <div class="work-field">
                <label for="account-name">Nama Rekening <span aria-hidden="true">*</span></label>
                <input id="account-name" name="name" value="{{ old('name') }}" type="text" maxlength="120" required placeholder="Contoh: Operasional Sekolah, Kas SPP"
                    @if(($firstError && $firstError === 'name') || !$firstError) autofocus @endif
                    @if($errors->has('name')) aria-invalid="true" aria-describedby="error-name" @endif>
                @error('name')<p id="error-name" class="work-muted work-error">{{ $message }}</p>@enderror
            </div>

            {{-- Jenis Rekening --}}
            <div class="work-field">
                <label for="account-type">Jenis Rekening <span aria-hidden="true">*</span></label>
                <select id="account-type" name="type" x-model="type" required @if($firstError === 'type') autofocus @endif @if($errors->has('type')) aria-invalid="true" aria-describedby="error-type" @endif>
                    <option value="Bank" @selected(old('type', 'Bank') === 'Bank')>Bank</option>
                    <option value="Tunai" @selected(old('type') === 'Tunai')>Tunai</option>
                </select>
                @error('type')<p id="error-type" class="work-muted work-error">{{ $message }}</p>@enderror
            </div>

            {{-- Nama Bank (Dropdown Bank di Indonesia) --}}
            <div class="work-field" x-show="type === 'Bank'" x-cloak>
                <label for="account-bank_name">Nama Bank <span aria-hidden="true">*</span></label>
                <select id="account-bank_name" name="bank_name" :required="type === 'Bank'" :disabled="type !== 'Bank'"
                    @if($firstError === 'bank_name') autofocus @endif
                    @if($errors->has('bank_name')) aria-invalid="true" aria-describedby="error-bank_name" @endif>
                    <option value="" disabled @selected(!old('bank_name'))>-- Pilih Nama Bank --</option>
                    @if($hasCustomBank)
                        <option value="{{ $currentBank }}" selected>{{ $currentBank }}</option>
                    @endif
                    @foreach($bankGroups as $groupLabel => $banks)
                        <optgroup label="{{ $groupLabel }}">
                            @foreach($banks as $bank)
                                <option value="{{ $bank }}" @selected(old('bank_name') === $bank)>{{ $bank }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                @error('bank_name')<p id="error-bank_name" class="work-muted work-error">{{ $message }}</p>@enderror
            </div>

            {{-- Nomor Rekening --}}
            <div class="work-field" x-show="type === 'Bank'" x-cloak>
                <label for="account-account_number">Nomor Rekening <span aria-hidden="true">*</span></label>
                <input id="account-account_number" name="account_number" value="{{ old('account_number') }}" type="text" maxlength="100" inputmode="numeric" placeholder="Contoh: 1234567890"
                    :required="type === 'Bank'" :disabled="type !== 'Bank'"
                    @if($firstError === 'account_number') autofocus @endif
                    @if($errors->has('account_number')) aria-invalid="true" aria-describedby="error-account_number" @endif>
                @error('account_number')<p id="error-account_number" class="work-muted work-error">{{ $message }}</p>@enderror
            </div>

            {{-- Nominal Saldo Saat Ini --}}
            <div class="work-field">
                <label for="account-opening_balance">Nominal saat ini (Saldo awal) <span aria-hidden="true">*</span></label>
                <input id="account-opening_balance" name="opening_balance" value="{{ old('opening_balance', '0') }}" type="number" min="0" max="9999999999999.99" step="0.01" inputmode="decimal" required
                    aria-describedby="balance-help{{ $errors->has('opening_balance') ? ' error-opening_balance' : '' }}"
                    @if($firstError === 'opening_balance') autofocus @endif
                    @if($errors->has('opening_balance')) aria-invalid="true" @endif>
                <p id="balance-help" class="work-muted">Dalam rupiah. Nominal ini menjadi saldo awal rekening.</p>
                @error('opening_balance')<p id="error-opening_balance" class="work-muted work-error">{{ $message }}</p>@enderror
            </div>

            <div class="work-actions mt-2 justify-end">
                <button type="button" class="work-btn" @click="$refs.accountModal.close()" :disabled="saving">Batal</button>
                <button type="submit" :disabled="saving" class="work-btn work-btn-primary" x-text="saving ? 'Menyimpan…' : 'Simpan rekening'">Simpan rekening</button>
            </div>
        </form>
    </dialog>

    {{-- MODAL UBAH REKENING SEKOLAH --}}
    <dialog x-ref="editAccountModal" class="account-dialog work" aria-labelledby="edit-account-modal-title"
        @close="saving = false" @cancel="if (saving) $event.preventDefault()"
        @click="const bounds = $el.getBoundingClientRect(); if (!saving && ($event.clientX < bounds.left || $event.clientX > bounds.right || $event.clientY < bounds.top || $event.clientY > bounds.bottom)) $el.close()">
        <div class="account-dialog-head">
            <div class="min-w-0">
                <h2 id="edit-account-modal-title">Ubah Rekening Sekolah</h2>
                <p class="work-muted mt-1">Perbarui data rekening dan informasi bank.</p>
            </div>
            <button type="button" class="work-btn" @click="$refs.editAccountModal.close()" :disabled="saving" aria-label="Tutup form ubah rekening">Tutup</button>
        </div>
        <form method="POST" :action="editingAccount ? editingAccount.update_url : ''" @submit="saving = true" :aria-busy="saving">
            @csrf
            @method('PUT')

            {{-- Nama Rekening --}}
            <div class="work-field">
                <label for="edit-account-name">Nama Rekening <span aria-hidden="true">*</span></label>
                <input id="edit-account-name" name="name" :value="editingAccount ? editingAccount.name : ''" type="text" maxlength="120" required placeholder="Contoh: Operasional Sekolah, Kas SPP">
            </div>

            {{-- Jenis Rekening --}}
            <div class="work-field">
                <label for="edit-account-type">Jenis Rekening <span aria-hidden="true">*</span></label>
                <select id="edit-account-type" name="type" x-model="editType" required>
                    <option value="Bank">Bank</option>
                    <option value="Tunai">Tunai</option>
                </select>
            </div>

            {{-- Nama Bank (Dropdown Bank di Indonesia) --}}
            <div class="work-field" x-show="editType === 'Bank'" x-cloak>
                <label for="edit-account-bank_name">Nama Bank <span aria-hidden="true">*</span></label>
                <select id="edit-account-bank_name" name="bank_name" :required="editType === 'Bank'" :disabled="editType !== 'Bank'">
                    <option value="" disabled>-- Pilih Nama Bank --</option>
                    @foreach($bankGroups as $groupLabel => $banks)
                        <optgroup label="{{ $groupLabel }}">
                            @foreach($banks as $bank)
                                <option value="{{ $bank }}" :selected="editingAccount && editingAccount.bank_name === '{{ $bank }}'">{{ $bank }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>

            {{-- Nomor Rekening --}}
            <div class="work-field" x-show="editType === 'Bank'" x-cloak>
                <label for="edit-account-account_number">Nomor Rekening <span aria-hidden="true">*</span></label>
                <input id="edit-account-account_number" name="account_number" :value="editingAccount ? editingAccount.account_number : ''" type="text" maxlength="100" inputmode="numeric" placeholder="Contoh: 1234567890"
                    :required="editType === 'Bank'" :disabled="editType !== 'Bank'">
            </div>

            {{-- Status Rekening --}}
            <div class="work-field">
                <label for="edit-account-is_active">Status Rekening <span aria-hidden="true">*</span></label>
                <select id="edit-account-is_active" name="is_active" required>
                    <option value="1" :selected="editingAccount && editingAccount.is_active">Aktif</option>
                    <option value="0" :selected="editingAccount && !editingAccount.is_active">Nonaktif</option>
                </select>
            </div>

            <div class="work-actions mt-2 justify-end">
                <button type="button" class="work-btn" @click="$refs.editAccountModal.close()" :disabled="saving">Batal</button>
                <button type="submit" :disabled="saving" class="work-btn work-btn-primary" x-text="saving ? 'Menyimpan…' : 'Simpan Perubahan'">Simpan Perubahan</button>
            </div>
        </form>
    </dialog>
</div>
@endsection
