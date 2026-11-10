@extends('layouts.app')

@section('content')
@php
    $reportColumns = [
        ['key' => 'date_formatted', 'label' => 'Tanggal / Ref', 'bold' => true, 'subKey' => 'ref'],
        ['key' => 'description', 'label' => 'Keterangan Transaksi', 'bold' => true],
        ['key' => 'source_funding', 'label' => 'Sumber Dana', 'type' => 'badge'],
        ['key' => 'payment_method', 'label' => 'Metode'],
        ['key' => 'debet', 'label' => 'Penerimaan (Debet)', 'type' => 'currency', 'minimumFractionDigits' => 0],
        ['key' => 'kredit', 'label' => 'Pengeluaran (Kredit)', 'type' => 'currency', 'minimumFractionDigits' => 0],
    ];

    $reportRows = collect($entries)->map(function ($entry) {
        $dateObj = $entry['date'] instanceof \Carbon\CarbonInterface
            ? $entry['date']
            : \Carbon\Carbon::parse($entry['date']);

        return [
            'id' => $entry['id'] ?? uniqid(),
            'date_formatted' => $dateObj->translatedFormat('d M Y'),
            'date_raw' => $dateObj->format('Y-m-d'),
            'ref' => $entry['ref'] ?? '-',
            'description' => $entry['description'] ?? '-',
            'source_funding' => $entry['source_funding'] ?? 'Komite',
            'payment_method' => $entry['payment_method'] ?? 'Tunai',
            'debet' => (float) ($entry['debet'] ?? 0),
            'kredit' => (float) ($entry['kredit'] ?? 0),
            'amount' => (float) ($entry['amount'] ?? 0),
            'only_edit' => false,
        ];
    });

    $fundingSummary = collect($entries)->groupBy('source_funding')->map(function ($group, $funding) {
        $debet = $group->sum('debet');
        $kredit = $group->sum('kredit');
        return [
            'funding' => $funding ?: 'Lainnya',
            'debet' => (float) $debet,
            'kredit' => (float) $kredit,
            'net' => (float) ($debet - $kredit),
        ];
    })->values();
@endphp

<style>
@media print {
    body * {
        visibility: hidden;
    }
    #printable-report, #printable-report * {
        visibility: visible;
    }
    #printable-report {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        padding: 20px;
        background: white !important;
        color: black !important;
    }
    .no-print {
        display: none !important;
    }
}
</style>

<x-common.page-breadcrumb pageTitle="Laporan Keuangan & Pembukuan" label="Laporan" />

