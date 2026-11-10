@php
    $record = $income ?? null;
    $selectedSource = old('source_funding', $record?->source_funding ?? '');
    $selectedYear = old('bos_year', $record?->bos_year ?? now()->year);
    $amountValue = old('amount', $record?->amount ?? '');
@endphp
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <div class="work-field"><label for="bos-source">Jenis BOS <span aria-hidden="true">*</span></label><select id="bos-source" name="source_funding" required><option value="">Pilih sumber dana BOS</option>@foreach($sources as $source)<option value="{{ $source }}" @selected($selectedSource === $source)>{{ $source }}</option>@endforeach</select>@error('source_funding')<p class="work-error">{{ $message }}</p>@enderror</div>
    <div class="work-field"><label for="bos-year">Tahun BOS <span aria-hidden="true">*</span></label><select id="bos-year" name="bos_year" required><option value="">Pilih tahun</option>@foreach($years as $year)<option value="{{ $year }}" @selected((int) $selectedYear === (int) $year)>{{ $year }}</option>@endforeach</select>@error('bos_year')<p class="work-error">{{ $message }}</p>@enderror</div>
    <div class="work-field">
        <label for="bos-amount">Nominal <span aria-hidden="true">*</span></label>
        <input id="bos-amount" name="amount" type="text" inputmode="numeric" data-mask="currency" class="mask-currency" value="{{ old('amount', $record?->amount ? number_format((float) $record->amount, 0, ',', '.') : '') }}" placeholder="Contoh: 5.000.000" required>
        @error('amount')<p class="work-error">{{ $message }}</p>@enderror
    </div>
    <div class="work-field"><label for="bos-received-date">Tanggal terima <span aria-hidden="true">*</span></label><input id="bos-received-date" name="received_date" type="date" value="{{ old('received_date', $record?->received_date?->toDateString() ?? now()->toDateString()) }}" required>@error('received_date')<p class="work-error">{{ $message }}</p>@enderror</div>
</div>
<div class="work-field"><label for="bos-proof">Upload SP2D (opsional)</label><input id="bos-proof" name="proof" type="file" accept=".pdf,.jpg,.jpeg,.png,.heic"><p class="work-muted">PDF, JPG, PNG, atau HEIC; maksimal 10 MB. Lampiran baru disimpan privat.@if($record?->proof_path) Lampiran lama tetap dipakai jika tidak diganti.@endif</p>@error('proof')<p class="work-error">{{ $message }}</p>@enderror</div>
@if($record?->account_id)
    <p class="work-muted">Rekening asal: {{ $record->account?->name ?? '-' }}. Revisi menyesuaikan saldo rekening asal setelah persetujuan.</p>
@elseif($bosAccounts->count() === 1)
    <input type="hidden" name="account_id" value="{{ $bosAccounts->first()->id }}"><p class="work-muted">Rekening tujuan: {{ $bosAccounts->first()->name }}</p>
@elseif($bosAccounts->isNotEmpty())
    <div class="work-field"><label for="bos-account">Rekening tujuan BOS</label><select id="bos-account" name="account_id" required><option value="">Pilih rekening BOS</option>@foreach($bosAccounts as $account)<option value="{{ $account->id }}" @selected(old('account_id') == $account->id)>{{ $account->name }}</option>@endforeach</select><p class="work-muted">Pilih rekening karena sekolah memiliki lebih dari satu rekening BOS aktif.</p></div>
@else
    <p role="status" class="work-muted">Belum ada rekening BOS aktif. Lengkapi Kategori Dana BOS di Rekening Sekolah sebelum menyimpan.</p>
@endif
@error('account_id')<p class="work-error" role="alert">{{ $message }}</p>@enderror
