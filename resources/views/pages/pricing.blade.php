<!DOCTYPE html>
<html lang="id" class="scroll-smooth" x-data="{ billingCycle: 'monthly', studentCount: 250 }">
<head>
    @include('partials.public-head', [
        'title' => 'Harga Eduja — Paket untuk SMP & SMK',
        'description' => 'Pilih paket Eduja sesuai ukuran dan kebutuhan SMP atau SMK Anda. Transparan, fleksibel, dan siap didampingi tim kami.'
    ])
    <style>
        input[type="range"] {
            -webkit-appearance: none;
            appearance: none;
            width: 100%;
            height: 6px;
            border-radius: 3px;
            outline: none;
        }
        html.dark input[type="range"] { background: #1e293b; }
        html:not(.dark) input[type="range"] { background: #e2e8f0; }
        input[type="range"]::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: #006266;
            cursor: pointer;
            transition: transform 0.15s, box-shadow 0.15s;
            box-shadow: 0 0 0 4px rgba(0, 98, 102, 0.15);
        }
        input[type="range"]::-webkit-slider-thumb:hover {
            transform: scale(1.2);
            box-shadow: 0 0 0 6px rgba(0, 98, 102, 0.2);
        }
    </style>
</head>
<body class="antialiased">

    @include('partials.public-nav', ['activePage' => 'harga'])

    {{-- HERO --}}
    <section class="relative pt-28 pb-12 px-6 text-center overflow-hidden">
        <div class="hero-orb hero-orb-1" style="opacity: 0.6;"></div>
        <div class="max-w-4xl mx-auto relative z-10">
            <div class="section-tag mb-6 inline-flex"><i class="bx bx-dollar-circle"></i> Harga Transparan</div>
            <h1 class="text-4xl sm:text-6xl font-extrabold tracking-tight leading-tight mb-6">
                <span class="gradient-text">Pilih yang Pas.</span><br>
                <span class="text-gray-900 dark:text-white">Sesuai Skala Sekolah Anda.</span>
            </h1>
            <p class="text-base sm:text-lg text-gray-500 dark:text-gray-400 max-w-2xl mx-auto leading-relaxed mb-8">
                Mulai dari kebutuhan inti SMP/SMK, lalu kembangkan saat sekolah siap. Tim Eduja membantu menghitung paket yang paling masuk akal berdasarkan jumlah siswa dan modul yang digunakan.
            </p>

            {{-- Billing Cycle Toggle --}}
            <div class="inline-flex items-center justify-center p-1.5 rounded-full bg-gray-100 dark:bg-white/5 border border-gray-200 dark:border-white/10">
                <button @click="billingCycle = 'monthly'"
                        :class="billingCycle === 'monthly' ? 'bg-white dark:bg-brand-500 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 dark:text-gray-400'"
                        class="rounded-full px-6 py-2 text-xs font-semibold transition-all">Bulanan</button>
                <button @click="billingCycle = 'yearly'"
                        :class="billingCycle === 'yearly' ? 'bg-white dark:bg-brand-500 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 dark:text-gray-400'"
                        class="rounded-full px-6 py-2 text-xs font-semibold transition-all">
                    Tahunan
                    <span class="ml-1.5 text-[9px] bg-brand-500/15 text-brand-600 dark:bg-white/20 dark:text-white px-2 py-0.5 rounded-full font-mono">Hemat 20%</span>
                </button>
            </div>
        </div>
    </section>

    {{-- STUDENT SLIDER --}}
    <section class="pb-12 px-6 max-w-2xl mx-auto z-10 relative">
        <div class="glass-card rounded-3xl p-7">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4 mb-6">
                <div>
                    <h3 class="text-base font-bold text-gray-900 dark:text-white">Simulasi Jumlah Siswa</h3>
                    <p class="text-xs text-gray-500 mt-1">Geser slider untuk menyesuaikan skala sekolah Anda</p>
                </div>
                <div class="bg-brand-500/10 border border-brand-500/20 text-brand-600 dark:text-brand-400 rounded-2xl px-6 py-3 text-center shrink-0">
                    <span class="text-3xl font-extrabold block leading-none" x-text="studentCount.toLocaleString('id-ID')"></span>
                    <span class="text-[9px] uppercase tracking-wider font-semibold font-mono">Siswa Aktif</span>
                </div>
            </div>
            <input type="range" min="50" max="2500" step="10" x-model.number="studentCount" class="cursor-pointer mb-3" aria-label="Jumlah siswa"/>
            <div class="flex justify-between text-[10px] text-gray-400 font-mono">
                <span>50</span><span>625</span><span>1.250</span><span>1.875</span><span>2.500+</span>
            </div>
        </div>
    </section>

    {{-- PRICING CARDS --}}
    <section class="pb-24 px-6 max-w-5xl mx-auto">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">

            {{-- Basic Plan --}}
            <div class="glass-card rounded-3xl p-8 flex flex-col justify-between">
                <div>
                    <div class="flex justify-between items-start mb-6">
                        <div>
                            <h2 class="text-2xl font-extrabold text-gray-900 dark:text-white">Paket Starter</h2>
                            <p class="text-xs text-gray-400 uppercase tracking-wider font-semibold mt-1">Fondasi Operasional Sekolah</p>
                        </div>
                        <div class="text-right">
                            <span class="text-2xl font-extrabold text-brand-500" x-text="billingCycle === 'monthly' ? 'Rp 10.000' : 'Rp 8.000'"></span>
                            <span class="text-[10px] text-gray-500 block mt-0.5">/ siswa / bulan</span>
                        </div>
                    </div>

                    <div class="bg-gray-100/60 dark:bg-white/3 rounded-2xl p-5 mb-7 border border-gray-200 dark:border-white/5">
                        <span class="text-xs text-gray-400 block mb-1">Estimasi biaya untuk sekolah Anda:</span>
                        <span class="text-2xl font-extrabold text-gray-900 dark:text-white"
                              x-text="new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(studentCount * (billingCycle === 'monthly' ? 10000 : 8000))">
                        </span>
                        <span class="text-xs text-gray-500" x-text="billingCycle === 'monthly' ? '/ bulan' : '/ bulan (tagih tahunan)'"></span>
                    </div>

                    <p class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed mb-5">
                        Ideal untuk sekolah yang baru memulai digitalisasi dan membutuhkan modul inti manajemen keuangan dan operasional.
                    </p>

                    <ul class="space-y-3.5 text-sm text-gray-600 dark:text-gray-300">
                        @foreach([
                            ['check', 'Manajemen Data Siswa & GTK'],
                            ['check', 'Kasir Penerimaan SPP & Cetak Kuitansi'],
                            ['check', 'Pencatatan Belanja & Dana BOS'],
                            ['check', 'Buku Kas Umum (BKU) Otomatis'],
                            ['check', 'Absensi GTK (Guru & Staf)'],
                            ['check', 'Dasbor Kepala Sekolah'],
                            ['x', 'Buku Pembantu Kas, Bank & Pajak', true],
                            ['x', 'Tabungan Siswa Terintegrasi', true],
                            ['x', 'Presensi Digital Siswa', true],
                            ['x', 'Asisten AI Tenaga Pendidik', true],
                        ] as $f)
                        <li class="flex items-center gap-3 {{ isset($f[2]) ? 'opacity-40' : '' }}">
                            <i class="bx {{ $f[0] === 'check' ? 'bx-check text-brand-500' : 'bx-x text-gray-400' }} text-base shrink-0"></i>
                            {{ $f[1] }}
                        </li>
                        @endforeach
                    </ul>
                </div>
                <div class="mt-8">
                    <a href="/contact" id="btn-basic-plan" class="block w-full text-center rounded-full border-2 border-gray-300 dark:border-white/20 text-gray-900 dark:text-white py-3.5 text-sm font-bold hover:bg-gray-50 dark:hover:bg-white/5 transition-all">
                        Mulai dengan Paket Starter
                    </a>
                </div>
            </div>

            {{-- Premium Plan --}}
            <div class="glass-card rounded-3xl p-8 flex flex-col justify-between ring-2 ring-brand-500/30 dark:ring-brand-500/50 relative overflow-hidden">
                {{-- Best Value Badge --}}
                <div class="absolute top-5 right-5">
                    <span class="bg-brand-500 text-white text-[9px] font-bold px-3 py-1 rounded-full uppercase tracking-wider shadow-md">⭐ Terpopuler</span>
                </div>

                <div>
                    <div class="flex justify-between items-start mb-6 pr-20">
                        <div>
                            <h2 class="text-2xl font-extrabold text-gray-900 dark:text-white">Paket School Partner</h2>
                            <p class="text-xs text-brand-500 dark:text-brand-400 uppercase tracking-wider font-semibold mt-1">Lengkap & Komprehensif</p>
                        </div>
                        <div class="text-right">
                            <span class="text-2xl font-extrabold text-brand-500" x-text="billingCycle === 'monthly' ? 'Rp 25.000' : 'Rp 20.000'"></span>
                            <span class="text-[10px] text-gray-500 block mt-0.5">/ siswa / bulan</span>
                        </div>
                    </div>

                    <div class="bg-brand-500/8 dark:bg-brand-500/15 rounded-2xl p-5 mb-7 border border-brand-500/15">
                        <span class="text-xs text-brand-500 dark:text-brand-400 block mb-1">Estimasi biaya untuk sekolah Anda:</span>
                        <span class="text-2xl font-extrabold text-gray-900 dark:text-white"
                              x-text="new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(studentCount * (billingCycle === 'monthly' ? 25000 : 20000))">
                        </span>
                        <span class="text-xs text-gray-500" x-text="billingCycle === 'monthly' ? '/ bulan' : '/ bulan (tagih tahunan)'"></span>
                    </div>

                    <p class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed mb-5">
                        Solusi terlengkap untuk sekolah yang ingin transformasi digital menyeluruh — dari keuangan, operasional, hingga komunikasi berbasis AI.
                    </p>

                    <ul class="space-y-3.5 text-sm text-gray-600 dark:text-gray-300">
                        @foreach([
                            'Semua Modul Paket Starter',
                            'Buku Pembantu Kas, Bank & Pajak (Sub-Ledger)',
                            'Presensi Harian Digital Siswa',
                            'Tabungan Siswa Terintegrasi + Portal Orang Tua',
                            'Asisten AI untuk Tenaga Pendidik',
                            'Bagan Organisasi Sekolah Visual & Dinamis',
                            'Laporan Keuangan per Kelas & Ekspor BKU PDF',
                            'Dasbor Eksekutif Yayasan / Dinas',
                            'Prioritas Dukungan Teknis & Onboarding',
                            'Update Fitur Terbaru Secara Berkala',
                        ] as $f)
                        <li class="flex items-center gap-3">
                            <i class="bx bx-check text-brand-500 text-base shrink-0"></i>
                            {{ $f }}
                        </li>
                        @endforeach
                    </ul>
                </div>
                <div class="mt-8">
                    <a href="/contact" id="btn-premium-plan" class="block w-full text-center rounded-full bg-brand-500 text-white py-3.5 text-sm font-bold hover:bg-brand-600 transition-all shadow-lg shadow-brand-500/25">
                        Mulai dengan Paket School Partner
                    </a>
                </div>
            </div>

        </div>
    </section>

    {{-- FAQ --}}
    <section class="py-20 px-6 section-divider scroll-reveal" x-data="{ open: null }">
        <div class="max-w-3xl mx-auto">
            <div class="text-center mb-12">
                <div class="section-tag mb-4 inline-flex">FAQ</div>
                <h2 class="text-3xl font-extrabold text-gray-900 dark:text-white tracking-tight">Pertanyaan yang Sering Diajukan</h2>
            </div>
            <div class="space-y-3">
                @foreach([
                    ['Apakah ada biaya setup atau instalasi?', 'Tidak ada. Eduja adalah platform berbasis cloud yang tidak memerlukan instalasi server. Proses onboarding dilakukan oleh tim kami secara remote tanpa biaya tambahan.'],
                    ['Bagaimana jika jumlah siswa kami berubah?', 'Biaya berlangganan disesuaikan secara fleksibel setiap bulan berdasarkan jumlah siswa aktif. Jika siswa bertambah atau berkurang, tagihan Anda menyesuaikan secara otomatis.'],
                    ['Apakah data sekolah kami aman?', 'Keamanan data adalah prioritas utama kami. Seluruh data disimpan di server terenkripsi dengan backup harian. Akses dikontrol ketat dengan sistem role-based yang dapat disesuaikan.'],
                    ['Berapa lama proses implementasi?', 'Rata-rata sekolah bisa beroperasi penuh dalam 3-5 hari kerja. Tim kami mendampingi proses migrasi data, pelatihan pengguna, dan pengaturan awal.'],
                    ['Apakah tersedia trial/demo sebelum berlangganan?', 'Ya! Kami menyediakan demo gratis selama 14 hari tanpa memerlukan kartu kredit. Tim kami akan mendampingi proses demo agar Anda bisa merasakan manfaat nyata platform ini.'],
                    ['Bagaimana cara menghubungi dukungan teknis?', 'Pengguna Paket School Partner mendapatkan prioritas dukungan via WhatsApp dan email. Paket Starter mendapatkan dukungan email standar.'],
                ] as $i => $faq)
                <div class="glass-card rounded-2xl overflow-hidden">
                    <button @click="open === {{ $i }} ? open = null : open = {{ $i }}"
                            class="w-full px-6 py-4 text-left flex items-center justify-between gap-4"
                            :aria-expanded="open === {{ $i }}">
                        <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ $faq[0] }}</span>
                        <i class="bx text-lg text-brand-500 shrink-0 transition-transform"
                           :class="open === {{ $i }} ? 'bx-minus rotate-180' : 'bx-plus'"></i>
                    </button>
                    <div x-show="open === {{ $i }}"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 -translate-y-2"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100 translate-y-0"
                         x-transition:leave-end="opacity-0 -translate-y-2"
                         class="px-6 pb-5 text-sm text-gray-500 dark:text-gray-400 leading-relaxed border-t border-gray-100 dark:border-white/5 pt-4">
                        {{ $faq[1] }}
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- CTA --}}
    <section class="py-20 px-6 text-center section-divider">
        <div class="max-w-2xl mx-auto">
            <h2 class="text-3xl font-extrabold text-gray-900 dark:text-white mb-4">Masih bingung pilih paket yang tepat?</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-8">Konsultasikan kebutuhan sekolah Anda dengan tim kami — gratis, tanpa tekanan, dan kami akan bantu menemukan solusi yang paling sesuai.</p>
            <a href="/contact" id="btn-konsultasi-pricing"
               class="inline-flex items-center gap-2 rounded-full bg-brand-500 px-8 py-4 text-sm font-bold text-white hover:bg-brand-600 transition-all shadow-lg">
                Konsultasi Gratis dengan Tim Kami <i class="bx bx-right-arrow-alt"></i>
            </a>
        </div>
    </section>

    @include('partials.public-footer')
    @include('partials.public-scripts')
</body>
</html>
