@extends('layouts.app')
@section('content')
<x-common.page-breadcrumb :pageTitle="$committeeIncome ? 'Pemasukan Komite' : 'Tunggakan Siswa'" label="Keuangan" />
<div class="work work-stack grid-cols-1">
    @if($committeeIncome) @include('pages.keuangan.income-category') @endif
    <p class="work-muted">Pilih siswa untuk melihat tagihan dan mencatat pembayaran. Bulan dan tahun mengikuti periode tagihan.</p>
    <form method="GET" action="{{ $committeeIncome ? route('spp.transaksi.index') : route('finance.arrears') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-6">
        @if($committeeIncome)<input type="hidden" name="category" value="komite">@endif
        <div class="work-field lg:col-span-2"><label for="arrears-search">Nama, NIS, atau NISN</label><input id="arrears-search" name="search" value="{{ $filters['search'] ?? '' }}" maxlength="160" placeholder="Cari siswa"></div>
        <div class="work-field"><label for="arrears-sort">Besar tunggakan</label><select id="arrears-sort" name="sort"><option value="desc" @selected(($filters['sort'] ?? 'desc') === 'desc')>Besar–Kecil</option><option value="asc" @selected(($filters['sort'] ?? '') === 'asc')>Kecil–Besar</option></select></div>
        <div class="work-field"><label for="arrears-class">Kelas</label><select id="arrears-class" name="class_id"><option value="">Semua kelas</option>@foreach($classes as $class)<option value="{{ $class->id }}" @selected(($filters['class_id'] ?? '') == $class->id)>{{ $class->name }} · {{ $class->academicYear?->year ?? '-' }}</option>@endforeach</select></div>
        <div class="work-field"><label for="arrears-month">Bulan</label><select id="arrears-month" name="month"><option value="">Semua bulan</option>@foreach(range(1, 12) as $month)<option value="{{ $month }}" @selected(($filters['month'] ?? '') == $month)>{{ \Carbon\Carbon::create(2026, $month, 1)->locale('id')->translatedFormat('F') }}</option>@endforeach</select></div>
        <div class="work-field"><label for="arrears-year">Tahun</label><input id="arrears-year" type="number" name="year" min="1900" max="2200" value="{{ $filters['year'] ?? '' }}" placeholder="Semua tahun"></div>
        <div class="flex items-center gap-2 sm:col-span-2 lg:col-span-6"><button type="submit" class="work-btn work-btn-primary">Terapkan filter</button><a class="work-btn" href="{{ $committeeIncome ? route('spp.transaksi.index', ['category' => 'komite']) : route('finance.arrears') }}">Reset</a></div>
    </form>
    @if($errors->any())<div role="alert" class="work-notice work-error">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <section class="data-table min-w-0 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700" aria-label="Daftar tunggakan siswa">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-gray-700 dark:bg-gray-800 dark:text-gray-200"><tr><th class="p-3">Siswa</th><th class="p-3">Kelas</th><th class="p-3 text-right">Nominal Tunggakan</th><th class="p-3">Status</th></tr></thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($students as $row)
                    <tr>
                        <td class="p-3"><a href="{{ route($committeeIncome ? 'finance.committee-income.show' : 'finance.arrears.show', $row['student']) }}" class="flex items-center gap-3 rounded focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-500">
                            @include('pages.keuangan.arrears.photo', ['student' => $row['student']])
                            <span><span class="block font-semibold">{{ $row['student']->name }}</span><span class="text-xs text-gray-500 dark:text-gray-400">NIS {{ $row['student']->nis ?? '-' }} · NISN {{ $row['student']->nisn ?? '-' }}</span></span>
                        </a></td>
                        <td class="p-3 whitespace-nowrap">{{ $row['class_name'] }}</td><td class="p-3 text-right whitespace-nowrap tabular-nums">Rp {{ number_format($row['total_arrears'], fmod((float) $row['total_arrears'], 1.0) === 0.0 ? 0 : 2, ',', '.') }}</td><td class="p-3 whitespace-nowrap">{{ $row['status'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="p-8 text-center text-gray-500 dark:text-gray-400">Tidak ada tunggakan yang sesuai filter.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <div>{{ $students->links() }}</div>
</div>
@endsection
