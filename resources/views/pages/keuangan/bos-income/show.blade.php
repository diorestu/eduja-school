@extends('layouts.app')
@section('content')
<x-common.page-breadcrumb pageTitle="Revisi Pemasukan BOS" label="Pemasukan BOS" />
<div class="work work-stack grid-cols-1" x-data="{saving:false}">
    <a class="work-btn w-fit" href="{{ route('spp.transaksi.index', ['category' => 'bos']) }}">Kembali ke Pemasukan BOS</a>
    @if(session('success'))<p role="status" class="work-notice">{{ session('success') }}</p>@endif
    <p class="work-muted">Pengajuan tidak mengubah penerimaan atau saldo sampai disetujui kepala sekolah. Revisi yang disetujui menyesuaikan selisih nominal pada rekening asal.</p>
    @if($income->revisions->contains('status', 'pending'))
        <p role="status" class="work-notice">Masih ada pengajuan revisi menunggu persetujuan kepala sekolah.</p>
    @else
        <form method="POST" action="{{ route('finance.bos-income.revisions', $income) }}" enctype="multipart/form-data" @submit="saving=true" :aria-busy="saving" class="work-stack grid-cols-1">
            @csrf
            @if($errors->any())<div role="alert" class="work-notice work-error">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
            @include('pages.keuangan.bos-income.fields')
            <div class="work-field"><label for="bos-revision-reason">Alasan revisi</label><textarea id="bos-revision-reason" name="reason" maxlength="1000" required>{{ old('reason') }}</textarea>@error('reason')<p class="work-error">{{ $message }}</p>@enderror</div>
            <button type="submit" class="work-btn work-btn-primary w-fit" :disabled="saving" x-text="saving ? 'Mengajukan…' : 'Ajukan Revisi'"></button>
        </form>
    @endif
    <h2 class="text-lg font-semibold">Jejak Revisi</h2>
    @forelse($income->revisions->sortByDesc('id') as $revision)
        <div class="rounded-lg border border-gray-200 p-4 text-sm dark:border-gray-700">
            <p class="font-semibold">{{ ['pending' => 'Menunggu persetujuan', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'][$revision->status] ?? $revision->status }}</p>
            <p>Diajukan oleh {{ $revision->requester?->name ?? '-' }} pada {{ $revision->created_at->format('d/m/Y H:i') }} {{ config('app.timezone') }}</p>
            <p>Nominal Rp {{ number_format((float) $revision->old_data['amount'], 2, ',', '.') }} menjadi Rp {{ number_format((float) $revision->new_data['amount'], 2, ',', '.') }}</p>
            <p>{{ $revision->old_data['source_funding'] }} → {{ $revision->new_data['source_funding'] }} · Tahun {{ $revision->old_data['bos_year'] ?? '-' }} → {{ $revision->new_data['bos_year'] }}</p>
            <p>Tanggal terima {{ $revision->old_data['received_date'] }} → {{ $revision->new_data['received_date'] }}</p>
            <p>Alasan: {{ $revision->reason }}</p>
            <p>Rekening: {{ $accountNames[$revision->old_data['account_id']] ?? 'Belum tercatat' }} → {{ $accountNames[$revision->new_data['account_id']] ?? '-' }}</p>
            @if($revision->reviewed_at)<p>Diputuskan oleh {{ $revision->reviewer?->name ?? '-' }} pada {{ $revision->reviewed_at->format('d/m/Y H:i') }} {{ config('app.timezone') }}</p>@endif
            @if($revision->new_data['proof_path'])<a class="work-btn mt-2" href="{{ route('finance.bos-income.revision-proof', $revision) }}">Unduh SP2D Pengajuan</a>@endif
            @if($revision->old_data['proof_path'] && $revision->old_data['proof_path'] !== $revision->new_data['proof_path'])<a class="work-btn mt-2" href="{{ route('finance.bos-income.revision-proof', ['revision' => $revision, 'version' => 'old']) }}">Unduh SP2D Sebelum Revisi</a>@endif
        </div>
    @empty<p class="work-muted">Belum ada revisi.</p>@endforelse
</div>
@endsection
