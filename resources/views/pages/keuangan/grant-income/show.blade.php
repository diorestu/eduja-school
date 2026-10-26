@extends('layouts.app')
@section('content')
<x-common.page-breadcrumb pageTitle="Revisi Pemasukan Hibah" label="Pemasukan Hibah" />
<div class="work work-stack grid-cols-1" x-data="{saving:false}">
    <a class="work-btn w-fit" href="{{ route('spp.transaksi.index', ['category' => 'hibah']) }}">Kembali ke Pemasukan Hibah</a>
    @if(session('success'))<p role="status" class="work-notice">{{ session('success') }}</p>@endif
    <p class="work-muted">Pengajuan tidak mengubah penerimaan atau saldo sampai disetujui kepala sekolah. Revisi yang disetujui menyesuaikan saldo kas/rekening dan alokasi dompet virtual.</p>
    @if($income->grantRevisions->contains('status', 'pending'))
        <p role="status" class="work-notice">Masih ada pengajuan revisi menunggu persetujuan kepala sekolah.</p>
    @else
        <form method="POST" action="{{ route('finance.grant-income.revisions', $income) }}" enctype="multipart/form-data" @submit="saving=true" :aria-busy="saving" class="work-stack grid-cols-1">
            @csrf
            @if($errors->any())<div role="alert" class="work-notice work-error">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
            @include('pages.keuangan.grant-income.fields')
            <div class="work-field"><label for="grant-revision-reason">Alasan revisi</label><textarea id="grant-revision-reason" name="reason" maxlength="1000" required>{{ old('reason') }}</textarea>@error('reason')<p class="work-error">{{ $message }}</p>@enderror</div>
            <button type="submit" class="work-btn work-btn-primary w-fit" :disabled="saving" x-text="saving ? 'Mengajukan…' : 'Ajukan Revisi'"></button>
        </form>
    @endif
    <h2 class="text-lg font-semibold">Jejak Revisi</h2>
    @forelse($income->grantRevisions->sortByDesc('id') as $revision)
        <div class="rounded-lg border border-gray-200 p-4 text-sm dark:border-gray-700">
            <p class="font-semibold">{{ ['pending' => 'Menunggu persetujuan', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'][$revision->status] ?? $revision->status }}</p>
            <p>Diajukan oleh {{ $revision->requester?->name ?? '-' }} pada {{ $revision->created_at->format('d/m/Y H:i') }} {{ config('app.timezone') }}</p>
            <p>Nominal Rp {{ number_format((float) $revision->old_data['amount'], 2, ',', '.') }} menjadi Rp {{ number_format((float) $revision->new_data['amount'], 2, ',', '.') }}</p>
            <p>Donatur: {{ $revision->old_data['donor_name'] }} → {{ $revision->new_data['donor_name'] }}</p>
            <p>Metode: {{ $revision->old_data['payment_method'] }} → {{ $revision->new_data['payment_method'] }}</p>
            <p>Tanggal terima {{ $revision->old_data['received_date'] }} → {{ $revision->new_data['received_date'] }}</p>
            <p>Alasan: {{ $revision->reason }}</p>
            <p>Rekening: {{ $accountNames[$revision->old_data['account_id']] ?? 'Belum tercatat' }} → {{ $accountNames[$revision->new_data['account_id']] ?? '-' }}</p>
            <p>Jenis Hibah: {{ $typeNames[$revision->old_data['income_type_id']] ?? 'Belum tercatat' }} → {{ $typeNames[$revision->new_data['income_type_id']] ?? 'Belum tercatat' }}</p>
            @php
                $oldWallets = collect($revision->old_data['grant_allocations'] ?? [])->groupBy('wallet_id')->map(fn ($rules) => $rules->sum('cents'));
                $newWallets = collect($revision->new_data['grant_allocations'] ?? [])->groupBy('wallet_id')->map(fn ($rules) => $rules->sum('cents'));
                $walletIds = $oldWallets->keys()->merge($newWallets->keys())->unique();
            @endphp
            @foreach($walletIds as $walletId)
                <p>Alokasi {{ $walletNames[$walletId] ?? 'Dompet dihapus' }}: Rp {{ number_format(($oldWallets[$walletId] ?? 0) / 100, 2, ',', '.') }} → Rp {{ number_format(($newWallets[$walletId] ?? 0) / 100, 2, ',', '.') }}</p>
            @endforeach
            @if($revision->reviewed_at)<p>Diputuskan oleh {{ $revision->reviewer?->name ?? '-' }} pada {{ $revision->reviewed_at->format('d/m/Y H:i') }} {{ config('app.timezone') }}</p>@endif
            @if($revision->new_data['proof_path'])<a class="work-btn mt-2" href="{{ route('finance.grant-income.revision-proof', $revision) }}">Unduh Bukti Pengajuan</a>@endif
            @if($revision->old_data['proof_path'] && $revision->old_data['proof_path'] !== $revision->new_data['proof_path'])<a class="work-btn mt-2" href="{{ route('finance.grant-income.revision-proof', ['revision' => $revision, 'version' => 'old']) }}">Unduh Bukti Sebelum Revisi</a>@endif
        </div>
    @empty<p class="work-muted">Belum ada revisi.</p>@endforelse
</div>
@endsection
