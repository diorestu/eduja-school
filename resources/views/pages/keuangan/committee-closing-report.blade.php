<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $reportTitle }} | EDUJA</title>
    <style>
        *{box-sizing:border-box}body{margin:0;background:#f3f4f6;color:#172033;font:14px/1.6 system-ui,sans-serif}.toolbar{padding:16px;display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap}button{padding:10px 16px;border:1px solid #cbd5e1;border-radius:6px;background:white;color:#172033;cursor:pointer}button:focus-visible{outline:3px solid #2563eb;outline-offset:2px}main{max-width:1100px;margin:0 auto 24px;background:white;padding:32px}h1{font-size:22px;line-height:1.4;margin:0}h2{font-size:17px;margin-top:24px}.muted{color:#475569}.status{font-weight:600;color:#1d4ed8}.scroll{overflow-x:auto}table{width:100%;border-collapse:collapse;font-size:12px}th,td{padding:10px;border:1px solid #d1d5db;text-align:left;vertical-align:top}th{background:#f1f5f9}.number{text-align:right;white-space:nowrap}dl{display:grid;grid-template-columns:minmax(110px,180px) 1fr;gap:6px}dt{font-weight:600}dd{margin:0;overflow-wrap:anywhere}header{border-bottom:2px solid #0f766e;padding-bottom:18px;margin-bottom:24px}@media(max-width:600px){main{padding:16px}h1{font-size:18px}dl{grid-template-columns:1fr}dd{margin-bottom:8px}}@media print{@page{size:A4 landscape;margin:12mm}body{background:white}.toolbar{display:none}main{max-width:none;margin:0;padding:0}.scroll{overflow:visible}thead{display:table-header-group}tr{break-inside:avoid}th{print-color-adjust:exact}}
    </style>
</head>
<body>
<div class="toolbar"><span>{{ $closing ? 'Laporan dari snapshot tersimpan' : 'Pratinjau sebelum penutupan' }}</span><button type="button" onclick="window.print()">Cetak / Simpan PDF</button></div>
<main>
    <header><p><strong>EDUJA · {{ $schoolName }}</strong></p><h1>{{ $reportTitle }} Komite (Non-BOS)</h1><p class="muted">Periode {{ $package['start'] }} s.d. {{ $package['end'] }}</p><p class="status">{{ $closing ? ($closing->status === 'closed' ? 'Periode ditutup' : 'Periode dibuka kembali; snapshot penutupan terdahulu') : 'Draf, periode belum ditutup' }}</p></header>
    @php
        $money = fn($value) => 'Rp '.number_format($value, 2, ',', '.');
        $entries = collect($package['entries']);
    @endphp
    @if(in_array($report, ['budget', 'category']))
        <p class="muted">Pagu merupakan anggaran tahunan yang disetujui pada tahun anggaran terkait. Realisasi hanya mencakup pengeluaran dalam periode ini, sehingga selisih bukan sisa anggaran tahunan.</p>
        <div class="scroll"><table><thead><tr><th>Pos Anggaran</th><th class="number">Pagu Tahunan</th><th class="number">Realisasi Periode</th><th class="number">Pagu - Realisasi Periode</th><th class="number">Persentase</th></tr></thead><tbody>
            @forelse($package['budgets'] as $budget)<tr><td>{{ $budget['name'] }}</td><td class="number">{{ $money($budget['budget']) }}</td><td class="number">{{ $money($budget['spent']) }}</td><td class="number">{{ $money($budget['budget'] - $budget['spent']) }}</td><td class="number">{{ $budget['budget'] > 0 ? number_format($budget['spent'] / $budget['budget'] * 100, 1, ',', '.').'%' : '-' }}</td></tr>@empty<tr><td colspan="5">Tidak ada anggaran Non-BOS yang disetujui pada tahun anggaran terkait.</td></tr>@endforelse
            @php($unallocated = $package['summary']['spent'] - collect($package['budgets'])->sum('spent'))
            @if($unallocated != 0)<tr><td>Pengeluaran di luar pos anggaran terkait</td><td class="number">-</td><td class="number">{{ $money($unallocated) }}</td><td class="number">-</td><td class="number">-</td></tr>@endif
        </tbody><tfoot><tr><th>Total</th><th class="number">{{ $money($package['summary']['budget']) }}</th><th class="number">{{ $money($package['summary']['spent']) }}</th><th class="number">{{ $money($package['summary']['budget'] - $package['summary']['spent']) }}</th><th></th></tr></tfoot></table></div>
        @if($report === 'category')
            <h2>Rincian Pengeluaran per Pos</h2>
            @foreach($entries->where('type', 'expense')->groupBy(fn($entry) => $entry['budget_plan_id'] ?? 'unallocated') as $planId => $group)
                <h3>{{ collect($package['budgets'])->firstWhere('id', $planId)['name'] ?? 'Di luar pos anggaran terkait' }}</h3>
                <ul>@foreach($group as $entry)<li>{{ $entry['date'] }} · {{ $entry['ref'] }} · {{ $entry['description'] }} · {{ $money($entry['kredit']) }}</li>@endforeach</ul>
            @endforeach
        @endif
    @elseif($report === 'cash')
        <p class="muted">Posisi rekening fisik sekolah pada akhir periode, termasuk rekening bersama BOS. Saldo dompet merupakan alokasi, tidak ditambahkan sebagai uang baru pada total kas/bank.</p>
        @foreach(['accounts' => 'Kas dan Rekening Bank Sekolah', 'wallets' => 'Alokasi Dompet Sekolah'] as $key => $label)
            <h2>{{ $label }}</h2><div class="scroll"><table><thead><tr><th>Nama</th><th class="number">Saldo Akhir</th></tr></thead><tbody>@forelse($package[$key] as $account)<tr><td>{{ $account['name'] }}</td><td class="number">{{ $money($account['balance']) }}</td></tr>@empty<tr><td colspan="2">Belum ada data.</td></tr>@endforelse</tbody></table></div>
        @endforeach
        <p><strong>Total kas/bank: {{ $money($package['summary']['account_balance']) }}</strong></p>
    @elseif(in_array($report, ['income', 'expense', 'ledger']))
        @php($rows = $entries->filter(fn($entry) => $report === 'ledger' || ($entry['type'] !== 'transfer' && ($report === 'income' ? $entry['debet'] > 0 : $entry['kredit'] > 0))))
        <div class="scroll"><table><thead><tr><th>Tanggal</th><th>Referensi</th><th>Uraian</th><th>Sumber Dana</th><th class="number">Masuk</th><th class="number">Keluar</th></tr></thead><tbody>
            @forelse($rows as $entry)<tr><td>{{ $entry['date'] }}</td><td>{{ $entry['ref'] }}</td><td>{{ $entry['description'] }}</td><td>{{ $entry['source_funding'] }}</td><td class="number">{{ $money($entry['debet']) }}</td><td class="number">{{ $money($entry['kredit']) }}</td></tr>@empty<tr><td colspan="6">Tidak ada transaksi pada periode ini.</td></tr>@endforelse
        </tbody><tfoot><tr><th colspan="4">Total mutasi</th><th class="number">{{ $money($rows->sum('debet')) }}</th><th class="number">{{ $money($rows->sum('kredit')) }}</th></tr></tfoot></table></div>
        @if($report === 'ledger')<p class="muted">Transfer internal ditampilkan sebagai mutasi antar rekening dan tidak dihitung sebagai pemasukan/pengeluaran pada ringkasan periode.</p>@endif
    @elseif($report === 'minutes')
        <p>{{ $closing ? 'Penutupan buku Komite telah dicatat dengan rincian berikut.' : 'Draf berita acara. Dokumen ini belum membuktikan penutupan periode.' }}</p>
        <dl><dt>Periode</dt><dd>{{ $package['start'] }} s.d. {{ $package['end'] }}</dd><dt>Pemasukan</dt><dd>{{ $money($package['summary']['collected']) }}</dd><dt>Pengeluaran</dt><dd>{{ $money($package['summary']['spent']) }}</dd><dt>Ditutup oleh</dt><dd>{{ $closing?->closedBy?->name ?? 'Belum ditutup' }}</dd><dt>Waktu penutupan</dt><dd>{{ $closing?->closed_at?->format('d/m/Y H:i:s') ?? '-' }}</dd><dt>Saldo awal berikutnya</dt><dd>{{ $package['next_opening']['date'] ?? \Carbon\Carbon::parse($package['end'])->addDay()->toDateString() }}</dd></dl>
        <p>Penutupan mengunci transaksi Komite dalam periode dan menyimpan saldo awal berikutnya tanpa menggandakan saldo kas/bank maupun dompet.</p>
        @if(!$package['validation']['all_passed'])<h2>Masalah yang Belum Diselesaikan</h2><ul>@foreach($package['validation']['error_messages'] as $message)<li>{{ $message }}</li>@endforeach</ul>@endif
    @elseif($report === 'audit')
        <dl><dt>ID Penutupan</dt><dd>{{ $closing?->id ?? 'Belum ditutup' }}</dd><dt>Status</dt><dd>{{ $closing?->status ?? 'Pratinjau' }}</dd><dt>Pelaku Penutupan</dt><dd>{{ $closing?->closedBy?->name ?? '-' }}</dd><dt>Waktu</dt><dd>{{ $closing?->closed_at?->format('d/m/Y H:i:s') ?? '-' }}</dd><dt>Sidik Data</dt><dd>{{ $package['fingerprint'] }}</dd></dl>
        @if($closing && isset($closing->audit_metadata['reopened_at']))<h2>Pembukaan Kembali</h2><dl><dt>Waktu</dt><dd>{{ $closing->audit_metadata['reopened_at'] }}</dd><dt>ID Pengguna</dt><dd>{{ $closing->audit_metadata['reopened_by'] }}</dd><dt>Alasan</dt><dd>{{ $closing->audit_metadata['reopen_reason'] }}</dd></dl>@endif
        <h2>Hasil Pemeriksaan saat {{ $closing ? 'Penutupan' : 'Pratinjau' }}</h2>
        <p>{{ $package['validation']['all_passed'] ? 'Seluruh pemeriksaan lulus.' : 'Masih ada masalah yang harus diselesaikan.' }}</p><ul>@foreach($package['validation']['error_messages'] as $message)<li>{{ $message }}</li>@endforeach</ul>
        <p class="muted">Audit ini memuat jejak pemeriksaan dan penutupan yang tersimpan, bukan riwayat seluruh perubahan transaksi.</p>
    @endif
</main>
</body>
</html>
