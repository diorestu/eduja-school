@extends('layouts.app')

@section('content')
<x-common.page-breadcrumb pageTitle="Dashboard Kepala Dinas" label="Monitoring Dinas" />
<div class="space-y-4">
    <section class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
        @foreach(['sd_mi'=>'SD / MI','smp_mts'=>'SMP / MTs','sma_ma'=>'SMA / MA','smk_mak'=>'SMK / MAK','negeri'=>'Negeri','swasta'=>'Swasta'] as $key => $label)
            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]"><p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $label }}</p><p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ $districtDashboard['schoolBreakdown'][$key] }}</p><p class="mt-1 text-xs text-gray-500">sekolah aktif</p></div>
        @endforeach
    </section>
    <section class="grid grid-cols-1 gap-3 md:grid-cols-3">
        @foreach(['students'=>'Siswa','teachers'=>'Guru','staff'=>'Tendik'] as $key => $label)
            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]"><p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $label }}</p><p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($districtDashboard['people'][$key], 0, ',', '.') }}</p><p class="mt-1 text-xs text-gray-500">aktif di seluruh sekolah</p></div>
        @endforeach
    </section>
    <section class="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]"><h2 class="text-sm font-semibold text-gray-900 dark:text-white">Absensi hadir per bulan</h2><p class="mt-1 text-xs text-gray-500">6 bulan terakhir · siswa, guru, dan tendik</p><div id="district-attendance-chart" class="mt-4 min-h-[300px]"></div></div>
        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]"><h2 class="text-sm font-semibold text-gray-900 dark:text-white">Pengeluaran BOS</h2><p class="mt-1 text-xs text-gray-500">6 bulan terakhir · selain transaksi ditolak</p><div id="district-bos-chart" class="mt-4 min-h-[300px]"></div></div>
    </section>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => { const d = @json($districtDashboard); if (!window.ApexCharts) return;
new ApexCharts(document.querySelector('#district-attendance-chart'), { chart:{type:'line',height:300,toolbar:{show:false}}, series:[{name:'Siswa',data:d.attendance.students},{name:'Guru',data:d.attendance.teachers},{name:'Tendik',data:d.attendance.staff}], xaxis:{categories:d.months}, stroke:{curve:'smooth',width:3}, colors:['#087f7a','#f59e0b','#315c72'], legend:{position:'top'}}).render();
new ApexCharts(document.querySelector('#district-bos-chart'), { chart:{type:'bar',height:300,toolbar:{show:false}}, series:[{name:'Pengeluaran BOS',data:d.bos}], xaxis:{categories:d.months}, yaxis:{labels:{formatter:v=>'Rp '+new Intl.NumberFormat('id-ID',{notation:'compact'}).format(v)}}, colors:['#315c72'], plotOptions:{bar:{borderRadius:6,columnWidth:'48%'}}, tooltip:{y:{formatter:v=>'Rp '+new Intl.NumberFormat('id-ID').format(v)}}}).render(); });
</script>
@endsection
