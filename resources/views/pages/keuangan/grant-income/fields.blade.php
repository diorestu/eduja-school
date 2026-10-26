@php
    $record = $income ?? null;
    $amountValue = old('amount', $record?->amount ?? '');
    $initialMethod = old('payment_method', $record?->payment_method ?? 'Tunai');
    $initialAccount = old('account_id', $record?->account_id ?? '');
@endphp
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <div class="work-field"><label for="grant-donor">Nama Donatur <span aria-hidden="true">*</span></label><input id="grant-donor" name="donor_name" value="{{ old('donor_name', $record?->donor_name) }}" maxlength="160" required>@error('donor_name')<p class="work-error">{{ $message }}</p>@enderror</div>
    <div class="work-field" x-data="{
        value: @js($amountValue),
        normalize() { const text=String(this.value ?? '').trim(); if(!text) return; const comma=/^(?:\d{1,3}(\.\d{3})*|\d+),\d{1,2}$/.test(text); const thousands=/^\d{1,3}(\.\d{3})+$/.test(text); const plain=/^(?:\d+(\.\d{1,2})?|\.\d{1,2})$/.test(text); if(!comma && !thousands && !plain) return; const n=Number(comma ? text.replace(/\./g,'').replace(',','.') : (thousands ? text.replace(/\./g,'') : text)); if(Number.isFinite(n)) this.value=n.toLocaleString('id-ID',{minimumFractionDigits:2,maximumFractionDigits:2}); },
        init() { this.normalize(); }
    }"><label for="grant-amount">Nominal <span aria-hidden="true">*</span></label><input id="grant-amount" name="amount" inputmode="decimal" x-model="value" @blur="normalize()" placeholder="Contoh: 5.000.000,00" required>@error('amount')<p class="work-error">{{ $message }}</p>@enderror</div>
    <div class="work-field"><label for="grant-date">Tanggal terima <span aria-hidden="true">*</span></label><input id="grant-date" name="received_date" type="date" value="{{ old('received_date', $record?->received_date?->toDateString() ?? now()->toDateString()) }}" required>@error('received_date')<p class="work-error">{{ $message }}</p>@enderror</div>
</div>
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2" x-data="{method: @js($initialMethod), account: @js((string) $initialAccount)}">
    <div class="work-field"><label for="grant-method">Metode Hibah <span aria-hidden="true">*</span></label><select id="grant-method" name="payment_method" x-model="method" @change="account=''" required><option value="Tunai">Tunai</option><option value="Transfer">Transfer</option></select>@error('payment_method')<p class="work-error">{{ $message }}</p>@enderror</div>
    <template x-if="method === 'Transfer'"><div class="work-field"><label for="grant-bank">Rekening Bank Tujuan <span aria-hidden="true">*</span></label><select id="grant-bank" name="account_id" x-model="account" required><option value="">Pilih rekening bank sekolah</option>@foreach($bankAccounts as $account)<option value="{{ $account->id }}">{{ $account->name }}</option>@endforeach</select></div></template>
    <template x-if="method === 'Tunai'"><div class="work-field">
        @if($cashAccounts->count() === 1)
            <input type="hidden" name="account_id" value="{{ $cashAccounts->first()->id }}"><p class="work-muted">Kas tujuan: {{ $cashAccounts->first()->name }}</p>
        @elseif($cashAccounts->isNotEmpty())
            <label for="grant-cash">Kas Tujuan <span aria-hidden="true">*</span></label><select id="grant-cash" name="account_id" x-model="account" required><option value="">Pilih kas sekolah</option>@foreach($cashAccounts as $account)<option value="{{ $account->id }}">{{ $account->name }}</option>@endforeach</select>
        @else
            <p role="status" class="work-muted">Belum ada kas sekolah aktif. Lengkapi Rekening Sekolah sebelum menyimpan.</p>
        @endif
    </div></template>
</div>
@error('account_id')<p class="work-error" role="alert">{{ $message }}</p>@enderror
@if($incomeTypes->count() > 1)
    <div class="work-field"><label for="grant-type">Jenis Pemasukan Hibah <span aria-hidden="true">*</span></label><select id="grant-type" name="income_type_id" required><option value="">Pilih jenis pemasukan Hibah</option>@foreach($incomeTypes as $type)<option value="{{ $type->id }}" @selected(old('income_type_id', $record?->income_type_id) == $type->id)>{{ $type->name }}</option>@endforeach</select><p class="work-muted">Pilihan ini menentukan alokasi dana ke dompet virtual.</p></div>
@elseif($incomeTypes->count() === 1)
    <input type="hidden" name="income_type_id" value="{{ $incomeTypes->first()->id }}"><p class="work-muted">Jenis pemasukan: {{ $incomeTypes->first()->name }}. Alokasi dompet mengikuti konfigurasi jenis ini.</p>
@endif
@error('income_type_id')<p class="work-error" role="alert">{{ $message }}</p>@enderror
<div class="work-field"><label for="grant-proof">Upload Bukti (opsional)</label><input id="grant-proof" name="proof" type="file" accept=".pdf,.jpg,.jpeg,.png,.heic"><p class="work-muted">PDF, JPG, PNG, atau HEIC; maksimal 10 MB. Lampiran disimpan privat.@if($record?->proof_path) Bukti lama tetap dipakai jika tidak diganti.@endif</p>@error('proof')<p class="work-error">{{ $message }}</p>@enderror</div>