<div class="work work-stack" id="printable-report">

    {{-- TABS LAPORAN: KOMITE vs BOS vs SEMUA --}}
    <div class="no-print flex items-center gap-2 border-b border-gray-200 dark:border-gray-800 pb-2 mb-3 overflow-x-auto">
        <a href="{{ route('finance.reports', array_merge(request()->except(['tab', 'page']), ['tab' => 'komite'])) }}"
           class="px-4 py-2 rounded-xl text-xs font-semibold transition-all inline-flex items-center gap-2 {{ ($activeTab ?? 'all') === 'komite' ? 'bg-brand-50 text-brand-600 border border-brand-200 dark:bg-brand-500/10 dark:text-brand-400 dark:border-brand-500/30 shadow-xs' : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white border border-transparent' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            Laporan Komite (Dana Siswa / SPP)
        </a>
        <a href="{{ route('finance.reports', array_merge(request()->except(['tab', 'page']), ['tab' => 'bos'])) }}"
           class="px-4 py-2 rounded-xl text-xs font-semibold transition-all inline-flex items-center gap-2 {{ ($activeTab ?? '') === 'bos' ? 'bg-brand-50 text-brand-600 border border-brand-200 dark:bg-brand-500/10 dark:text-brand-400 dark:border-brand-500/30 shadow-xs' : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white border border-transparent' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"/></svg>
            Laporan BOS (Bantuan Operasional Sekolah)
        </a>
        <a href="{{ route('finance.reports', array_merge(request()->except(['tab', 'page']), ['tab' => 'all'])) }}"
           class="px-4 py-2 rounded-xl text-xs font-semibold transition-all inline-flex items-center gap-2 {{ ($activeTab ?? '') === 'all' ? 'bg-brand-50 text-brand-600 border border-brand-200 dark:bg-brand-500/10 dark:text-brand-400 dark:border-brand-500/30 shadow-xs' : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white border border-transparent' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            Semua Laporan (Konsolidasi)
        </a>
    </div>

    {{-- REPORT HEADER & PRINT ACTION --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-1">
        <div>
            <h2 class="text-base font-bold text-gray-900 dark:text-white">
                @if(($activeTab ?? '') === 'komite')
                    Laporan Keuangan Komite & SPP
                @elseif(($activeTab ?? '') === 'bos')
                    Laporan Keuangan Dana BOS
                @else
                    Laporan Keuangan & Arus Kas Konsolidasi
                @endif
            </h2>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                @if(($activeTab ?? '') === 'komite')
                    Penerimaan tagihan siswa/SPP, belanja operasional komite, dan saldo kas/bank komite.
                @elseif(($activeTab ?? '') === 'bos')
                    Penerimaan dana BOS via SP2D, realisasi belanja operasional BOS per komponen, dan pajak belanja.
                @else
                    Ringkasan transaksi riil yang telah disetujui, realisasi belanja, dan posisi kas rekening seluruh sumber dana.
                @endif
            </p>
        </div>
        <div class="no-print flex items-center gap-2">
            <button type="button" onclick="window.print()" class="work-btn work-btn-secondary">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                Cetak / Simpan PDF
            </button>
        </div>
    </div>

    {{-- METRICS SUMMARY --}}
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-5 mb-2">
        @foreach($metrics as $metric)
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-xs dark:border-gray-800 dark:bg-gray-900">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $metric['label'] }}</p>
                <p class="mt-1 text-xl font-bold tracking-tight tabular-nums text-gray-900 dark:text-white">{{ $metric['value'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- FILTER TOOLBAR (NO PRINT) --}}
    <div class="no-print rounded-xl border border-gray-200 bg-white p-4 shadow-xs dark:border-gray-800 dark:bg-gray-900 mb-2">
        <form method="GET" action="{{ route('finance.reports') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end">
            <input type="hidden" name="tab" value="{{ $activeTab }}">
            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Dari Tanggal</label>
                <input type="date" name="from" value="{{ request('from') }}"
                    class="h-9 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-1.5 text-xs text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-brand-500">
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Sampai Tanggal</label>
                <input type="date" name="to" value="{{ request('to') }}"
                    class="h-9 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-1.5 text-xs text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-brand-500">
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Sumber Dana</label>
                <select name="source_funding" class="h-9 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-1.5 text-xs text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-brand-500">
                    <option value="">Semua Sumber Dana</option>
                    <option value="Komite" @selected(request('source_funding') === 'Komite')>Komite / SPP</option>
                    <option value="BOS" @selected(request('source_funding') === 'BOS')>BOS</option>
                    <option value="Yayasan" @selected(request('source_funding') === 'Yayasan')>Yayasan</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Rekening Kas/Bank</label>
                <select name="account_id" class="h-9 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-1.5 text-xs text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-brand-500">
                    <option value="">Semua Rekening</option>
                    @foreach($accounts as $acc)
                        <option value="{{ $acc->id }}" @selected(request('account_id') == $acc->id)>{{ $acc->name }} ({{ $acc->type }})</option>
                    @endforeach
                </select>
            </div>

            <div class="flex gap-2">
                <button type="submit" class="work-btn work-btn-primary flex-1 justify-center">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                    Filter Laporan
                </button>
                @if(request()->hasAny(['from', 'to', 'source_funding', 'account_id']))
                    <a href="{{ route('finance.reports') }}" class="work-btn work-btn-secondary text-gray-500 px-3">
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- REKAPITULASI DUA KOLOM: SUMBER DANA & REKENING --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-2">
        {{-- Card 1: Rekap per Sumber Dana --}}
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-xs dark:border-gray-800 dark:bg-gray-900">
            <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-3">
                Rekapitulasi Arus Kas per Sumber Dana
            </h3>
            <div class="overflow-hidden rounded-lg border border-gray-100 dark:border-gray-800">
                <table class="w-full text-xs text-left">
                    <thead class="bg-gray-50 dark:bg-gray-800/50 border-b border-gray-100 dark:border-gray-800 text-gray-500">
                        <tr>
                            <th class="px-3 py-2 font-medium">Sumber Dana</th>
                            <th class="px-3 py-2 font-medium text-right">Penerimaan</th>
                            <th class="px-3 py-2 font-medium text-right">Pengeluaran</th>
                            <th class="px-3 py-2 font-medium text-right">Net</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($fundingSummary as $fs)
                            <tr>
                                <td class="px-3 py-2.5 font-semibold text-gray-900 dark:text-white">{{ $fs['funding'] }}</td>
                                <td class="px-3 py-2.5 text-right font-mono text-emerald-600 dark:text-emerald-400">Rp {{ number_format($fs['debet'], 0, ',', '.') }}</td>
                                <td class="px-3 py-2.5 text-right font-mono text-rose-600 dark:text-rose-400">Rp {{ number_format($fs['kredit'], 0, ',', '.') }}</td>
                                <td class="px-3 py-2.5 text-right font-mono font-bold {{ $fs['net'] >= 0 ? 'text-gray-900 dark:text-white' : 'text-rose-600' }}">
                                    Rp {{ number_format($fs['net'], 0, ',', '.') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-3 py-4 text-center text-gray-400">Belum ada data transaksi approved.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Card 2: Saldo Rekening Saat Ini --}}
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-xs dark:border-gray-800 dark:bg-gray-900">
            <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-3">
                Posisi Saldo Kas & Rekening Bank
            </h3>
            <div class="overflow-hidden rounded-lg border border-gray-100 dark:border-gray-800">
                <table class="w-full text-xs text-left">
                    <thead class="bg-gray-50 dark:bg-gray-800/50 border-b border-gray-100 dark:border-gray-800 text-gray-500">
                        <tr>
                            <th class="px-3 py-2 font-medium">Nama Rekening</th>
                            <th class="px-3 py-2 font-medium">Tipe</th>
                            <th class="px-3 py-2 font-medium text-right">Saldo Saat Ini</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($accounts as $acc)
                            <tr>
                                <td class="px-3 py-2.5 font-medium text-gray-900 dark:text-white">
                                    {{ $acc->name }}
                                    @if($acc->bank_name)
                                        <span class="block text-[10px] text-gray-400 font-mono">{{ $acc->bank_name }} - {{ $acc->account_number }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-gray-500">{{ $acc->type }}</td>
                                <td class="px-3 py-2.5 text-right font-mono font-bold text-gray-900 dark:text-white">
                                    Rp {{ number_format((float) $acc->current_balance, 0, ',', '.') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-3 py-4 text-center text-gray-400">Belum ada data rekening sekolah.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- DATA TABLE: BUKU JURNAL TRANSAKSI --}}
    <x-common.data-table
        :rows="$reportRows"
        :columns="$reportColumns"
        caption="Buku Jurnal & Rincian Transaksi Keuangan"
        search-label="Cari referensi, keterangan, atau sumber dana..."
        row-label="transaksi"
        subtitle="Rincian seluruh mutasi keuangan yang telah diverifikasi dan disetujui"
        :show-actions="false"
        :show-avatar="false"
        :exportable="true"
        export-label="Export Jurnal (CSV)"
        empty-message="Belum ada transaksi pada periode yang dipilih."
        empty-hint="Sesuaikan filter rentang tanggal atau sumber dana untuk melihat riwayat jurnal."
    />
</div>
@endsection
