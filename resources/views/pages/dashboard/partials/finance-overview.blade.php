@php
    $dashboardDate = now()->locale('id');
    $rupiah = fn ($amount) => 'Rp '.number_format($amount, 0, ',', '.');
    $colors = ['#078579', '#3885ed', '#8b5cf6', '#e7a23a', '#64748b', '#ca587c'];
    $stats = [
        ['Total Pemasukan Bulan Ini', $summary['incomeMonth'], 'bx-trending-up', 'finance-green', $dashboardDate->translatedFormat('F Y')],
        ['Dana BOS Diterima Tahun Ini', $summary['bosYear'], 'bxs-bank', 'finance-blue', 'Januari–'.$dashboardDate->translatedFormat('F Y')],
        ['Total Pengeluaran Bulan Ini', $summary['expenseMonth'], 'bx-trending-down', 'finance-rose', 'Belanja disetujui · '.$dashboardDate->translatedFormat('F Y')],
        ['Saldo Kas Tunai', $summary['cashBalance'], 'bx-wallet', 'finance-violet', 'Saldo kas aktif tercatat'],
        ['Saldo Rekening Sekolah', $summary['bankBalance'], 'bxs-bank', 'finance-blue', 'Seluruh rekening bank aktif'],
        ['Total Tunggakan Siswa', $summary['totalOutstanding'] ?? ($financeHealth['totalOutstanding'] ?? 0), 'bx-error-circle', 'finance-amber', 'Sisa tagihan belum lunas'],
    ];
