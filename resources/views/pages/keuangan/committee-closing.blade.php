@extends('layouts.app')

@section('content')
<div class="work space-y-5" x-data="{ type: @js($type), reviewed: false, saving: false, dirty: false, reportUrl: '' }">
    <nav class="flex flex-wrap gap-2" aria-label="Jenis buku">
        <a class="work-btn work-btn-primary" href="{{ route('finance.closing', ['tab' => 'komite']) }}" aria-current="page">Tutup Buku Komite</a>
        <a class="work-btn" href="{{ route('finance.closing', ['tab' => 'bos']) }}">Tutup Buku BOS</a>
        <a class="work-btn" href="#closing-history">Riwayat Tutup Buku</a>
    </nav>
    @if(session('success'))<div role="status" class="work-notice">{{ session('success') }}</div>@endif
    @if($errors->any())
        <div role="alert" class="rounded-xl border border-red-300 bg-red-50 p-4 text-sm text-red-800 dark:bg-red-950 dark:text-red-200">
            <ul class="list-disc pl-4">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    <ol class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5" aria-label="Alur tutup buku Komite">
        @foreach(['Pilih Jenis Tutup Buku' => 'Bulanan atau tahunan sesuai tahun anggaran sekolah.', 'Pilih Periode' => 'Tentukan batas transaksi yang akan ditutup.', 'Pemeriksaan Data' => 'Periksa approval, saldo, posting, dan bukti transaksi.', 'Tinjau Laporan' => 'Periksa paket laporan dari data periode terpilih.', 'Konfirmasi Tutup Buku' => 'Kunci transaksi dan simpan saldo awal berikutnya.'] as $step => $help)
            <li class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <span class="mb-3 inline-flex h-8 w-8 items-center justify-center rounded-full bg-brand-50 font-semibold text-brand-700 dark:bg-brand-950 dark:text-brand-300">{{ $loop->iteration }}</span>
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ $step }}</h2>
                <p class="mt-1 text-xs leading-relaxed text-gray-600 dark:text-gray-400">{{ $help }}</p>
            </li>
        @endforeach
    </ol>
    <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900" aria-labelledby="committee-closing-title">
        <h2 id="committee-closing-title" class="text-lg font-semibold text-gray-900 dark:text-white">Tutup Buku Komite (Non-BOS)</h2>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Periksa periode, tinjau laporan, lalu konfirmasikan penguncian transaksi.</p>
        <div class="mt-5 grid gap-6 lg:grid-cols-3">
            <form method="GET" action="{{ route('finance.closing') }}" class="space-y-4" @submit="saving = true" @change="dirty = true">
                <input type="hidden" name="tab" value="komite"><input type="hidden" name="check" value="1">
                <fieldset>
                    <legend class="mb-2 text-sm font-semibold">Jenis Periode</legend>
                    <div class="flex flex-wrap gap-4 text-sm">
                        <label class="inline-flex min-h-10 items-center gap-2"><input type="radio" name="type" value="monthly" x-model="type"> Bulanan</label>
                        <label class="inline-flex min-h-10 items-center gap-2"><input type="radio" name="type" value="yearly" x-model="type"> Tahunan</label>
                    </div>
                </fieldset>
                <div class="work-field" x-show="type === 'monthly'">
                    <label for="committee-month">Bulan dan Tahun</label>
                    <input id="committee-month" name="period" type="month" value="{{ $type === 'monthly' ? $period : now()->format('Y-m') }}" :disabled="type !== 'monthly'" :required="type === 'monthly'">
                </div>
                <div class="work-field" x-show="type === 'yearly'" x-cloak>
                    <label for="committee-year">Tahun Anggaran</label>
                    <select id="committee-year" name="period" :disabled="type !== 'yearly'" :required="type === 'yearly'">
                        <option value="">Pilih tahun anggaran</option>
                        @foreach($years as $year)<option value="{{ $year->id }}" @selected($type === 'yearly' && (string) $year->id === $period)>{{ $year->name }} ({{ $year->start_date->format('d/m/Y') }} - {{ $year->end_date->format('d/m/Y') }})</option>@endforeach
                    </select>
                    @if($years->isEmpty())<p class="work-muted text-xs">Belum ada tahun anggaran sekolah. Lengkapi master tahun anggaran dahulu.</p>@endif
                </div>
                <button class="work-btn work-btn-primary w-full" type="submit" :disabled="saving" x-text="saving ? 'Memeriksa…' : 'Cek Kesiapan'">Cek Kesiapan</button>
                <p class="text-xs leading-relaxed text-gray-600 dark:text-gray-400">Bila pilihan periode berubah, klik Cek Kesiapan kembali untuk memperbarui hasil dan laporan.</p>
            </form>
            <div class="lg:col-span-2">
                @if(!$preview)
                    <div class="rounded-lg border border-dashed border-gray-300 p-6 dark:border-gray-700">
                        <h3 class="text-sm font-semibold">Belum diperiksa</h3>
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Pilih periode lalu klik Cek Kesiapan. Ringkasan, paket laporan, dan konfirmasi akan tersedia setelah pemeriksaan.</p>
                    </div>
                @else
                    <h3 class="text-sm font-semibold">Hasil Pemeriksaan: {{ \Carbon\Carbon::parse($preview['start'])->locale('id')->translatedFormat('d F Y') }} - {{ \Carbon\Carbon::parse($preview['end'])->locale('id')->translatedFormat('d F Y') }}</h3>
                    <dl class="mt-3 grid gap-2 sm:grid-cols-2">
                        @foreach(['pending_approvals' => 'Persetujuan transaksi', 'negative_accounts' => 'Saldo rekening dan dompet sekolah', 'failed_journals' => 'Posting transaksi', 'incomplete_transfers' => 'Verifikasi pembayaran transfer', 'pending_expenses' => 'Transaksi pemasukan dan pengeluaran', 'unpaid_taxes' => 'Setoran pajak', 'missing_documents' => 'Bukti transaksi', 'account_reconciliation' => 'Kesesuaian rekening sekolah'] as $key => $label)
                            <div class="flex items-center justify-between gap-3 rounded-lg bg-gray-50 p-3 text-xs dark:bg-gray-800">
                                <dt>{{ $label }}</dt><dd class="shrink-0 font-semibold {{ $preview['validation'][$key] === 0 ? 'text-emerald-700 dark:text-emerald-300' : 'text-red-700 dark:text-red-300' }}">{{ $preview['validation'][$key] === 0 ? 'Valid' : $preview['validation'][$key].' perlu diperiksa' }}</dd>
                            </div>
                        @endforeach
                    </dl>
                    <div class="mt-3 rounded-lg border p-3 text-sm {{ $preview['validation']['all_passed'] ? 'border-emerald-300 text-emerald-800 dark:text-emerald-200' : 'border-amber-300 text-amber-900 dark:text-amber-200' }}" role="status">
                        @if($preview['validation']['all_passed'])
                            Persyaratan terpenuhi. Tinjau paket laporan sebelum menutup periode.
                        @else
                            <p class="font-semibold">Periode belum dapat ditutup.</p>
                            <ul class="mt-2 list-disc pl-4">@foreach($preview['validation']['error_messages'] as $message)<li>{{ $message }}</li>@endforeach</ul>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </section>
    @if($preview)
        <section aria-label="Ringkasan periode yang diperiksa" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach(['collected' => 'Total Pemasukan Non-BOS', 'spent' => 'Total Pengeluaran Non-BOS', 'account_balance' => 'Saldo Kas/Bank Sekolah pada Akhir Periode', 'budget' => 'Pagu Anggaran Tahunan Terkait'] as $key => $label)
                <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900"><p class="text-xs text-gray-600 dark:text-gray-400">{{ $label }}</p><p class="mt-2 text-xl font-semibold tabular-nums">Rp {{ number_format($preview['summary'][$key], 0, ',', '.') }}</p></div>
            @endforeach
        </section>
        <section id="closing-reports" class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900" aria-labelledby="reports-title">
            <h2 id="reports-title" class="text-lg font-semibold">Paket Laporan Tutup Buku Komite</h2>
            <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">Pratinjau periode {{ $preview['start'] }} s.d. {{ $preview['end'] }}. Posisi kas menunjukkan rekening fisik sekolah yang dapat dipakai bersama dengan BOS; saldo dompet tidak ditambahkan ke kas/bank.</p>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @foreach($reports as $key => $label)
                    <a class="work-btn min-h-14 justify-between text-left" href="{{ route('finance.closing.committee.report', ['type' => $preview['type'], 'period' => $preview['period'], 'report' => $key]) }}" target="_blank" rel="noopener" @click.prevent="reportUrl = $el.href; reviewed = true">{{ $label }} <span class="text-xs">Lihat</span></a>
                @endforeach
            </div>
            <p class="mt-3 text-xs text-gray-600 dark:text-gray-400">Pilih laporan untuk pratinjau di bawah. Gunakan Cetak / Simpan PDF pada halaman laporan.</p>
            <div class="mt-4" x-show="reportUrl" x-cloak><iframe :src="reportUrl || 'about:blank'" title="Pratinjau laporan tutup buku Komite" class="h-[560px] w-full rounded-lg border border-gray-200 bg-white"></iframe></div>
        </section>
        @if($preview['validation']['all_passed'])
            <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900" aria-labelledby="confirm-title">
                <h2 id="confirm-title" class="text-lg font-semibold">Konfirmasi Tutup Buku</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Transaksi Komite pada periode {{ $preview['start'] }} s.d. {{ $preview['end'] }} akan dikunci. Saldo awal berikutnya disimpan tanpa menambah ulang saldo rekening atau dompet.</p>
                <form method="POST" action="{{ route('finance.closing.store') }}" class="mt-4 space-y-4" @submit="saving = true">
                    @csrf
                    <input type="hidden" name="type" value="{{ $preview['type'] }}"><input type="hidden" name="period" value="{{ $preview['period'] }}"><input type="hidden" name="fingerprint" value="{{ $preview['fingerprint'] }}">
                    <label class="flex items-start gap-3 text-sm"><input class="mt-1" type="checkbox" name="confirmed" value="1" required :disabled="!reviewed || dirty || type !== @js($preview['type'])"> Saya telah meninjau paket laporan dan menyetujui penutupan periode ini.</label>
                    <p x-show="!reviewed" class="text-xs text-gray-600 dark:text-gray-400">Pilih laporan untuk mengaktifkan konfirmasi. Jika periode diubah, jalankan Cek Kesiapan kembali.</p>
                    <button type="submit" class="work-btn work-btn-primary" :disabled="!reviewed || dirty || saving || type !== @js($preview['type'])" x-text="saving ? 'Menutup periode…' : 'Kunci & Tutup Buku'">Kunci &amp; Tutup Buku</button>
                </form>
            </section>
        @endif
    @endif
    <section id="closing-history" class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900" aria-labelledby="history-title">
        <h2 id="history-title" class="text-lg font-semibold">Riwayat Tutup Buku Komite</h2>
        @forelse($closings as $closing)
            <details class="mt-3 rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                <summary class="cursor-pointer text-sm font-semibold">{{ $closing->period }} · {{ $closing->type === 'yearly' ? 'Tahunan' : 'Bulanan' }} · {{ $closing->status === 'closed' ? 'Ditutup' : 'Dibuka Kembali' }} · {{ $closing->closedBy?->name }} · {{ $closing->closed_at?->format('d/m/Y H:i') }}</summary>
                <p class="mt-3 text-xs">Saldo awal berikutnya: {{ $closing->snapshot['next_opening']['date'] ?? '-' }}</p>
                <dl class="mt-2 space-y-1 text-sm">@foreach($closing->snapshot['accounts'] ?? [] as $account)<div class="flex flex-wrap justify-between gap-2"><dt>{{ $account['name'] }}</dt><dd>Rp {{ number_format($account['balance'], 0, ',', '.') }}</dd></div>@endforeach</dl>
                @if(($closing->snapshot['book'] ?? null) === 'komite')
                    <div class="mt-3 flex flex-wrap gap-2">@foreach($reports as $key => $label)<a class="work-btn text-xs" href="{{ route('finance.closing.committee.report', ['closing_id' => $closing->id, 'report' => $key]) }}" target="_blank" rel="noopener">{{ $label }}</a>@endforeach</div>
                @else<p class="mt-2 text-xs text-gray-600 dark:text-gray-400">Snapshot lama hanya menyimpan rincian saldo. Paket laporan historis belum tersedia.</p>@endif
                @if($closing->status === 'closed' && auth()->user()?->hasRole(['kepsek', 'super_admin']))
                    <form method="POST" action="{{ route('finance.closing.reopen', $closing) }}" class="mt-4 space-y-3">
                        @csrf
                        <div class="work-field"><label for="reopen-committee-{{ $closing->id }}">Alasan Pembukaan Kembali</label><textarea id="reopen-committee-{{ $closing->id }}" name="reason" required minlength="5" maxlength="1000"></textarea></div>
                        <button type="submit" class="work-btn">Buka Kembali Periode</button>
                    </form>
                @endif
            </details>
        @empty<p class="mt-3 text-sm text-gray-600 dark:text-gray-400">Belum ada periode Komite yang ditutup.</p>@endforelse
    </section>
</div>
@endsection
