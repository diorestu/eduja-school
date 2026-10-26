@extends('layouts.app')
@section('content')
<x-common.page-breadcrumb pageTitle="Pemasukan BOS" label="Pemasukan" />
<div class="work work-stack grid-cols-1" x-data="{saving:false}">
    @include('pages.keuangan.income-category')
    @if(session('success'))<p role="status" class="work-notice">{{ session('success') }}</p>@endif
    <h2 class="text-lg font-semibold">Catat Pemasukan BOS</h2>
    <form method="POST" action="{{ route('spp.transaksi.pemasukan') }}" enctype="multipart/form-data" @submit="saving=true" :aria-busy="saving" class="work-stack grid-cols-1">
        @csrf<input type="hidden" name="category" value="bos">
        <input type="hidden" name="bos_submission_key" value="{{ old('bos_submission_key', (string) \Illuminate\Support\Str::uuid()) }}">
        @if($errors->any())<div role="alert" class="work-notice work-error">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
        @include('pages.keuangan.bos-income.fields')
        <button type="submit" class="work-btn work-btn-primary w-fit" :disabled="saving" x-text="saving ? 'Menyimpan…' : 'Simpan'"></button>
    </form>
    <h2 class="text-lg font-semibold">Riwayat Penerimaan BOS</h2>
    <section class="min-w-0 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700"><div class="overflow-x-auto"><table class="w-full text-left text-sm">
        <thead class="bg-gray-50 dark:bg-gray-800"><tr><th class="p-3">Tanggal</th><th class="p-3">Sumber dana / Tahun</th><th class="p-3">Nominal</th><th class="p-3">Rekening</th><th class="p-3">Aksi</th></tr></thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">@forelse($incomes as $receipt)<tr>
            <td class="p-3 whitespace-nowrap">{{ $receipt->received_date?->locale('id')->translatedFormat('d M Y') ?? '-' }}</td><td class="p-3">{{ $receipt->source_funding }}<span class="block text-xs">{{ $receipt->bos_year ?? '-' }}</span></td><td class="p-3 whitespace-nowrap tabular-nums">Rp {{ number_format($receipt->amount, fmod((float) $receipt->amount, 1.0) === 0.0 ? 0 : 2, ',', '.') }}</td><td class="p-3">{{ $receipt->account?->name ?? 'Belum tercatat' }}</td>
            <td class="p-3"><a class="work-btn" href="{{ route('finance.bos-income.show', $receipt) }}">Revisi / Riwayat</a>@if($receipt->proof_path)<a class="work-btn" href="{{ route('finance.bos-income.proof', $receipt) }}">Unduh SP2D</a>@endif @if($receipt->revisions->contains('status', 'pending'))<span class="block text-xs">Revisi menunggu kepala sekolah</span>@endif</td>
        </tr>@empty<tr><td colspan="5" class="p-8 text-center text-gray-500">Belum ada penerimaan BOS.</td></tr>@endforelse</tbody>
    </table></div></section>
    {{ $incomes->links() }}
</div>
@endsection