@endphp
<div class="finance-dashboard">
    <section id="tour-financial-metrics" class="finance-stats" aria-label="Ringkasan keuangan">
        @foreach($stats as [$label, $amount, $icon, $tone, $period])
            <article class="finance-panel finance-stat {{ $tone }}"><div class="finance-panel-heading"><span class="finance-icon"><i class="bx {{ $icon }}" aria-hidden="true"></i></span><h2>{{ $label }}</h2></div><strong class="finance-amount" title="{{ $rupiah($amount) }}">{{ $rupiah($amount) }}</strong><p class="finance-caption">{{ $period }}</p></article>
        @endforeach
    </section>

    <section class="finance-funds" aria-label="Saldo dan anggaran dana">
        <article class="finance-panel finance-blue"><div class="finance-panel-heading"><span class="finance-icon"><i class="bx bxs-bank" aria-hidden="true"></i></span><h2>Saldo BOS</h2></div><strong class="finance-amount" title="{{ $rupiah($summary['bosBalance']) }}">{{ $rupiah($summary['bosBalance']) }}</strong><p class="finance-caption">Saldo rekening aktif khusus BOS, BOP, dan DAK.</p><a href="{{ route('finance.accounts') }}" class="finance-link">Lihat rekening <span aria-hidden="true">↗</span></a></article>
        <article class="finance-panel finance-wallets"><div class="finance-panel-heading"><span class="finance-icon"><i class="bx bx-wallet" aria-hidden="true"></i></span><div><h2>Dompet Virtual</h2><p class="finance-caption">Komposisi saldo dompet aktif</p></div><a href="{{ route('finance.virtual-wallets') }}" class="finance-link ml-auto">Lihat detail</a></div>
            @if($summary['walletTotal'] > 0)
                <div class="finance-wallet-stack" role="img" aria-label="Komposisi saldo dompet virtual; rincian di bawah">@foreach($summary['wallets'] as $wallet)<span style="width: {{ $wallet['percentage'] }}%; background-color: {{ $colors[$loop->index % count($colors)] }}"></span>@endforeach</div>
                <div class="finance-wallet-list">@foreach($summary['wallets'] as $wallet)<div class="finance-wallet-row"><span class="finance-dot" style="background-color: {{ $colors[$loop->index % count($colors)] }}"></span><span>{{ $wallet['name'] }}</span><span>{{ $wallet['percentage'] }}%</span><strong>{{ $rupiah($wallet['amount']) }}</strong></div>@endforeach</div>
            @else<p class="finance-empty">Belum ada saldo dompet virtual yang dapat ditampilkan.</p>@endif
        </article>
        @foreach(['komite', 'bos'] as $source)
            @include('pages.dashboard.partials.finance-budget', ['budget' => $summary['budgets'][$source]])
        @endforeach
    </section>

    <section class="finance-collections" aria-label="Kinerja penagihan">
        <article class="finance-panel"><div class="finance-panel-heading"><span class="finance-icon"><i class="bx bx-group" aria-hidden="true"></i></span><h2>Collection Rate per Kelas</h2></div><p class="finance-caption">Pembayaran diterima / total tagihan · kelas siswa saat ini</p>
            @if($summary['byClass']->isNotEmpty())
                <div class="overflow-x-auto"><div id="finance-collection-class" style="min-width: {{ max(280, $summary['byClass']->count() * 52) }}px" aria-label="Grafik batang persentase pembayaran per kelas"></div></div>
                <details class="finance-caption"><summary class="cursor-pointer">Lihat rincian per kelas</summary><div class="overflow-x-auto"><table class="mt-2 w-full"><thead><tr><th class="text-left">Kelas</th><th class="text-right">Collection Rate</th></tr></thead><tbody>@foreach($summary['byClass'] as $item)<tr><td>{{ $item['name'] }}</td><td class="text-right">{{ $item['percentage'] }}%</td></tr>@endforeach</tbody></table></div></details>
            @else<p class="finance-empty">Belum ada tagihan per kelas.</p>@endif
        </article>
        <article class="finance-panel"><div class="finance-panel-heading"><span class="finance-icon"><i class="bx bx-receipt" aria-hidden="true"></i></span><h2>Collection Rate per Tagihan</h2></div><p class="finance-caption">Pembayaran diterima / total tiap jenis tagihan</p>
            <div class="finance-rate-list">@forelse($summary['byBill'] as $item)<div><div class="finance-rate-label"><span>{{ $item['name'] }}</span><strong>{{ $item['percentage'] }}%</strong></div><div class="finance-track"><span style="width: {{ $item['percentage'] }}%; background-color: {{ $colors[$loop->index % count($colors)] }}"></span></div><span class="sr-only">{{ $rupiah($item['paid']) }} dari {{ $rupiah($item['billed']) }}</span></div>@empty<p class="finance-empty">Belum ada tagihan untuk dihitung.</p>@endforelse</div>
        </article>
        <article class="finance-panel"><div class="finance-panel-heading"><span class="finance-icon"><i class="bx bx-line-chart" aria-hidden="true"></i></span><h2>Collection Trend</h2></div><p class="finance-caption">Tagihan jatuh tempo tiap bulan yang dibayar sampai akhir bulan tersebut.</p><div id="finance-collection-trend" aria-label="Tren collection rate {{ now()->year }} dan {{ now()->year - 1 }}"></div>
            <details class="finance-caption"><summary class="cursor-pointer">Lihat angka bulanan</summary><div class="overflow-x-auto"><table class="w-full mt-2"><thead><tr><th class="text-left">Bulan</th><th>{{ now()->year }}</th><th>{{ now()->year - 1 }}</th></tr></thead><tbody>@foreach($summary['trend']['months'] as $index => $month)<tr><td>{{ $month }}</td><td class="text-center">{{ $summary['trend']['current'][$index] === null ? '—' : $summary['trend']['current'][$index].'%' }}</td><td class="text-center">{{ $summary['trend']['previous'][$index] === null ? '—' : $summary['trend']['previous'][$index].'%' }}</td></tr>@endforeach</tbody></table></div></details>
        </article>
    </section>

    <section class="finance-panel finance-health-panel" aria-labelledby="finance-health-heading"><div class="finance-panel-heading"><span class="finance-icon"><i class="bx bx-heart" aria-hidden="true"></i></span><div><h2 id="finance-health-heading">Kesehatan Keuangan Sekolah</h2><p class="finance-caption">Kesehatan penagihan dan realisasi anggaran sekolah aktif.</p></div></div>
        <div class="finance-health-grid">
            @foreach(['collection' => 'Collection Rate', 'arrears' => 'Rasio Tunggakan'] as $key => $label)
                @php($metric = $financeHealth[$key])
                <article><h3>{{ $label }}</h3><div class="finance-ring finance-ring-{{ $key }}" style="--progress: {{ min(100, max(0, $metric['percentage'])) }}"><strong>{{ $metric['percentage'] }}%</strong></div><strong class="finance-health-label">{{ $metric['label'] }}</strong><p class="finance-caption">{{ $metric['description'] }}</p></article>
            @endforeach
            <article><h3>Siswa Menunggak</h3><strong class="finance-health-number">{{ $financeHealth['delinquentStudents']['count'] }} <span>siswa</span></strong><p class="finance-caption">dari {{ $financeHealth['delinquentStudents']['total'] }} siswa aktif ({{ $financeHealth['delinquentStudents']['percentage'] }}%)</p><a href="{{ route('finance.arrears') }}" class="finance-link">Lihat tunggakan</a></article>
            <article><h3>Cashflow</h3><strong class="finance-health-number" title="{{ $rupiah($summary['cashflow']) }}">{{ $rupiah($summary['cashflow']) }}</strong><p class="finance-caption">Arus kas bersih bulan ini</p><p class="finance-caption mt-4">Pemasukan dikurangi pengeluaran yang disetujui.</p></article>
            @foreach(['komite' => 'Realisasi Anggaran Komite', 'bos' => 'Serapan BOS'] as $source => $label)
                @php($budget = $summary['budgets'][$source])
                <article><h3>{{ $label }}</h3>@if($budget['percentage'] !== null)<div class="finance-ring {{ $source === 'bos' ? 'finance-ring-bos' : '' }}" style="--progress: {{ min(100, max(0, $budget['percentage'])) }}"><strong>{{ $budget['percentage'] }}%</strong></div><strong class="finance-health-label">{{ $rupiah($budget['spent']) }}</strong><p class="finance-caption">dari {{ $rupiah($budget['amount']) }}</p>@else<p class="finance-empty">Belum ada anggaran disetujui.</p>@endif</article>
            @endforeach
        </div>
    </section>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    if (!window.ApexCharts) return;
    const mountChart = (host, options, minimumWidth = 0) => {
        const chart = new ApexCharts(host, options);
        chart.render().then(() => {
            let width = host.clientWidth;
            new ResizeObserver(([entry]) => {
                const nextWidth = Math.max(minimumWidth, Math.round(entry.contentRect.width));
                if (nextWidth > 0 && Math.abs(width - nextWidth) > 1) {
                    width = nextWidth;
                    chart.updateOptions({ chart: { width } }, false, false);
                }
            }).observe(host.parentElement);
            new MutationObserver(() => chart.updateOptions({
                theme: { mode: document.documentElement.classList.contains('dark') ? 'dark' : 'light' },
                dataLabels: { style: { colors: [document.documentElement.classList.contains('dark') ? '#b8c6d6' : '#526579'] } }
            })).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
        });
    };
    const classHost = document.getElementById('finance-collection-class');
    if (classHost) {
        const classes = @json($summary['byClass']);
        mountChart(classHost, {
            chart: { type: 'bar', height: 260, toolbar: { show: false }, fontFamily: 'Inter, sans-serif', background: 'transparent', animations: { enabled: false } },
            series: [{ name: 'Collection Rate', data: classes.map(item => item.percentage) }],
            colors: @json($colors),
            plotOptions: { bar: { horizontal: false, columnWidth: '55%', borderRadius: 4, distributed: true, dataLabels: { position: 'top' } } },
            dataLabels: { enabled: true, offsetY: -18, formatter: value => value + '%', style: { colors: [document.documentElement.classList.contains('dark') ? '#b8c6d6' : '#526579'], fontSize: '10px', fontWeight: 600 } },
            xaxis: { categories: classes.map(item => item.name), labels: { rotate: -45, hideOverlappingLabels: false } },
            yaxis: { min: 0, max: 100, tickAmount: 4, labels: { formatter: value => value + '%' } },
            tooltip: { y: { formatter: value => value + '%' } },
            grid: { borderColor: '#94a3b833', padding: { top: 12 } }, legend: { show: false },
            theme: { mode: document.documentElement.classList.contains('dark') ? 'dark' : 'light' }
        }, Math.max(280, classes.length * 52));
    }
    const host = document.getElementById('finance-collection-trend');
    if (!host) return;
    const trend = @json($summary['trend']);
    const isolatedPoints = ['current', 'previous'].flatMap((key, index) => {
        if (trend[key].filter(value => value !== null).length !== 1) return [];
        return trend[key].flatMap((value, month) => value === null ? [] : [{
            x: trend.months[month], y: value,
            marker: { size: 4, fillColor: index === 0 ? '#3885ed' : '#94a3b8', strokeColor: '#ffffff' },
            label: { text: value + '%', borderColor: '#cbd5e1', style: { color: '#163344', background: '#ffffff' } }
        }]);
    });
    mountChart(host, {
        chart: { type: 'line', height: 240, toolbar: { show: false }, fontFamily: 'Inter, sans-serif', background: 'transparent', animations: { enabled: false } },
        series: [{ name: '{{ now()->year }}', data: trend.current }, { name: '{{ now()->year - 1 }}', data: trend.previous }],
        colors: ['#3885ed', '#94a3b8'], stroke: { width: [3, 2], curve: 'straight', dashArray: [0, 5] },
        xaxis: { categories: trend.months }, yaxis: { min: 0, max: 100, labels: { formatter: value => Math.round(value) + '%' } },
        dataLabels: { enabled: false }, markers: { size: 3 }, grid: { borderColor: '#94a3b833' },
        theme: { mode: document.documentElement.classList.contains('dark') ? 'dark' : 'light' },
        noData: { text: 'Belum ada data collection' }, legend: { position: 'top', horizontalAlign: 'right' },
        annotations: { points: isolatedPoints }
    });
});
</script>
