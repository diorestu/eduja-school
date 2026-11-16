@extends('layouts.app')

@section('content')
@php
    $money = fn($value) => 'Rp '.number_format($value, 0, ',', '.');
    $package = $preview ?? (($completed?->snapshot['book'] ?? null) === 'bos' ? $completed->snapshot : null);
    $ready = $preview && $preview['validation']['all_passed'];
@endphp
<div class="work space-y-5" x-data="{dirty: false, saving: false, agreed: false, notes: @js((string) old('notes', ''))}">
    <nav class="flex flex-wrap gap-2" aria-label="Jenis tutup buku">
        <a class="work-btn" href="{{ route('finance.closing', ['tab' => 'komite']) }}">Tutup Buku Komite</a>
        <a class="work-btn work-btn-primary" aria-current="page" href="{{ route('finance.closing', ['tab' => 'bos']) }}">Tutup Buku BOS</a>
        <a class="work-btn" href="#bos-history">Riwayat Penutupan</a>
    </nav>
    <header><h1>Tutup Buku BOS</h1><p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Menutup tahun anggaran BOS setelah transaksi valid, laporan ditinjau, dan saldo diperiksa.</p></header>
    @if(session('success'))<div class="work-notice" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="rounded-xl border border-red-300 bg-red-50 p-4 text-sm text-red-800 dark:bg-red-950 dark:text-red-200" role="alert"><ul class="list-disc pl-4">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="grid items-start gap-4 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-5">
        <section class="min-w-0 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900" aria-labelledby="bos-select-title">
            <h2 id="bos-select-title" class="flex items-center gap-3"><span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-700 dark:bg-brand-950 dark:text-brand-300">1</span> Pilih Tahun BOS</h2>
            <form method="GET" action="{{ route('finance.closing') }}" class="mt-4 space-y-4" @change="dirty = true; agreed = false" @submit="saving = true">
                <input type="hidden" name="tab" value="bos"><input type="hidden" name="check" value="1">
                <div class="work-field"><label for="bos-source">Sumber Dana</label><select id="bos-source" name="bos_type" required>@foreach($sources as $item)<option @selected($source === $item)>{{ $item }}</option>@endforeach</select></div>
                <div class="work-field"><label for="bos-year">Tahun BOS</label><select id="bos-year" name="period" required>@foreach($years as $item)<option value="{{ $item }}" @selected($year === $item)>{{ $item }}</option>@endforeach</select></div>
                <div class="rounded-lg bg-gray-50 p-3 text-xs leading-relaxed dark:bg-gray-800"><p class="font-semibold">Informasi Tahun BOS</p><dl class="mt-2 space-y-2"><div><dt class="text-gray-600 dark:text-gray-400">Periode</dt><dd>1 Januari - 31 Desember {{ $year }}</dd></div><div><dt class="text-gray-600 dark:text-gray-400">Sumber</dt><dd>{{ $source }}</dd></div>@if($package)<div><dt class="text-gray-600 dark:text-gray-400">Pagu anggaran disetujui</dt><dd class="font-semibold">{{ $money($package['summary']['budget']) }}</dd></div>@endif</dl></div>
                <p class="text-xs leading-relaxed text-gray-600 dark:text-gray-400">Transaksi dikunci untuk sumber dan tahun BOS terpilih. Pembukaan kembali mengikuti kewenangan kepala sekolah atau super admin yang aktif di sekolah.</p>
                <button type="submit" class="work-btn work-btn-primary w-full" :disabled="saving" x-text="saving ? 'Memeriksa…' : 'Cek Kesiapan'">Cek Kesiapan</button>
            </form>
        </section>
        <section class="min-w-0 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900" aria-labelledby="bos-check-title">
            <h2 id="bos-check-title" class="flex items-center gap-3"><span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-700 dark:bg-brand-950 dark:text-brand-300">2</span> Pemeriksaan Kelayakan</h2>
            @if(!$package)<p class="mt-4 text-sm text-gray-600 dark:text-gray-400">Belum diperiksa. Pilih sumber dan tahun, lalu klik Cek Kesiapan.</p>@else
                <p class="mt-3 text-xs text-gray-600 dark:text-gray-400">{{ $package['source'] }} · Tahun BOS {{ $package['year'] }}</p>
                <ol class="mt-3 space-y-2">@foreach($checks as $key => $label)<li class="flex items-start justify-between gap-2 rounded-lg bg-gray-50 p-3 text-xs dark:bg-gray-800"><span>{{ $loop->iteration }}. {{ $label }}</span><strong class="shrink-0 {{ $package['validation'][$key] === 0 ? 'text-emerald-700 dark:text-emerald-300' : 'text-red-700 dark:text-red-300' }}">{{ $package['validation'][$key] === 0 ? 'Valid' : $package['validation'][$key].' masalah' }}</strong></li>@endforeach</ol>
                <p class="mt-3 text-xs leading-relaxed text-gray-600 dark:text-gray-400">{{ $package['validation']['transaction_count'] }} transaksi diposting. {{ $package['validation']['transfer_count'] }} transfer internal tercatat. Transaksi approved yang belum diposting harus diselesaikan terlebih dahulu.</p>
            @endif
        </section>
        <section class="min-w-0 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900" aria-labelledby="bos-details-title">
            <h2 id="bos-details-title" class="flex items-center gap-3"><span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-700 dark:bg-brand-950 dark:text-brand-300">3</span> Detail Pemeriksaan</h2>
            @if(!$package)<p class="mt-4 text-sm text-gray-600 dark:text-gray-400">Hasil dan rincian masalah muncul setelah pemeriksaan.</p>@else
                <div class="mt-4 rounded-lg border p-3 text-sm {{ $package['validation']['all_passed'] ? 'border-emerald-300 text-emerald-800 dark:text-emerald-200' : 'border-amber-300 text-amber-900 dark:text-amber-200' }}" role="status">
                    @if($package['validation']['all_passed'])<strong>Semua persyaratan terpenuhi.</strong><p class="mt-1">{{ $completed ? 'Hasil saat penutupan tersimpan.' : 'Tahun BOS siap ditutup setelah konfirmasi.' }}</p>
                    @else<strong>Masih perlu tindakan.</strong><ul class="mt-2 list-disc space-y-1 pl-4">@foreach($package['validation']['error_messages'] as $message)<li>{{ $message }}</li>@endforeach</ul>@endif
                </div>
                <dl class="mt-4 space-y-3 text-xs"><div><dt class="font-semibold">Keseimbangan BOS</dt><dd class="mt-1 text-gray-600 dark:text-gray-400">Saldo awal rekening + seluruh mutasi = saldo rekening. Dana tidak wajib habis dibelanjakan.</dd></div><div><dt class="font-semibold">Transfer selesai</dt><dd class="mt-1 text-gray-600 dark:text-gray-400">Transfer internal tersimpan atomik; nominal dan kedua rekening diperiksa.</dd></div><div><dt class="font-semibold">Bukti transaksi</dt><dd class="mt-1 text-gray-600 dark:text-gray-400">{{ $package['validation']['missing_documents'] }} bukti tidak tersedia; {{ $package['validation']['ambiguous_years'] }} transaksi belum memiliki tahun BOS.</dd></div><div><dt class="font-semibold">Lingkup tahun</dt><dd class="mt-1 text-gray-600 dark:text-gray-400">Mengikuti tahun BOS yang tercatat, termasuk penerimaan yang dibukukan pada tahun kalender berbeda.</dd></div></dl>
            @endif
        </section>
        <section class="min-w-0 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900" aria-labelledby="bos-confirm-title">
            <h2 id="bos-confirm-title" class="flex items-center gap-3"><span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-700 dark:bg-brand-950 dark:text-brand-300">4</span> Konfirmasi Tutup Buku</h2>
            @if(!$preview)<p class="mt-4 text-sm text-gray-600 dark:text-gray-400">Jalankan pemeriksaan untuk meninjau ringkasan dan mengonfirmasi.</p>@else
                <p class="mt-4 rounded-lg bg-amber-50 p-3 text-xs leading-relaxed text-amber-900 dark:bg-amber-950 dark:text-amber-200">Pastikan data sudah benar. Setelah ditutup, perubahan transaksi memerlukan pembukaan kembali oleh pengguna berwenang.</p>
                <dl class="mt-4 space-y-3 text-xs">@foreach(['collected' => 'Total Pemasukan', 'spent' => 'Total Pengeluaran', 'account_balance' => 'Saldo Rekening BOS Fisik'] as $key => $label)<div class="flex flex-wrap justify-between gap-2"><dt>{{ $label }}</dt><dd class="font-semibold tabular-nums">{{ $money($preview['summary'][$key]) }}</dd></div>@endforeach</dl>
                <p class="mt-3 text-xs text-gray-600 dark:text-gray-400">Pemasukan/pengeluaran mengikuti sumber dan tahun terpilih. Saldo fisik dapat dipakai bersama jenis/tahun BOS lain.</p>
                <a class="work-btn mt-4 w-full" target="_blank" rel="noopener" href="{{ route('finance.closing.bos.report', ['bos_type' => $preview['source'], 'period' => $preview['year'], 'report' => 'financial']) }}">Tinjau Laporan</a>
                @if($ready)
                    <form method="POST" action="{{ route('finance.closing.store') }}" class="mt-4 space-y-4" @submit="saving = true">
                        @csrf
                        <input type="hidden" name="type" value="bos"><input type="hidden" name="bos_type" value="{{ $preview['source'] }}"><input type="hidden" name="period" value="{{ $preview['year'] }}"><input type="hidden" name="fingerprint" value="{{ $preview['fingerprint'] }}">
                        <label class="flex items-start gap-2 text-xs leading-relaxed"><input type="checkbox" name="confirmed" value="1" x-model="agreed" required :disabled="dirty" class="mt-1"> Saya telah memeriksa data dan laporan, serta menyetujui penutupan {{ $preview['source'] }} tahun {{ $preview['year'] }}.</label>
                        <div class="work-field"><label for="bos-notes">Catatan (Opsional)</label><textarea id="bos-notes" name="notes" maxlength="200" x-model="notes" rows="3"></textarea><p class="text-right text-xs text-gray-600 dark:text-gray-400" x-text="notes.length + '/200'">0/200</p></div>
                        <p x-show="dirty" x-cloak class="text-xs text-amber-800 dark:text-amber-200">Pilihan berubah. Jalankan Cek Kesiapan kembali.</p>
                        <div class="flex flex-wrap gap-2"><a class="work-btn" href="{{ route('finance.closing', ['tab' => 'bos', 'bos_type' => $preview['source'], 'period' => $preview['year']]) }}">Batal</a><button type="submit" class="work-btn work-btn-primary" :disabled="!agreed || dirty || saving" x-text="saving ? 'Menutup…' : 'Tutup Buku'">Tutup Buku</button></div>
                    </form>
                @else<p class="mt-4 text-xs text-amber-900 dark:text-amber-200">Selesaikan masalah pemeriksaan sebelum konfirmasi.</p>@endif
            @endif
        </section>
        <section class="min-w-0 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900" aria-labelledby="bos-success-title">
            <h2 id="bos-success-title" class="flex items-center gap-3"><span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-700 dark:bg-brand-950 dark:text-brand-300">5</span> Hasil Penutupan</h2>
            @if(!$completed)<p class="mt-4 text-sm text-gray-600 dark:text-gray-400">Belum ada penutupan yang dipilih. Bukti, pelaku, tanggal, dan nomor referensi muncul setelah penutupan berhasil.</p>@else
                <p class="mt-4 rounded-lg bg-emerald-50 p-3 text-sm font-semibold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">{{ $completed->period }} · {{ $completed->status === 'closed' ? 'Berhasil ditutup' : 'Sudah dibuka kembali' }}</p>
                <dl class="mt-4 space-y-3 text-xs"><div><dt class="text-gray-600 dark:text-gray-400">Tanggal Penutupan</dt><dd>{{ $completed->closed_at?->format('d/m/Y H:i') }}</dd></div><div><dt class="text-gray-600 dark:text-gray-400">Ditutup Oleh</dt><dd>{{ $completed->closedBy?->name ?? '-' }}</dd></div><div><dt class="text-gray-600 dark:text-gray-400">Nomor Referensi</dt><dd>{{ $completed->audit_metadata['reference'] ?? 'Snapshot lama #'.$completed->id }}</dd></div><div><dt class="text-gray-600 dark:text-gray-400">Catatan</dt><dd>{{ $completed->audit_metadata['notes'] ?? '-' }}</dd></div><div><dt class="text-gray-600 dark:text-gray-400">Saldo Awal Berikutnya</dt><dd>{{ $completed->snapshot['next_opening']['date'] ?? '-' }}</dd></div></dl>
                <p class="mt-4 text-xs leading-relaxed text-gray-600 dark:text-gray-400">Snapshot laporan dan pemeriksaan tersimpan. Saldo awal berikutnya dicatat tanpa menggandakan uang rekening atau dompet.</p>
                @if(($completed->snapshot['book'] ?? null) === 'bos')<div class="mt-4 flex flex-wrap gap-2"><a class="work-btn" target="_blank" rel="noopener" href="{{ route('finance.closing.bos.report', ['closing_id' => $completed->id, 'report' => 'financial']) }}">Lihat Laporan</a><a class="work-btn" target="_blank" rel="noopener" href="{{ route('finance.closing.bos.report', ['closing_id' => $completed->id, 'report' => 'receipt']) }}">Cetak Bukti</a></div>@endif
            @endif
        </section>
    </div>
    @if($package)
        <div class="grid items-start gap-4 xl:grid-cols-3">
            <section class="min-w-0 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900" aria-labelledby="bos-realization-title">
                <h2 id="bos-realization-title">Ringkasan Realisasi {{ $package['source'] }} {{ $package['year'] }}</h2>
                <p class="mt-2 text-xs text-gray-600 dark:text-gray-400">Pagu tahun anggaran terkait: {{ $money($package['summary']['budget']) }}. Realisasi: {{ $money($package['summary']['spent']) }}.</p>
                <p class="mt-2 text-xs text-gray-600 dark:text-gray-400">Pagu mencakup rencana disetujui pada tahun anggaran sekolah yang beririsan. Realisasi mengikuti tahun BOS pada transaksi.</p>
                @forelse($package['categories'] as $category)
                    @php($percentage = $package['summary']['spent'] > 0 ? $category['amount'] / $package['summary']['spent'] * 100 : 0)
                    <div class="mt-4"><div class="flex flex-wrap justify-between gap-2 text-xs"><span>{{ $category['name'] }}</span><strong>{{ $money($category['amount']) }} ({{ number_format($percentage, 1, ',', '.') }}%)</strong></div><div class="mt-2 h-2 overflow-hidden rounded bg-gray-100 dark:bg-gray-800" aria-hidden="true"><div class="h-full bg-brand-600 dark:bg-brand-400" style="width:{{ min(100, max(0, $percentage)) }}%"></div></div></div>
                @empty<p class="mt-4 text-sm text-gray-600 dark:text-gray-400">Belum ada pengeluaran BOS yang diposting pada tahun dan sumber ini.</p>@endforelse
                <dl class="mt-5 border-t border-gray-200 pt-3 text-sm dark:border-gray-800"><dt>Selisih pagu dan realisasi</dt><dd class="mt-1 font-semibold">{{ $money($package['summary']['budget'] - $package['summary']['spent']) }}</dd></dl>
            </section>
            <section class="min-w-0 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900" aria-labelledby="bos-balances-title">
                <h2 id="bos-balances-title">Saldo Fisik Akhir Tahun {{ $package['year'] }}</h2>
                <p class="mt-2 text-xs leading-relaxed text-gray-600 dark:text-gray-400">Saldo per 31 Desember untuk rekening/dompet BOS sekolah yang dapat dipakai bersama jenis dan tahun BOS lain. Alokasi dompet tidak ditambahkan ke total kas/bank. Transfer rekening BOS bersama pada tahun tertutup memerlukan pembukaan kembali.</p>
                <dl class="mt-4 space-y-3 text-sm">@forelse($package['accounts'] as $account)<div class="flex flex-wrap justify-between gap-2"><dt>{{ $account['name'] }}</dt><dd class="font-semibold">{{ $money($account['balance']) }}</dd></div>@empty<div>Belum ada rekening BOS.</div>@endforelse<div class="flex flex-wrap justify-between gap-2 border-t border-gray-200 pt-3 dark:border-gray-800"><dt>Total Kas/Bank</dt><dd class="font-semibold">{{ $money($package['summary']['account_balance']) }}</dd></div></dl>
                <h3 class="mt-5">Alokasi Dompet</h3><dl class="mt-3 space-y-2 text-sm">@forelse($package['wallets'] as $wallet)<div class="flex flex-wrap justify-between gap-2"><dt>{{ $wallet['name'] }}</dt><dd>{{ $money($wallet['balance']) }}</dd></div>@empty<div>Belum ada dompet BOS.</div>@endforelse</dl>
            </section>
            <section id="bos-history" class="min-w-0 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900" aria-labelledby="bos-history-title">
                @include('pages.keuangan.partials.bos-closing-history')
            </section>
        </div>
    @else
        <section id="bos-history" class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900" aria-labelledby="bos-history-title">@include('pages.keuangan.partials.bos-closing-history')</section>
    @endif
</div>
@endsection
