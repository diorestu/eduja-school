@extends('layouts.fullscreen-layout')

@section('content')
<div class="portal-shell">
    <header class="portal-header"><div class="portal-header__inner"><a class="portal-brand" href="{{ route('portal.orang-tua') }}">EDUJA · Portal Wali Murid</a><form action="{{ route('logout') }}" method="POST">@csrf<button class="portal-action" type="submit">Keluar</button></form></div></header>
    <main class="portal-main">
        <section class="portal-card"><label class="portal-muted" for="student-selector">Anak yang dipantau</label><select id="student-selector" class="mt-2 min-h-11 w-full rounded-lg border border-zinc-200 px-3 text-sm dark:border-zinc-700 dark:bg-zinc-900" onchange="window.location.assign('{{ route('portal.orang-tua') }}?student_id=' + this.value)">@foreach($students as $child)<option value="{{ $child->id }}" @selected($child->id === $student->id)>{{ $child->name }} · {{ $child->nis }}</option>@endforeach</select></section>
        <div class="portal-grid portal-grid--two mt-4">
            <section class="portal-card"><p class="portal-muted">Ringkasan kehadiran</p><h1 class="text-2xl font-extrabold">{{ $student->name }}</h1><p class="portal-muted mt-1">{{ $className }}</p><div class="portal-stats mt-6">@foreach(['Hadir'=>$stats['hadir'],'Sakit'=>$stats['sakit'],'Izin'=>$stats['izin'],'Alpha'=>$stats['alpa']] as $label=>$value)<div><p class="portal-muted">{{ $label }}</p><p class="portal-figure">{{ $value }}</p></div>@endforeach</div><p class="portal-muted mt-4">@if($stats['rate'] !== null) Kehadiran tercatat: {{ $stats['rate'] }}%. @else Belum ada riwayat presensi untuk dihitung. @endif</p></section>
            <section class="portal-card"><h2>Presensi hari ini</h2><p class="portal-muted mt-3">@if($todayAttendance?->clock_in_at) Masuk tercatat {{ substr($todayAttendance->clock_in_at, 0, 5) }}. @if($todayAttendance?->clock_out_at) Pulang tercatat {{ substr($todayAttendance->clock_out_at, 0, 5) }}.@endif @else Belum ada presensi tercatat hari ini.@endif</p><p class="portal-muted mt-4">Presensi mandiri dilakukan dari akun siswa.</p></section>
        </div>
        <div class="portal-grid portal-grid--two mt-4">
            <section class="portal-card"><h2>Lokasi presensi sekolah</h2>@if($map['enabled'])<div id="portal-map" class="portal-map" data-map='@json($map)'></div><p class="portal-muted mt-3">Peta menunjukkan area presensi sekolah.</p>@else<p class="portal-muted mt-3">Peta sekolah belum diaktifkan. Hubungi admin sekolah bila presensi lokasi diperlukan.</p>@endif</section>
            <section class="portal-card"><h2>Wali kelas</h2>@if($homeroomTeacher)<p class="mt-3 font-bold">{{ $homeroomTeacher->name }}</p>@if($homeroomTeacher->phone)<a class="portal-action mt-4" href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $homeroomTeacher->phone) }}" target="_blank" rel="noopener">Hubungi wali kelas</a>@endif @else<p class="portal-muted mt-3">Wali kelas belum tercatat untuk kelas ini.</p>@endif</section>
        </div>
        <div class="portal-grid portal-grid--two mt-4">
            <section class="portal-card"><h2>Tagihan sekolah</h2><div class="portal-list">@forelse($invoices as $invoice)<div class="portal-meta"><span>{{ $invoice->invoice_number ?: 'Tagihan sekolah' }}<small class="block portal-muted">Jatuh tempo {{ $invoice->due_date?->translatedFormat('d M Y') ?? 'belum ditentukan' }}</small></span><strong>Rp {{ number_format($invoice->remaining_amount, 0, ',', '.') }}</strong></div>@empty<p class="portal-muted">Tidak ada tagihan untuk saat ini.</p>@endforelse</div></section>
            <section class="portal-card"><h2>Tabungan</h2><p class="portal-figure mt-3">Rp {{ number_format($savingsBalance, 0, ',', '.') }}</p><div class="portal-list">@forelse($savings->take(5) as $saving)<div class="portal-meta"><span>{{ $saving->type === 'credit' ? 'Setoran' : 'Penarikan' }}</span><strong>{{ $saving->type === 'credit' ? '+' : '-' }} Rp {{ number_format($saving->amount, 0, ',', '.') }}</strong></div>@empty<p class="portal-muted">Belum ada mutasi tabungan.</p>@endforelse</div></section>
        </div>
        <section class="portal-card mt-4"><h2>Ajukan izin atau sakit</h2><p class="portal-muted mt-2">Pengajuan ini dikirim atas nama {{ $student->name }} dan menunggu keputusan sekolah.</p><x-portal.permission-form :student="$student" /></section>
        <section class="portal-card mt-4"><h2>Pengajuan terbaru</h2><div class="portal-list">@forelse($permissionRequests as $item)<div class="portal-meta"><span>{{ ucfirst($item->request_type) }} · {{ $item->start_date->translatedFormat('d M Y') }}</span><span class="portal-status">{{ ['pending'=>'Menunggu keputusan','approved'=>'Disetujui','rejected'=>'Ditolak'][$item->status] ?? $item->status }}</span></div>@empty<p class="portal-muted">Belum ada pengajuan.</p>@endforelse</div></section>
    </main>
</div>
@include('pages.portal.mapbox-script')
@endsection
