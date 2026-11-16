<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $report === 'receipt' ? 'Bukti Penutupan' : 'Laporan Keuangan' }} BOS | EDUJA</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#f3f4f6;color:#172033;font:14px/1.6 system-ui,sans-serif}.toolbar{padding:16px;display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap}button{padding:10px 16px;border:1px solid #cbd5e1;border-radius:6px;background:white;color:#172033;cursor:pointer}button:focus-visible{outline:3px solid #2563eb;outline-offset:2px}main{max-width:1100px;margin:0 auto 24px;background:white;padding:32px}h1{font-size:22px;line-height:1.4}h2{font-size:17px;margin-top:24px}.muted{color:#475569}.scroll{overflow-x:auto}table{width:100%;border-collapse:collapse;font-size:12px}th,td{padding:9px;border:1px solid #d1d5db;text-align:left;vertical-align:top}th{background:#f1f5f9}.number{text-align:right;white-space:nowrap}dl{display:grid;grid-template-columns:minmax(110px,200px) 1fr;gap:8px}dt{font-weight:600}dd{margin:0;overflow-wrap:anywhere}header{border-bottom:2px solid #0f766e;padding-bottom:18px;margin-bottom:24px}@media(max-width:600px){main{padding:16px}h1{font-size:18px}dl{grid-template-columns:1fr}dd{margin-bottom:8px}}@media print{@page{size:A4 landscape;margin:12mm}body{background:white}.toolbar{display:none}main{max-width:none;margin:0;padding:0}.scroll{overflow:visible}thead{display:table-header-group}tr{break-inside:avoid}th{print-color-adjust:exact}}
</style>
</head>
<body>
@php($money = fn($value) => 'Rp '.number_format($value, 2, ',', '.'))
<div class="toolbar"><span>{{ $closing ? 'Snapshot penutupan tersimpan' : 'Pratinjau, belum ditutup' }}</span><button type="button" onclick="window.print()">Cetak / Simpan PDF</button></div>
<main>
<header><p><strong>EDUJA · {{ $schoolName }}</strong></p><h1>{{ $report === 'receipt' ? 'Bukti Penutupan Buku' : 'Laporan Keuangan' }} BOS</h1><p>{{ $package['source'] }} · Tahun BOS {{ $package['year'] }}</p><p class="muted">Periode: 1 Januari - 31 Desember {{ $package['year'] }}. Transaksi mengikuti tahun BOS yang tercatat, termasuk yang dibukukan di luar tahun kalender tersebut.</p><strong>{{ $closing ? ($closing->status === 'closed' ? 'Ditutup' : 'Dibuka kembali; snapshot penutupan terdahulu') : 'Draf sebelum penutupan' }}</strong></header>
<dl><dt>Pagu disetujui</dt><dd>{{ $money($package['summary']['budget']) }}</dd><dt>Total Pemasukan</dt><dd>{{ $money($package['summary']['collected']) }}</dd><dt>Total Pengeluaran</dt><dd>{{ $money($package['summary']['spent']) }}</dd><dt>Saldo Kas/Bank BOS Fisik</dt><dd>{{ $money($package['summary']['account_balance']) }}</dd><dt>Nomor Referensi</dt><dd>{{ $closing?->audit_metadata['reference'] ?? 'Belum ditutup' }}</dd><dt>Ditutup Oleh</dt><dd>{{ $closing?->closedBy?->name ?? '-' }}</dd><dt>Tanggal Penutupan</dt><dd>{{ $closing?->closed_at?->format('d/m/Y H:i:s') ?? '-' }}</dd><dt>Catatan</dt><dd>{{ $closing?->audit_metadata['notes'] ?? '-' }}</dd><dt>Saldo Awal Berikutnya</dt><dd>{{ $package['next_opening']['date'] ?? ($package['year'] + 1).'-01-01' }}</dd></dl>
<h2>Hasil Pemeriksaan</h2>
<div class="scroll"><table><thead><tr><th>Pemeriksaan</th><th>Hasil</th></tr></thead><tbody>@foreach($checks + ['ambiguous_years' => 'Kelengkapan Tahun BOS', 'missing_documents' => 'Ketersediaan Bukti Transaksi'] as $key => $label)<tr><td>{{ $label }}</td><td>{{ $package['validation'][$key] === 0 ? 'Valid' : $package['validation'][$key].' item perlu diperiksa' }}</td></tr>@endforeach</tbody></table></div>
@if($package['validation']['error_messages'])<ul>@foreach($package['validation']['error_messages'] as $message)<li>{{ $message }}</li>@endforeach</ul>@endif
<h2>Saldo Akhir Rekening dan Dompet</h2><p class="muted">Saldo fisik per 31 Desember dapat dipakai bersama jenis/tahun BOS lainnya. Saldo dompet merupakan alokasi dan tidak ditambahkan ke kas/bank. Saldo awal berikutnya dicatat tanpa menambah kembali uang rekening/dompet.</p>
<div class="scroll"><table><thead><tr><th>Jenis</th><th>Nama</th><th class="number">Saldo Akhir</th></tr></thead><tbody>@if(empty($package['accounts']) && empty($package['wallets']))<tr><td colspan="3">Belum ada rekening atau dompet BOS.</td></tr>@endif
@foreach(['accounts' => 'Kas/Bank', 'wallets' => 'Alokasi Dompet'] as $key => $label)@foreach($package[$key] as $item)<tr><td>{{ $label }}</td><td>{{ $item['name'] }}</td><td class="number">{{ $money($item['balance']) }}</td></tr>@endforeach
@endforeach</tbody></table></div>
@if($report === 'financial')
<h2>Realisasi per Komponen</h2><div class="scroll"><table><thead><tr><th>Komponen</th><th class="number">Realisasi</th></tr></thead><tbody>@forelse($package['categories'] as $category)<tr><td>{{ $category['name'] }}</td><td class="number">{{ $money($category['amount']) }}</td></tr>@empty<tr><td colspan="2">Belum ada pengeluaran yang diposting.</td></tr>@endforelse</tbody></table></div>
<h2>Rincian Transaksi {{ $package['source'] }} Tahun {{ $package['year'] }}</h2><div class="scroll"><table><thead><tr><th>Tanggal Pembukuan</th><th>Referensi</th><th>Uraian</th><th class="number">Masuk</th><th class="number">Keluar</th><th class="number">Pajak</th></tr></thead><tbody>@forelse($package['entries'] as $entry)<tr><td>{{ $entry['date'] }}</td><td>{{ $entry['ref'] }}</td><td>{{ $entry['description'] }}</td><td class="number">{{ $money($entry['debet']) }}</td><td class="number">{{ $money($entry['kredit']) }}</td><td class="number">{{ $money($entry['tax_amount']) }}</td></tr>@empty<tr><td colspan="6">Tidak ada transaksi pada tahun dan sumber ini.</td></tr>@endforelse</tbody></table></div>
@endif
@if($closing && isset($closing->audit_metadata['reopened_at']))<h2>Pembukaan Kembali</h2><dl><dt>Waktu</dt><dd>{{ $closing->audit_metadata['reopened_at'] }}</dd><dt>ID Pengguna</dt><dd>{{ $closing->audit_metadata['reopened_by'] }}</dd><dt>Alasan</dt><dd>{{ $closing->audit_metadata['reopen_reason'] }}</dd></dl>@endif
<p class="muted">Sidik data pemeriksaan: {{ $package['fingerprint'] }}.</p>
</main>
</body>
</html>
