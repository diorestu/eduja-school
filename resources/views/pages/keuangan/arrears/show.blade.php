@extends('layouts.app')
@section('content')
@php
    $student = $row['student'];
    $payable = $row['invoices']->filter(fn ($invoice) => $invoice->remaining_amount > 0 && $invoice->status !== 'Lunas');
    $invoiceData = $payable->map(fn ($invoice) => ['id' => (string) $invoice->id, 'name' => $invoice->invoiceItems->where('is_late_fee', false)->first()?->name ?? $invoice->invoice_number, 'remaining' => (float) $invoice->remaining_amount])->values();
@endphp
<x-common.page-breadcrumb pageTitle="Tagihan Siswa" label="Tunggakan Siswa" />
<div class="work work-stack grid-cols-1" x-data="{
    invoices: @js($invoiceData), selectedInvoice: @js((string) old('invoice_id', $payable->first()?->id ?? '')),
    amount: @js(old('amount_paid', $payable->first()?->remaining_amount ?? '')),
    method: @js(old('payment_method', 'Tunai')), saving: false,
    init() { if (@js($errors->any() && old('invoice_id') !== null)) this.$nextTick(() => this.$refs.payment.showModal()); },
    choose(id) { this.selectedInvoice = String(id); const invoice = this.invoices.find(i => i.id === String(id)); this.amount = window.formatCurrencyMask ? window.formatCurrencyMask(invoice?.remaining || 0) : (invoice?.remaining || 0); this.$refs.payment.showModal(); }
}">
    <a class="work-btn w-fit" href="{{ route('finance.arrears') }}">Kembali ke Tunggakan Siswa</a>
    @if(session('success'))<p class="work-notice" role="status">{{ session('success') }}@if(session('trx_number')) · {{ session('trx_number') }}@endif</p>@endif
    <div class="flex flex-wrap items-center gap-3">
        @include('pages.keuangan.arrears.photo', ['student' => $student])
        <div><h2 class="text-lg font-semibold">{{ $student->name }}</h2><p class="work-muted">{{ $row['class_name'] }} · NIS {{ $student->nis ?? '-' }} · NISN {{ $student->nisn ?? '-' }}</p></div>
        <div class="sm:ml-auto"><span class="block text-sm">{{ $row['status'] }}</span><strong class="tabular-nums">Sisa Rp {{ number_format($row['total_arrears'], 0, ',', '.') }}</strong></div>
    </div>
    <section class="min-w-0 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700" aria-label="Tagihan siswa">
        <div class="overflow-x-auto"><table class="w-full text-left text-sm">
            <thead class="bg-gray-50 dark:bg-gray-800"><tr><th class="p-3">Tagihan</th><th class="p-3">Periode / Deadline</th><th class="p-3">Pokok / Denda</th><th class="p-3">Dibayar / Sisa</th><th class="p-3">Status</th><th class="p-3">Aksi</th></tr></thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            @forelse($row['invoices'] as $invoice)
                <tr><td class="p-3"><strong>{{ $invoice->invoiceItems->where('is_late_fee', false)->first()?->name ?? 'Tagihan' }}</strong><span class="block text-xs text-gray-500 dark:text-gray-400">{{ $invoice->invoice_number }}</span></td>
                    <td class="p-3 whitespace-nowrap">{{ ($invoice->billingPeriod?->start_date ?? $invoice->due_date)->locale('id')->translatedFormat('F Y') }}<span class="block text-xs">{{ $invoice->due_date->locale('id')->translatedFormat('d M Y') }}</span></td>
                    <td class="p-3 whitespace-nowrap tabular-nums">Rp {{ number_format($invoice->principal_amount ?? $invoice->total_amount, 0, ',', '.') }}<span class="block text-xs">Denda Rp {{ number_format($invoice->late_fee_amount, 0, ',', '.') }}</span></td>
                    <td class="p-3 whitespace-nowrap tabular-nums">Rp {{ number_format($invoice->paid_amount, 0, ',', '.') }}<span class="block font-semibold">Sisa Rp {{ number_format($invoice->remaining_amount, 0, ',', '.') }}</span></td>
                    <td class="p-3 whitespace-nowrap">{{ $arrears->status($invoice) }}</td><td class="p-3">@if($invoice->remaining_amount > 0 && $invoice->status !== 'Lunas')<button type="button" class="work-btn work-btn-primary" @click="choose(@js((string) $invoice->id))">Bayar</button>@else<span class="text-gray-500">Lunas</span>@endif</td>
                </tr>
            @empty<tr><td colspan="6" class="p-8 text-center text-gray-500">Belum ada tagihan untuk siswa ini.</td></tr>@endforelse
            </tbody></table></div>
    </section>
    @if($row['invoices']->flatMap->transactions->isNotEmpty())
        <section class="space-y-3" aria-label="Riwayat pembayaran">
            <h2 class="font-semibold">Riwayat pembayaran</h2>
            @foreach($row['invoices'] as $invoice)
                @foreach($invoice->transactions as $transaction)
                    <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-gray-200 p-3 text-sm dark:border-gray-700">
                        <div><strong>{{ $transaction->receipt_number }}</strong><p class="work-muted">{{ $invoice->invoice_number }} · {{ $transaction->payment_date->format('d M Y') }} · {{ $transaction->payment_method }} · {{ $transaction->status }}</p><p>Rp {{ number_format($transaction->amount_paid, 0, ',', '.') }}</p></div>
                        @if($transaction->proof_path)<a class="work-btn" href="{{ route('finance.arrears.proof', [$student, $transaction]) }}" target="_blank" rel="noopener">Bukti transfer<span class="sr-only"> {{ $transaction->receipt_number }} (tab baru)</span></a>@endif
                    </div>
                @endforeach
            @endforeach
        </section>
    @endif
    <p class="work-muted">Notifikasi pembayaran belum dikirim karena integrasi WhatsApp ditunda.</p>
    <dialog x-ref="payment" class="account-dialog work max-w-lg w-full" aria-labelledby="arrears-payment-title" @close="saving = false" @cancel="if(saving) $event.preventDefault()">
        <div class="account-dialog-head"><h2 id="arrears-payment-title">Pembayaran Tagihan</h2><button type="button" class="work-btn" @click="$refs.payment.close()" :disabled="saving">Tutup</button></div>
        <form method="POST" enctype="multipart/form-data" action="{{ route('finance.arrears.pay', $student) }}" @submit="saving = true" :aria-busy="saving" class="space-y-4 py-4">
            @csrf
            @if($errors->any())<div role="alert" class="work-notice work-error">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
            <div class="work-field"><label for="arrears-invoice">Tagihan</label><select id="arrears-invoice" name="invoice_id" x-model="selectedInvoice" @change="const i = invoices.find(i => i.id === selectedInvoice); amount = window.formatCurrencyMask ? window.formatCurrencyMask(i?.remaining || 0) : (i?.remaining || 0)" required><template x-for="invoice in invoices" :key="invoice.id"><option :value="invoice.id" x-text="invoice.name + ' · Sisa Rp ' + invoice.remaining.toLocaleString('id-ID')"></option></template></select></div>
            <div class="work-field"><label for="arrears-amount">Nominal bayar</label><input id="arrears-amount" name="amount_paid" x-model="amount" data-mask="currency" inputmode="numeric" required><p class="work-muted">Cicilan mengikuti aturan tagihan. Nilai awal adalah seluruh sisa pembayaran.</p></div>
            <div class="work-field"><label for="arrears-method">Metode pembayaran</label><select id="arrears-method" name="payment_method" x-model="method"><option value="Tunai">Tunai</option><option value="Transfer">Transfer</option><option value="Transfer Bank">Transfer Bank</option></select></div>
            <div class="work-field" x-show="method === 'Transfer' || method === 'Transfer Bank'" x-cloak><label for="arrears-bank">Rekening tujuan transfer</label><select id="arrears-bank" name="account_id" :required="method === 'Transfer' || method === 'Transfer Bank'"><option value="">Pilih rekening bank</option>@foreach($bankAccounts as $bank)<option value="{{ $bank->id }}" @selected(old('account_id') == $bank->id)>{{ $bank->name }} · {{ $bank->bank_name }} · {{ $bank->account_number }}</option>@endforeach</select>@if($bankAccounts->isEmpty())<p class="work-muted">Belum ada rekening bank aktif untuk pembayaran ini. Bendahara dapat menambahkan rekening umum atau Komite di Rekening Sekolah.</p>@endif</div>
            <div class="work-field" x-show="method === 'Transfer' || method === 'Transfer Bank'" x-cloak>
                <label for="arrears-proof">Bukti transfer <span class="font-normal">(opsional)</span></label>
                <input id="arrears-proof" name="proof" type="file" accept=".pdf,.jpg,.jpeg,.png" aria-describedby="arrears-proof-help">
                <p id="arrears-proof-help" class="work-muted">PDF, JPG, atau PNG, maksimal 5 MB.@if($errors->has('proof')) Pilih ulang file sebelum menyimpan.@endif</p>
            </div>
            <div class="work-field"><label for="arrears-date">Tanggal transaksi</label><input id="arrears-date" type="date" name="payment_date" value="{{ old('payment_date', now()->toDateString()) }}" required></div>
            <div class="account-dialog-actions"><button type="button" class="work-btn" @click="$refs.payment.close()" :disabled="saving">Batal</button><button type="submit" class="work-btn work-btn-primary" :disabled="saving || invoices.length === 0" x-text="saving ? 'Menyimpan…' : 'Simpan'"></button></div>
        </form>
    </dialog>
</div>
@endsection
