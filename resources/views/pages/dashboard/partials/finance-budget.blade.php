<article class="finance-panel finance-budget {{ $source === 'bos' ? 'finance-violet' : 'finance-amber' }}">
    <div class="finance-panel-heading"><span class="finance-icon"><i class="bx {{ $source === 'bos' ? 'bxs-bank' : 'bx-briefcase' }}" aria-hidden="true"></i></span><h2>Anggaran {{ $source === 'bos' ? 'BOS' : 'Komite' }}</h2></div>
    <strong class="finance-amount">{{ $rupiah($budget['amount']) }}</strong>
    <p class="finance-caption">Tahun anggaran berjalan · disetujui</p>
    @if($budget['percentage'] !== null)
        <div class="finance-track mt-5" role="progressbar" aria-label="Realisasi anggaran {{ $source }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ min(100, $budget['percentage']) }}"><span style="width: {{ min(100, $budget['percentage']) }}%"></span></div>
        <div class="finance-budget-meta"><span>{{ $budget['percentage'] }}% terserap</span><span>{{ $rupiah($budget['remaining']) }} sisa</span></div>
    @else
        <p class="finance-empty">Belum ada anggaran disetujui untuk periode ini.</p>
    @endif
</article>
