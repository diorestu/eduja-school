@extends('layouts.app')

@section('content')
<x-common.page-breadcrumb pageTitle="Monitoring Dinas Pendidikan" />
<div class="space-y-4">
    <section class="flex flex-col gap-4 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03] lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h1 class="text-xl font-semibold text-gray-900 dark:text-white">{{ $selectedSchool?->name ?? 'Semua sekolah' }}</h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $selectedSchool ? 'Ringkasan sekolah yang dipilih.' : 'Ringkasan seluruh sekolah aktif.' }} Data monitoring hanya untuk dilihat.</p>
            @if($selectedSchool)
                <a href="{{ route('dinas.dashboard') }}" class="mt-2 inline-flex min-h-11 items-center text-sm font-medium text-brand-600 underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500 dark:text-brand-300">Kembali ke semua sekolah</a>
            @endif
        </div>
        <form method="GET" action="{{ route('dinas.dashboard') }}" class="flex w-full flex-col gap-2 sm:flex-row sm:items-end lg:w-auto">
            <div class="min-w-0 sm:flex-1 lg:w-72">
                <label for="district-school" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Sekolah</label>
                <select id="district-school" name="school_id" class="h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-900 focus:border-brand-500 focus:ring-2 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    <option value="">Semua sekolah</option>
                    @foreach($schools as $school)
                        <option value="{{ $school->id }}" @selected($selectedSchool?->id === $school->id)>{{ $school->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="inline-flex h-11 items-center justify-center rounded-lg bg-brand-500 px-5 text-sm font-semibold text-white hover:bg-brand-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500">Tampilkan</button>
        </form>
    </section>
    @if($schools->isEmpty())
        <p role="status" class="text-sm text-gray-600 dark:text-gray-400">Belum ada sekolah aktif untuk ditampilkan.</p>
    @endif
    <section aria-label="Jumlah sekolah" class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
        @foreach(['sd_mi'=>'SD / MI','smp_mts'=>'SMP / MTs','sma_ma'=>'SMA / MA','smk_mak'=>'SMK / MAK','negeri'=>'Negeri','swasta'=>'Swasta'] as $key => $label)
            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]"><p class="text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-400">{{ $label }}</p><p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ $districtDashboard['schoolBreakdown'][$key] }}</p><p class="mt-1 text-xs text-gray-600 dark:text-gray-400">sekolah aktif</p></div>
        @endforeach
    </section>
    <section aria-label="Jumlah siswa dan tenaga sekolah" class="grid grid-cols-1 gap-3 md:grid-cols-3">
        @foreach(['students'=>'Siswa','teachers'=>'Guru','staff'=>'Tendik'] as $key => $label)
            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]"><p class="text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-400">{{ $label }}</p><p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($districtDashboard['people'][$key], 0, ',', '.') }}</p><p class="mt-1 text-xs text-gray-600 dark:text-gray-400">{{ $selectedSchool ? 'aktif di sekolah ini' : 'aktif di seluruh sekolah' }}</p></div>
        @endforeach
    </section>
    <section class="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <div class="min-w-0 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Absensi hadir per bulan</h2>
            <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">6 bulan terakhir · siswa, guru, dan tendik</p>
            <div id="district-attendance-chart" class="mt-4 min-h-[300px]" aria-label="Grafik kehadiran siswa, guru, dan tendik"></div>
        </div>
        <div class="min-w-0 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Pengeluaran BOS</h2>
            @if($districtDashboard['finance']['available'])
                <p class="mt-1 text-xs leading-5 text-gray-600 dark:text-gray-400">6 bulan terakhir · transaksi disetujui. Data dari {{ $districtDashboard['finance']['schoolCount'] }} dari {{ $districtDashboard['finance']['totalSchoolCount'] }} sekolah yang memberi izin keuangan.</p>
                <div id="district-bos-chart" class="mt-4 min-h-[300px]" aria-label="Grafik pengeluaran BOS sekolah yang memberi izin"></div>
            @else
                <p class="mt-4 text-sm leading-6 text-gray-600 dark:text-gray-400">{{ $schools->isEmpty() ? 'Belum ada data keuangan untuk ditampilkan.' : 'Belum ada izin keuangan dari kepala sekolah untuk cakupan ini.' }}</p>
            @endif
        </div>
    </section>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const data = @json($districtDashboard);
    if (!window.ApexCharts) return;
    const animations = { enabled: !window.matchMedia('(prefers-reduced-motion: reduce)').matches };
    const renderChart = (selector, options) => {
        const element = document.querySelector(selector);
        let width = element.clientWidth;
        const chart = new ApexCharts(element, {
            ...options,
            chart: {...options.chart, width, redrawOnWindowResize: false, redrawOnParentResize: false}
        });
        chart.render().then(() => {
            new ResizeObserver(([entry]) => {
                const nextWidth = Math.floor(entry.contentRect.width);
                if (nextWidth > 0 && Math.abs(nextWidth - width) > 1) {
                    width = nextWidth;
                    chart.updateOptions({chart: {width}}, false, false);
                }
            }).observe(element);
        });
    };
    renderChart('#district-attendance-chart', {
        chart: {type: 'line', height: 300, toolbar: {show: false}, animations},
        series: [{name: 'Siswa', data: data.attendance.students}, {name: 'Guru', data: data.attendance.teachers}, {name: 'Tendik', data: data.attendance.staff}],
        xaxis: {categories: data.months}, stroke: {curve: 'smooth', width: 3},
        colors: ['#087f7a', '#a16207', '#315c72'], legend: {position: 'top'}
    });
    if (data.finance.available) {
        renderChart('#district-bos-chart', {
            chart: {type: 'bar', height: 300, toolbar: {show: false}, animations},
            dataLabels: {enabled: false},
            series: [{name: 'Pengeluaran BOS', data: data.bos}], xaxis: {categories: data.months},
            yaxis: {labels: {formatter: value => 'Rp ' + new Intl.NumberFormat('id-ID', {notation: 'compact'}).format(value)}},
            colors: ['#315c72'], plotOptions: {bar: {borderRadius: 6, columnWidth: '48%'}},
            tooltip: {y: {formatter: value => 'Rp ' + new Intl.NumberFormat('id-ID').format(value)}}
        });
    }
});
</script>
@endsection
