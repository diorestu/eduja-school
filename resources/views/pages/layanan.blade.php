<!DOCTYPE html>
<html lang="id" class="scroll-smooth" x-data>
<head>
    @include('partials.public-head', [
        'title' => 'Layanan Eduja — Modul Digital untuk SMP & SMK',
        'description' => 'Lihat modul Eduja untuk akademik, SPP, BOS, presensi, tabungan siswa, dan dasbor pimpinan SMP dan SMK.',
        'keywords' => 'modul manajemen sekolah, aplikasi SPP, aplikasi dana BOS, presensi digital siswa, tabungan siswa'
    ])
</head>
<body class="antialiased">

    @include('partials.public-nav', ['activePage' => 'layanan'])

    {{-- HERO --}}
    <section class="relative pt-28 pb-16 px-6 text-center overflow-hidden">
        <div class="hero-orb hero-orb-1" style="opacity: 0.6;"></div>
        <div class="max-w-4xl mx-auto relative z-10">
            <div class="section-tag mb-6 inline-flex"><i class="bx bx-grid-alt"></i> Platform Lengkap</div>
            <h1 class="text-4xl sm:text-6xl font-extrabold tracking-tight leading-tight mb-6">
                <span class="gradient-text">Semua Urusan Sekolah</span><br>
                <span class="text-gray-900 dark:text-white">Punya Ruang yang Rapi.</span>
            </h1>
            <p class="text-base sm:text-lg text-gray-500 dark:text-gray-400 max-w-2xl mx-auto leading-relaxed">
                Dari administrasi SMP sampai operasional SMK, setiap modul dirancang untuk membuat kerja harian lebih ringan, data lebih jelas, dan keputusan lebih cepat.
            </p>
        </div>
    </section>

    {{-- MODUL DETAIL --}}

    {{-- 1: SPP --}}
    <section id="spp" class="py-20 px-6 section-divider scroll-reveal">
        <div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
            <div>
                <span class="section-tag mb-5 inline-flex"><i class="bx bx-receipt"></i> Modul 01 — Keuangan SPP</span>
                <h2 class="text-3xl sm:text-4xl font-extrabold text-gray-900 dark:text-white tracking-tight leading-tight mb-5">
                    Tagihan SPP ribuan siswa,<br>selesai dalam satu klik.
                </h2>
                <p class="text-sm sm:text-base text-gray-500 dark:text-gray-400 leading-relaxed mb-6">
                    Tidak ada lagi lembar tagihan manual yang hilang atau rekap tunggakan yang memakan waktu berjam-jam. Kasir SPP Eduja mengotomatiskan seluruh siklus penagihan — dari pembuatan tagihan, penerimaan pembayaran, hingga cetak kuitansi digital.
                </p>
                <ul class="space-y-3 mb-8">
                    @foreach([
                        'Buat tagihan otomatis untuk seluruh siswa per kelas dalam hitungan detik',
                        'Lacak tunggakan per siswa secara real-time tanpa perlu hitung manual',
                        'Cetak kuitansi profesional langsung dari sistem',
                        'Rekap penerimaan SPP per periode, kelas, atau tahun ajaran',
                        'Dukungan pembayaran angsuran untuk siswa dengan cicilan',
                    ] as $feat)
                    <li class="flex items-start gap-3 text-sm text-gray-600 dark:text-gray-300">
                        <span class="w-5 h-5 rounded-full bg-brand-500/15 text-brand-500 flex items-center justify-center mt-0.5 shrink-0"><i class="bx bx-check text-xs font-bold"></i></span>
                        {{ $feat }}
                    </li>
                    @endforeach
                </ul>
                <a href="/contact" class="inline-flex items-center gap-2 rounded-full bg-brand-500 text-white px-7 py-3 text-sm font-bold hover:bg-brand-600 transition-all shadow-md">
                    Demo Modul SPP <i class="bx bx-right-arrow-alt"></i>
                </a>
            </div>
            <div class="glass-card rounded-2xl overflow-hidden">
                <div class="bg-gray-100 dark:bg-white/5 px-4 py-3 flex items-center gap-2 border-b border-gray-200 dark:border-white/5">
                    <span class="w-2.5 h-2.5 rounded-full bg-red-400/80"></span>
                    <span class="w-2.5 h-2.5 rounded-full bg-yellow-400/80"></span>
                    <span class="w-2.5 h-2.5 rounded-full bg-green-400/80"></span>
                    <div class="bg-gray-200 dark:bg-black/30 text-[9px] text-gray-500 px-4 py-0.5 rounded font-mono ml-3">Kasir SPP</div>
                </div>
                <div class="p-5 bg-gray-50 dark:bg-[#0a0d14] select-none pointer-events-none space-y-3">
                    <div class="flex justify-between items-center mb-4">
                        <div>
                            <p class="text-xs font-bold text-gray-900 dark:text-white">Rekap SPP — Juli 2026</p>
                            <p class="text-[10px] text-gray-500">Kelas X, XI, XII — SMA Harapan</p>
                        </div>
                        <span class="text-[9px] bg-brand-500/15 text-brand-500 font-bold px-3 py-1 rounded-full">Bulan Aktif</span>
                    </div>
                    @foreach([['Kelas X-A', '32', '30', '2'], ['Kelas XI-IPA', '36', '36', '0'], ['Kelas XII-IPS', '34', '28', '6']] as $row)
                    <div class="bg-white dark:bg-white/3 rounded-xl p-3 flex items-center justify-between border border-gray-100 dark:border-white/5">
                        <span class="text-xs font-semibold text-gray-800 dark:text-white">{{ $row[0] }}</span>
                        <div class="flex gap-4 text-[10px]">
                            <span class="text-gray-500">Total: <b class="text-gray-800 dark:text-white">{{ $row[1] }}</b></span>
                            <span class="text-green-600 font-bold">Lunas: {{ $row[2] }}</span>
                            <span class="{{ $row[3] > 0 ? 'text-red-500' : 'text-gray-400' }} font-bold">Tunggak: {{ $row[3] }}</span>
                        </div>
                    </div>
                    @endforeach
                    <div class="bg-brand-500/8 dark:bg-brand-500/15 rounded-xl p-3 flex justify-between items-center border border-brand-500/20">
                        <span class="text-xs font-bold text-brand-600 dark:text-brand-400">Total Penerimaan</span>
                        <span class="text-sm font-extrabold text-brand-600 dark:text-brand-400">Rp 248.500.000</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- 2: BOS --}}
    <section id="bos" class="py-20 px-6 section-divider scroll-reveal">
        <div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
            <div class="order-2 lg:order-1 glass-card rounded-2xl p-6">
                <div class="space-y-3">
                    <p class="text-xs font-bold text-gray-900 dark:text-white mb-4">Buku Kas Umum (BKU) — Agustus 2026</p>
                    @foreach([
                        ['Saldo Awal', '+', 'Rp 45.200.000', 'text-gray-800 dark:text-white'],
                        ['Alokasi Sarpras', '-', 'Rp 12.000.000', 'text-red-500'],
                        ['Alokasi PTK Non-PNS', '-', 'Rp 8.500.000', 'text-red-500'],
                        ['Pajak PPN (11%)', '-', 'Rp 1.320.000', 'text-orange-500'],
                        ['Saldo Akhir', '=', 'Rp 23.380.000', 'text-brand-500 dark:text-brand-400 font-extrabold'],
                    ] as $row)
                    <div class="flex items-center justify-between text-xs py-2 border-b border-gray-100 dark:border-white/5 last:border-none">
                        <span class="text-gray-600 dark:text-gray-400">{{ $row[0] }}</span>
                        <span class="{{ $row[3] }}">{{ $row[1] }} {{ $row[2] }}</span>
                    </div>
                    @endforeach
                    <button class="w-full mt-3 text-xs font-bold text-white bg-brand-500 rounded-xl py-2.5 opacity-80">
                        <i class="bx bx-download mr-1"></i> Ekspor BKU — Format PDF
                    </button>
                </div>
            </div>
            <div class="order-1 lg:order-2">
                <span class="section-tag mb-5 inline-flex"><i class="bx bx-book-content"></i> Modul 02 — Dana BOS</span>
                <h2 class="text-3xl sm:text-4xl font-extrabold text-gray-900 dark:text-white tracking-tight leading-tight mb-5">
                    BKU Dana BOS selesai<br>tanpa lembur.
                </h2>
                <p class="text-sm sm:text-base text-gray-500 dark:text-gray-400 leading-relaxed mb-6">
                    Eduja mengkompilasi seluruh transaksi belanja dana BOS secara otomatis ke dalam BKU lengkap beserta buku pembantu Kas Tunai, Rekening Bank, dan Pajak. Bendahara tidak perlu lagi menginput ulang data — semuanya sudah terintegrasi.
                </p>
                <ul class="space-y-3 mb-8">
                    @foreach([
                        'BKU otomatis dari seluruh pencatatan belanja yang diinput',
                        'Pemisahan otomatis: Kas Tunai, Bank, dan Pajak',
                        'Perhitungan pajak PPN, PPh 21, 22, 23 secara akurat',
                        'Ekspor langsung ke PDF siap cetak untuk pelaporan dinas',
                        'Monitoring anggaran RKAS vs realisasi secara real-time',
                    ] as $feat)
                    <li class="flex items-start gap-3 text-sm text-gray-600 dark:text-gray-300">
                        <span class="w-5 h-5 rounded-full bg-orange-500/15 text-orange-500 flex items-center justify-center mt-0.5 shrink-0"><i class="bx bx-check text-xs font-bold"></i></span>
                        {{ $feat }}
                    </li>
                    @endforeach
                </ul>
                <a href="/contact" class="inline-flex items-center gap-2 rounded-full bg-orange-500 text-white px-7 py-3 text-sm font-bold hover:bg-orange-600 transition-all shadow-md">
                    Demo Modul BOS <i class="bx bx-right-arrow-alt"></i>
                </a>
            </div>
        </div>
    </section>

    {{-- 3: Presensi --}}
    <section id="presensi" class="py-20 px-6 section-divider scroll-reveal">
        <div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
            <div>
                <span class="section-tag mb-5 inline-flex"><i class="bx bx-calendar-check"></i> Modul 03 — Presensi</span>
                <h2 class="text-3xl sm:text-4xl font-extrabold text-gray-900 dark:text-white tracking-tight leading-tight mb-5">
                    Absensi digital yang<br>efisien dan akurat.
                </h2>
                <p class="text-sm sm:text-base text-gray-500 dark:text-gray-400 leading-relaxed mb-6">
                    Tidak ada lagi kertas absensi yang hilang atau rekap yang memakan waktu. Presensi harian siswa dan GTK (Guru dan Tenaga Kependidikan) dikelola secara digital — termasuk status Hadir, Sakit, Izin, Alfa, dan Dinas Luar.
                </p>
                <ul class="space-y-3 mb-8">
                    @foreach([
                        'Input presensi harian siswa per kelas oleh wali kelas',
                        'Presensi GTK dengan status lengkap termasuk Dinas Luar (DL)',
                        'Portal siswa dan orang tua untuk pengajuan izin digital',
                        'Rekap bulanan otomatis siap unduh',
                        'Sinkronisasi data presensi dengan laporan kehadiran yayasan',
                    ] as $feat)
                    <li class="flex items-start gap-3 text-sm text-gray-600 dark:text-gray-300">
                        <span class="w-5 h-5 rounded-full bg-blue-500/15 text-blue-500 flex items-center justify-center mt-0.5 shrink-0"><i class="bx bx-check text-xs font-bold"></i></span>
                        {{ $feat }}
                    </li>
                    @endforeach
                </ul>
                <a href="/contact" class="inline-flex items-center gap-2 rounded-full bg-blue-500 text-white px-7 py-3 text-sm font-bold hover:bg-blue-600 transition-all shadow-md">
                    Demo Modul Presensi <i class="bx bx-right-arrow-alt"></i>
                </a>
            </div>
            <div class="glass-card rounded-2xl p-6">
                <p class="text-xs font-bold text-gray-900 dark:text-white mb-4">Presensi Kelas XI-IPA 2 — Senin, 21 Juli 2026</p>
                <div class="space-y-2">
                    @foreach([
                        ['Ahmad Zaki', 'Hadir', 'text-green-500 bg-green-500/10'],
                        ['Bunga Safitri', 'Hadir', 'text-green-500 bg-green-500/10'],
                        ['Cahyo Prabowo', 'Sakit', 'text-yellow-500 bg-yellow-500/10'],
                        ['Dina Marlinda', 'Hadir', 'text-green-500 bg-green-500/10'],
                        ['Eko Santoso', 'Izin', 'text-blue-500 bg-blue-500/10'],
                        ['Fatimah Azzahra', 'Alfa', 'text-red-500 bg-red-500/10'],
                    ] as $siswa)
                    <div class="flex items-center justify-between bg-white dark:bg-white/3 rounded-xl px-4 py-2.5 border border-gray-100 dark:border-white/5 select-none">
                        <div class="flex items-center gap-3">
                            <div class="w-7 h-7 rounded-full bg-gray-100 dark:bg-white/10 flex items-center justify-center text-[10px] font-bold text-gray-500">{{ strtoupper(substr($siswa[0], 0, 1)) }}</div>
                            <span class="text-xs font-medium text-gray-800 dark:text-white">{{ $siswa[0] }}</span>
                        </div>
                        <span class="text-[10px] font-bold px-3 py-1 rounded-full {{ $siswa[2] }}">{{ $siswa[1] }}</span>
                    </div>
                    @endforeach
                </div>
                <div class="mt-4 grid grid-cols-4 gap-2 text-center text-[10px]">
                    <div class="bg-green-500/10 text-green-600 rounded-lg py-1.5"><b>4</b><br>Hadir</div>
                    <div class="bg-yellow-500/10 text-yellow-600 rounded-lg py-1.5"><b>1</b><br>Sakit</div>
                    <div class="bg-blue-500/10 text-blue-600 rounded-lg py-1.5"><b>1</b><br>Izin</div>
                    <div class="bg-red-500/10 text-red-600 rounded-lg py-1.5"><b>1</b><br>Alfa</div>
                </div>
            </div>
        </div>
    </section>

    {{-- 4: Tabungan --}}
    <section id="tabungan" class="py-20 px-6 section-divider scroll-reveal">
        <div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
            <div class="glass-card rounded-2xl p-6">
                <p class="text-xs font-bold text-gray-900 dark:text-white mb-4">Buku Tabungan — Ahmad Zaki (XI-IPA 2)</p>
                <div class="space-y-2">
                    @foreach([
                        ['21 Jul 2026', 'Setoran', '+Rp 50.000', 'text-green-500', 'Rp 450.000'],
                        ['14 Jul 2026', 'Setoran', '+Rp 50.000', 'text-green-500', 'Rp 400.000'],
                        ['1 Jul 2026', 'Penarikan', '-Rp 100.000', 'text-red-500', 'Rp 350.000'],
                        ['23 Jun 2026', 'Setoran', '+Rp 50.000', 'text-green-500', 'Rp 450.000'],
                    ] as $row)
                    <div class="flex items-center justify-between text-xs py-2 border-b border-gray-100 dark:border-white/5">
                        <div>
                            <span class="text-gray-500">{{ $row[0] }}</span>
                            <span class="text-gray-800 dark:text-white font-medium ml-3">{{ $row[1] }}</span>
                        </div>
                        <div class="text-right">
                            <span class="{{ $row[3] }} font-bold block">{{ $row[2] }}</span>
                            <span class="text-gray-400 text-[10px]">Saldo: {{ $row[4] }}</span>
                        </div>
                    </div>
                    @endforeach
                </div>
                <div class="mt-4 bg-brand-500/8 dark:bg-brand-500/15 rounded-xl p-3 flex justify-between items-center">
                    <span class="text-xs font-bold text-brand-600 dark:text-brand-400">Saldo Saat Ini</span>
                    <span class="text-base font-extrabold text-brand-600 dark:text-brand-400">Rp 450.000</span>
                </div>
            </div>
            <div>
                <span class="section-tag mb-5 inline-flex"><i class="bx bx-wallet"></i> Modul 04 — Tabungan Siswa</span>
                <h2 class="text-3xl sm:text-4xl font-extrabold text-gray-900 dark:text-white tracking-tight leading-tight mb-5">
                    Tabungan siswa yang<br>aman dan transparan.
                </h2>
                <p class="text-sm sm:text-base text-gray-500 dark:text-gray-400 leading-relaxed mb-6">
                    Program tabungan sekolah kini bisa dikelola dengan akurat dan transparan. Setiap setoran dan penarikan tercatat digital, dan orang tua dapat memantau saldo anak mereka melalui portal secara real-time.
                </p>
                <ul class="space-y-3 mb-8">
                    @foreach([
                        'Pencatatan setoran dan penarikan per siswa secara digital',
                        'Rekap saldo tabungan seluruh siswa per kelas',
                        'Portal orang tua untuk memantau saldo tanpa perlu ke sekolah',
                        'Laporan tabungan kolektif per semester',
                        'Keamanan data ganda dengan audit trail setiap transaksi',
                    ] as $feat)
                    <li class="flex items-start gap-3 text-sm text-gray-600 dark:text-gray-300">
                        <span class="w-5 h-5 rounded-full bg-purple-500/15 text-purple-500 flex items-center justify-center mt-0.5 shrink-0"><i class="bx bx-check text-xs font-bold"></i></span>
                        {{ $feat }}
                    </li>
                    @endforeach
                </ul>
                <a href="/contact" class="inline-flex items-center gap-2 rounded-full bg-purple-500 text-white px-7 py-3 text-sm font-bold hover:bg-purple-600 transition-all shadow-md">
                    Demo Modul Tabungan <i class="bx bx-right-arrow-alt"></i>
                </a>
            </div>
        </div>
    </section>

    {{-- 5: AI --}}
    <section id="ai" class="py-20 px-6 section-divider scroll-reveal">
        <div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
            <div>
                <span class="section-tag mb-5 inline-flex"><i class="bx bx-brain"></i> Modul 05 — Asisten AI</span>
                <h2 class="text-3xl sm:text-4xl font-extrabold text-gray-900 dark:text-white tracking-tight leading-tight mb-5">
                    Kecerdasan buatan<br>di tangan pendidik.
                </h2>
                <p class="text-sm sm:text-base text-gray-500 dark:text-gray-400 leading-relaxed mb-6">
                    Asisten AI Eduja membantu tenaga pendidik membuat pengumuman, laporan naratif, surat resmi, dan materi komunikasi dalam hitungan detik — menghemat waktu agar guru bisa lebih fokus mendidik.
                </p>
                <ul class="space-y-3 mb-8">
                    @foreach([
                        'Buat pengumuman sekolah profesional dengan satu prompt',
                        'Bantuan penulisan surat dinas dan surat resmi',
                        'Ringkasan laporan bulanan operasional otomatis',
                        'Saran komunikasi kepada orang tua siswa',
                        'Tersedia langsung di dalam platform tanpa aplikasi tambahan',
                    ] as $feat)
                    <li class="flex items-start gap-3 text-sm text-gray-600 dark:text-gray-300">
                        <span class="w-5 h-5 rounded-full bg-emerald-500/15 text-emerald-500 flex items-center justify-center mt-0.5 shrink-0"><i class="bx bx-check text-xs font-bold"></i></span>
                        {{ $feat }}
                    </li>
                    @endforeach
                </ul>
                <a href="/contact" class="inline-flex items-center gap-2 rounded-full bg-emerald-500 text-white px-7 py-3 text-sm font-bold hover:bg-emerald-600 transition-all shadow-md">
                    Coba Asisten AI <i class="bx bx-right-arrow-alt"></i>
                </a>
            </div>
            <div class="glass-card rounded-2xl p-6 space-y-4">
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-8 h-8 rounded-full bg-emerald-500 flex items-center justify-center"><i class="bx bx-brain text-white text-sm"></i></div>
                    <span class="text-sm font-bold text-gray-900 dark:text-white">Asisten AI Eduja</span>
                    <span class="text-[9px] bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 font-bold px-2 py-0.5 rounded-full">Online</span>
                </div>
                <div class="bg-gray-100 dark:bg-white/5 rounded-xl p-4 text-xs text-gray-700 dark:text-gray-300 select-none">
                    <p class="font-semibold text-gray-500 dark:text-gray-400 text-[10px] uppercase mb-2">Prompt kamu:</p>
                    <p class="italic">"Buatkan pengumuman libur akhir semester untuk orang tua siswa."</p>
                </div>
                <div class="bg-brand-500/8 dark:bg-brand-500/12 rounded-xl p-4 text-xs text-gray-700 dark:text-gray-300 leading-relaxed select-none border border-brand-500/15">
                    <p class="font-semibold text-brand-500 dark:text-brand-400 text-[10px] uppercase mb-2">Hasil AI:</p>
                    <p>Yth. Bapak/Ibu Orang Tua/Wali Siswa,<br><br>
                    Diberitahukan bahwa kegiatan belajar mengajar akan diliburkan pada <b>21–31 Juli 2026</b> dalam rangka libur akhir semester ganjil tahun pelajaran 2026/2027. Siswa kembali masuk pada <b>1 Agustus 2026</b>.<br><br>
                    Demikian pemberitahuan ini kami sampaikan. Atas perhatiannya, kami ucapkan terima kasih.<br><br>
                    Hormat kami,<br><i>Kepala Sekolah</i></p>
                </div>
                <p class="text-[10px] text-gray-400 text-center">✦ Dihasilkan dalam 2 detik &nbsp;|&nbsp; Bisa diedit dan langsung kirim</p>
            </div>
        </div>
    </section>

    {{-- 6: Dasbor Yayasan --}}
    <section class="py-20 px-6 section-divider scroll-reveal">
        <div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
            <div class="glass-card rounded-2xl p-6 space-y-4">
                <p class="text-xs font-bold text-gray-900 dark:text-white">Dasbor Yayasan — Ringkasan 3 Unit Sekolah</p>
                @foreach([['SMP Al-Falah', '480 Siswa', 'Rp 96jt SPP', 'BKU Up-to-date'], ['SMA Harapan', '620 Siswa', 'Rp 155jt SPP', 'BKU Up-to-date'], ['SMK Teknik', '380 Siswa', 'Rp 76jt SPP', 'Ada 3 Tunggakan']] as $unit)
                <div class="bg-white dark:bg-white/3 rounded-xl p-4 border border-gray-100 dark:border-white/5">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-xs font-bold text-gray-900 dark:text-white">{{ $unit[0] }}</p>
                            <p class="text-[10px] text-gray-400">{{ $unit[1] }}</p>
                        </div>
                        <span class="text-[9px] {{ strpos($unit[2], 'Tunggakan') !== false ? 'bg-red-500/15 text-red-500' : 'bg-green-500/15 text-green-600' }} font-bold px-2 py-0.5 rounded-full">{{ strpos($unit[3], 'Tunggakan') !== false ? '⚠ Perhatian' : '✓ Normal' }}</span>
                    </div>
                    <div class="mt-3 grid grid-cols-2 gap-2">
                        <div class="bg-gray-50 dark:bg-white/3 rounded-lg p-2 text-center text-[10px]">
                            <span class="text-gray-500 block">Penerimaan SPP</span>
                            <b class="text-brand-500">{{ $unit[2] }}</b>
                        </div>
                        <div class="bg-gray-50 dark:bg-white/3 rounded-lg p-2 text-center text-[10px]">
                            <span class="text-gray-500 block">Status BKU</span>
                            <b class="{{ strpos($unit[3], 'Tunggakan') !== false ? 'text-orange-500' : 'text-green-500' }}">{{ $unit[3] }}</b>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            <div>
                <span class="section-tag mb-5 inline-flex"><i class="bx bx-bar-chart-alt-2"></i> Modul 06 — Yayasan & Dinas</span>
                <h2 class="text-3xl sm:text-4xl font-extrabold text-gray-900 dark:text-white tracking-tight leading-tight mb-5">
                    Pantau seluruh unit sekolah<br>dari satu layar.
                </h2>
                <p class="text-sm sm:text-base text-gray-500 dark:text-gray-400 leading-relaxed mb-6">
                    Pengurus yayasan kini tidak perlu lagi meminta laporan satu per satu dari tiap sekolah. Dasbor eksekutif Eduja merangkum kondisi keuangan dan operasional seluruh unit dalam satu tampilan yang mudah dipahami.
                </p>
                <ul class="space-y-3 mb-8">
                    @foreach([
                        'Monitoring multi-sekolah dalam satu akun yayasan',
                        'Ringkasan penerimaan SPP dan saldo BOS per unit sekolah',
                        'Notifikasi otomatis jika ada anomali keuangan',
                        'Akses khusus level yayasan tanpa mengganggu operasional sekolah',
                        'Laporan konsolidasi keuangan antar unit tersedia kapan saja',
                    ] as $feat)
                    <li class="flex items-start gap-3 text-sm text-gray-600 dark:text-gray-300">
                        <span class="w-5 h-5 rounded-full bg-rose-500/15 text-rose-500 flex items-center justify-center mt-0.5 shrink-0"><i class="bx bx-check text-xs font-bold"></i></span>
                        {{ $feat }}
                    </li>
                    @endforeach
                </ul>
                <a href="/contact" class="inline-flex items-center gap-2 rounded-full bg-rose-500 text-white px-7 py-3 text-sm font-bold hover:bg-rose-600 transition-all shadow-md">
                    Demo Dasbor Yayasan <i class="bx bx-right-arrow-alt"></i>
                </a>
            </div>
        </div>
    </section>

    {{-- CTA --}}
    <section class="py-24 px-6 text-center relative overflow-hidden section-divider">
        <div class="hero-orb hero-orb-1" style="opacity: 0.5;"></div>
        <div class="max-w-2xl mx-auto relative z-10">
            <h2 class="text-3xl sm:text-4xl font-extrabold text-gray-900 dark:text-white tracking-tight mb-4">Tertarik dengan layanan kami?</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-8 leading-relaxed">Jadwalkan demo gratis bersama tim Eduja. Kami akan tunjukkan cara terbaik mengimplementasikan platform ini untuk sekolah Anda.</p>
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                <a href="/contact" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-full bg-brand-500 px-8 py-4 text-sm font-bold text-white hover:bg-brand-600 transition-all shadow-lg">
                    Jadwalkan Demo <i class="bx bx-right-arrow-alt"></i>
                </a>
                <a href="/pricing" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-full border border-gray-300 dark:border-white/15 px-8 py-4 text-sm font-semibold text-gray-800 dark:text-white hover:bg-gray-50 dark:hover:bg-white/5 transition-all">
                    Lihat Harga
                </a>
            </div>
        </div>
    </section>

    @include('partials.public-footer')
    @include('partials.public-scripts')
</body>
</html>
