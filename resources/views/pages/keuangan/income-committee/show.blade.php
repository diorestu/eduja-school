@extends('layouts.app')
@section('content')
@php
    $student = $row['student'];
    $payable = $row['invoices']->filter(fn ($invoice) => $invoice->remaining_amount > 0 && $invoice->status !== 'Lunas');
    $invoiceData = $payable->map(fn ($invoice) => ['id' => (string) $invoice->id, 'name' => $invoice->invoiceItems->where('is_late_fee', false)->first()?->name ?? $invoice->invoice_number, 'remaining' => (float) $invoice->remaining_amount])->values();
    $oldPayments = collect(old('payments', []))->filter(fn ($line) => is_array($line) && isset($line['invoice_id']));
    $oldAmounts = (object) $oldPayments->mapWithKeys(fn ($line) => [(string) $line['invoice_id'] => $line['amount_paid'] ?? ''])->all();
    $formatMoney = fn ($value) => number_format((float) $value, fmod((float) $value, 1.0) === 0.0 ? 0 : 2, ',', '.');
@endphp
<x-common.page-breadcrumb pageTitle="Pemasukan Komite" label="Pemasukan" />
<div class="work work-stack grid-cols-1" x-data="{
    invoices: @js($invoiceData), selected: @js($oldPayments->pluck('invoice_id')->map(fn ($id) => (string) $id)->values()->all()), amounts: @js($oldAmounts), method: @js(old('payment_method', 'Tunai')), saving: false,
    moneyNumber(value) { if(typeof value === 'number') return value; const text = String(value ?? '').trim(); if(text.includes(',')) { if(!/^(?:\d{1,3}(\.\d{3})*|\d+),\d{1,2}$/.test(text)) return NaN; return Number(text.replace(/\./g, '').replace(',', '.')); } if(/^\d{1,3}(\.\d{3})+$/.test(text)) return Number(text.replace(/\./g, '')); return /^(?:\d+(\.\d{1,2})?|\.\d{1,2})$/.test(text) ? Number(text) : NaN; },
    moneyInput(value) { if(value === '') return ''; const n = this.moneyNumber(value); return Number.isFinite(n) ? n.toLocaleString('id-ID', {minimumFractionDigits:2, maximumFractionDigits:2}) : String(value); },
    init() { this.selected = this.selected.filter(id => this.invoices.some(i => i.id === id)); this.invoices.forEach(i => { this.amounts[i.id] = this.moneyInput(this.amounts[i.id] ?? i.remaining); }); },
    total() { return this.selected.reduce((sum, id) => {const value = this.moneyNumber(this.amounts[id]); return sum + (Number.isFinite(value) ? Math.round(value * 100) : 0);}, 0) / 100; }
}">
    <a class="work-btn w-fit" href="{{ route('spp.transaksi.index', ['category' => 'komite']) }}">Kembali ke Pemasukan Komite</a>
    @if(session('success'))<div class="work-notice" role="status"><p>{{ session('success') }}</p><p>Nomor Transaksi: {{ session('committee_receipt_number') }} · Total Rp {{ $formatMoney(session('committee_receipt_amount')) }}</p></div>@endif
    <div class="flex flex-wrap items-center gap-3">@include('pages.keuangan.arrears.photo', ['student' => $student])<div><h2 class="text-lg font-semibold">{{ $student->name }}</h2><p class="work-muted">{{ $row['class_name'] }} · NIS {{ $student->nis ?? '-' }} · NISN {{ $student->nisn ?? '-' }}</p></div><strong class="sm:ml-auto tabular-nums">Tunggakan Rp {{ $formatMoney($row['total_arrears']) }}</strong></div>
    <form method="POST" action="{{ route('finance.committee-income.store', $student) }}" @submit="saving = true" :aria-busy="saving" class="work-stack grid-cols-1">
        @csrf
        <input type="hidden" name="submission_key" value="{{ old('submission_key', (string) \Illuminate\Support\Str::uuid()) }}">
        @if($errors->any())<div role="alert" class="work-notice work-error">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
        <h3 class="font-semibold">{{ $payable->isNotEmpty() ? 'Pilih tagihan' : 'Tagihan Komite' }}</h3>@if($payable->isNotEmpty())<p class="work-muted">Pilih satu atau beberapa tagihan. Nominal bayar dapat disesuaikan jika tagihan mengizinkan cicilan.</p>@endif
        <section class="min-w-0 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700"><div class="overflow-x-auto"><table class="w-full text-left text-sm">
            <thead class="bg-gray-50 dark:bg-gray-800"><tr><th class="p-3">Pilih</th><th class="p-3">Tagihan</th><th class="p-3">Deadline</th><th class="p-3">Sisa</th><th class="p-3">Status</th><th class="p-3">Nominal bayar</th></tr></thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">@forelse($row['invoices'] as $invoice)
                <tr><td class="p-3">@if($invoice->remaining_amount > 0 && $invoice->status !== 'Lunas')<input id="committee-select-{{ $invoice->id }}" type="checkbox" value="{{ $invoice->id }}" x-model="selected" class="h-4 w-4"><label for="committee-select-{{ $invoice->id }}" class="sr-only">Pilih {{ $invoice->invoice_number }}</label>@endif</td>
                    <td class="p-3">@if($invoice->remaining_amount > 0 && $invoice->status !== 'Lunas')<label for="committee-select-{{ $invoice->id }}" class="font-semibold">{{ $invoice->invoiceItems->where('is_late_fee', false)->first()?->name ?? 'Tagihan Komite' }}</label>@else<strong>{{ $invoice->invoiceItems->where('is_late_fee', false)->first()?->name ?? 'Tagihan Komite' }}</strong>@endif<span class="block text-xs text-gray-500 dark:text-gray-400">{{ $invoice->invoice_number }}</span></td>
                    <td class="p-3 whitespace-nowrap">{{ $invoice->due_date->locale('id')->translatedFormat('d M Y') }}</td><td class="p-3 whitespace-nowrap tabular-nums">Rp {{ $formatMoney($invoice->remaining_amount) }}<span class="block text-xs">Denda Rp {{ $formatMoney($invoice->late_fee_amount) }}</span></td><td class="p-3 whitespace-nowrap">{{ $arrears->status($invoice) }}</td>
                    <td class="p-3">@if($invoice->remaining_amount > 0 && $invoice->status !== 'Lunas')<label class="sr-only" for="committee-amount-{{ $invoice->id }}">Nominal {{ $invoice->invoice_number }}</label><input id="committee-amount-{{ $invoice->id }}" :name="selected.includes(@js((string) $invoice->id)) ? 'payments[{{ $invoice->id }}][amount_paid]' : null" x-model="amounts[@js((string) $invoice->id)]" @blur="amounts[@js((string) $invoice->id)] = moneyInput(amounts[@js((string) $invoice->id)])" :disabled="!selected.includes(@js((string) $invoice->id))" :required="selected.includes(@js((string) $invoice->id))" inputmode="decimal" class="w-36 rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-700 dark:bg-gray-800"><input type="hidden" :name="selected.includes(@js((string) $invoice->id)) ? 'payments[{{ $invoice->id }}][invoice_id]' : null" value="{{ $invoice->id }}">@else<span class="text-gray-500">Lunas</span>@endif</td>
                </tr>
            @empty<tr><td colspan="6" class="p-8 text-center text-gray-500">Belum ada tagihan Komite untuk siswa ini.</td></tr>@endforelse</tbody></table></div></section>
        @if($payable->isNotEmpty())
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="work-field"><label for="committee-method">Metode pembayaran</label><select id="committee-method" name="payment_method" x-model="method"><option value="Tunai">Tunai</option><option value="Transfer">Transfer</option></select></div>
            <div class="work-field"><label for="committee-date">Tanggal transaksi</label><input id="committee-date" name="payment_date" type="date" value="{{ old('payment_date', now()->toDateString()) }}" required></div>
        </div>
        <div class="work-field" x-show="method === 'Transfer'" x-cloak><label for="committee-bank">Rekening Transfer Komite</label><select id="committee-bank" name="account_id" :disabled="method !== 'Transfer'" :required="method === 'Transfer'"><option value="">Pilih rekening Komite</option>@foreach($bankAccounts as $bank)<option value="{{ $bank->id }}" @selected(old('account_id') == $bank->id)>{{ $bank->name }} · {{ $bank->bank_name }} · {{ $bank->account_number }}</option>@endforeach</select>@if($bankAccounts->isEmpty())<p class="work-muted">Belum ada rekening bank aktif bertanda Komite. Lengkapi Kategori Dana pada Rekening Sekolah.</p>@endif</div>
        <div class="flex flex-wrap items-center justify-between gap-3"><strong x-text="'Total pembayaran Rp ' + total().toLocaleString('id-ID', {minimumFractionDigits: Number.isInteger(total()) ? 0 : 2, maximumFractionDigits: 2})"></strong><button type="submit" class="work-btn work-btn-primary" :disabled="saving || selected.length === 0" x-text="saving ? 'Menyimpan…' : 'Simpan'"></button></div>
        @elseif($row['invoices']->isNotEmpty())
            <p role="status" class="work-muted">Seluruh tagihan Komite siswa ini telah lunas.</p>
        @endif
    </form>
    <p class="work-muted">Pengiriman notifikasi orang tua masih ditunda sesuai pengaturan integrasi WhatsApp.</p>
</div>
@endsection
