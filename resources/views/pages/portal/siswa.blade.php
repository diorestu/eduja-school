@extends('layouts.fullscreen-layout')

@section('content')
<div class="portal-shell">
    <header class="portal-header"><div class="portal-header__inner"><a class="portal-brand" href="{{ route('portal.siswa') }}">EDUJA · Portal Siswa</a><form action="{{ route('logout') }}" method="POST">@csrf<button class="portal-action" type="submit">Keluar</button></form></div></header>
    <main class="portal-main">
        <div class="portal-grid portal-grid--two">
            <section class="portal-card"><p class="portal-muted">Siswa aktif</p><h1 class="text-2xl font-extrabold">{{ $student->name }}</h1><p class="portal-muted mt-1">{{ $className }}</p>
                @if(session('success'))<p class="portal-status mt-4" role="status">{{ session('success') }}</p>@endif
                @if(session('error'))<p class="portal-feedback is-visible" role="alert">{{ session('error') }}</p>@endif
                @error('attendance')<p class="portal-feedback is-visible" role="alert">{{ $message }}</p>@enderror
                @error('location')<p class="portal-feedback is-visible" role="alert">{{ $message }}</p>@enderror
                <div class="portal-stats mt-6">@foreach(['Hadir'=>$stats['hadir'],'Sakit'=>$stats['sakit'],'Izin'=>$stats['izin'],'Alpha'=>$stats['alpa']] as $label => $value)<div><p class="portal-muted">{{ $label }}</p><p class="portal-figure">{{ $value }}</p></div>@endforeach</div>
                <p class="portal-muted mt-4">@if($stats['rate'] !== null) Kehadiran tercatat: {{ $stats['rate'] }}%. @else Belum ada riwayat presensi untuk dihitung. @endif</p>
            </section>
            <section class="portal-card"><h2>Presensi hari ini</h2>
                <p class="portal-muted mt-2">@if($todayAttendance?->clock_in_at) Masuk tercatat {{ substr($todayAttendance->clock_in_at, 0, 5) }}@else Belum ada presensi masuk.@endif</p>
                <form action="{{ route('portal.siswa.attendance') }}" method="POST" class="portal-form" data-portal-attendance>@csrf
                    <input type="hidden" name="type" value="{{ $todayAttendance?->clock_in_at ? 'pulang' : 'masuk' }}">
                    <button class="portal-action portal-action--primary" type="submit" @disabled($todayAttendance?->clock_out_at)>{{ $todayAttendance?->clock_in_at ? 'Catat pulang' : 'Catat masuk' }}</button>
                    <p class="portal-muted">Aplikasi meminta lokasi perangkat saat Anda menekan tombol. Koordinat tidak ditampilkan di portal.</p>
                    <p class="portal-feedback" data-location-feedback role="alert"></p>
                </form>
            </section>
        </div>
        <div class="portal-grid portal-grid--two mt-4">
            <section class="portal-card"><h2>Lokasi presensi sekolah</h2>
                @if($map['enabled'])<div id="portal-map" class="portal-map" data-map='@json($map)'></div><p class="portal-muted mt-3">Peta menunjukkan area presensi sekolah, bukan posisi detail Anda.</p>
                @else<p class="portal-muted mt-3">Peta sekolah belum diaktifkan. Hubungi admin sekolah bila presensi lokasi diperlukan.</p>@endif
            </section>
            <section class="portal-card"><h2>Wali kelas</h2>
                @if($homeroomTeacher)<p class="mt-3 font-bold">{{ $homeroomTeacher->name }}</p>@if($homeroomTeacher->phone)<a class="portal-action mt-4" href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $homeroomTeacher->phone) }}" target="_blank" rel="noopener">Hubungi wali kelas</a>@endif
                @else<p class="portal-muted mt-3">Wali kelas belum tercatat untuk kelas ini.</p>@endif
            </section>
        </div>
        <div class="portal-grid portal-grid--two mt-4">
            <section class="portal-card"><h2>Riwayat presensi</h2><div class="portal-list">@forelse($attendanceHistory as $entry)<div class="portal-meta"><span>{{ $entry->attendance_date->translatedFormat('d M Y') }}</span><strong>{{ ['H'=>'Hadir','S'=>'Sakit','I'=>'Izin','D'=>'Dispensasi','A'=>'Alpha'][$entry->status] ?? $entry->status }}</strong></div>@empty<p class="portal-muted">Belum ada riwayat presensi.</p>@endforelse</div></section>
            <section class="portal-card"><h2>Jadwal dan nilai</h2><p class="portal-muted mt-3">Jadwal belum tersedia.</p><p class="portal-muted mt-2">Nilai belum tersedia.</p><p class="portal-muted mt-4">Data akan tampil setelah sekolah menghubungkan modul akademik.</p></section>
        </div>
        <section class="portal-card mt-4"><h2>Ajukan izin atau sakit</h2><p class="portal-muted mt-2">Setiap pengajuan masuk ke antrean persetujuan sekolah.</p><x-portal.permission-form :student="$student" /></section>
        <section class="portal-card mt-4"><h2>Pengajuan terbaru</h2><div class="portal-list">@forelse($permissionRequests as $item)<div class="portal-meta"><span>{{ ucfirst($item->request_type) }} · {{ $item->start_date->translatedFormat('d M Y') }}</span><span class="portal-status">{{ ['pending'=>'Menunggu keputusan','approved'=>'Disetujui','rejected'=>'Ditolak'][$item->status] ?? $item->status }}</span></div>@empty<p class="portal-muted">Belum ada pengajuan.</p>@endforelse</div></section>
    </main>
</div>
@include('pages.portal.mapbox-script')
@endsection
