<!DOCTYPE html>
<html lang="id" class="scroll-smooth" x-data>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="/favicon.png">
    <title>Eduja - Sistem Manajemen Operasional & Keuangan Sekolah Modern</title>
    
    <!-- Meta SEO -->
    <meta name="description" content="Eduja adalah sistem terpadu manajemen operasional dan keuangan sekolah di Indonesia. Mengelola SPP, Tabungan, Absensi, dan BKU Dana BOS secara presisi.">
    <meta name="keywords" content="sistem sekolah, aplikasi spp, dana bos, bku sekolah, rkas, absensi siswa, tabungan siswa">
    
    <!-- Google Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    
    <!-- Tailwind v4 and Custom Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <script>
        // Inline theme initialization to prevent flash on load
        const systemTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        const savedTheme = localStorage.getItem('theme');
        const theme = savedTheme || systemTheme;
        if (theme === 'dark') {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            transition: background-color 0.5s ease, color 0.5s ease;
        }

        /* Dark Theme Default */
        html.dark body {
            background-color: #08080c;
            color: #f3f4f6;
        }
        /* Light Theme */
        html:not(.dark) body {
            background-color: #fcfcfd;
            color: #1f2937;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-track {
            background: transparent;
        }
        ::-webkit-scrollbar-thumb {
            border-radius: 4px;
            transition: background-color 0.3s;
        }
        html.dark ::-webkit-scrollbar-thumb {
            background: #1a1a24;
        }
        html:not(.dark) ::-webkit-scrollbar-thumb {
            background: #e2e8f0;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #006266;
        }

        /* Apple Glassmorphism Nav */
        .glass-nav {
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            transition: background-color 0.5s ease, border-color 0.5s ease;
        }
        html.dark .glass-nav {
            background: rgba(8, 8, 12, 0.75);
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        html:not(.dark) .glass-nav {
            background: rgba(252, 252, 253, 0.75);
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        /* Apple Glassmorphism Card */
        .glass-card {
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            transition: all 0.5s cubic-bezier(0.16, 1, 0.3, 1);
        }
        html.dark .glass-card {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        html:not(.dark) .glass-card {
            background: rgba(255, 255, 255, 0.6);
            border: 1px solid rgba(0, 0, 0, 0.06);
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.02);
        }

        html.dark .glass-card:hover {
            background: rgba(255, 255, 255, 0.04);
            border-color: rgba(0, 98, 102, 0.4);
            transform: translateY(-4px);
            box-shadow: 0 20px 40px -15px rgba(0, 98, 102, 0.15);
        }
        html:not(.dark) .glass-card:hover {
            background: rgba(255, 255, 255, 0.9);
            border-color: rgba(0, 98, 102, 0.25);
            transform: translateY(-4px);
            box-shadow: 0 20px 45px -12px rgba(0, 98, 102, 0.08);
        }

        /* Gradient Text */
        .gradient-text {
            transition: background-image 0.5s ease;
        }
        html.dark .gradient-text {
            background: linear-gradient(135deg, #ffffff 30%, #a5b1c2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        html:not(.dark) .gradient-text {
            background: linear-gradient(135deg, #111827 30%, #4b5563 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* Native Scroll-Driven Animations */
        @media (prefers-reduced-motion: no-preference) {
            @supports ((animation-timeline: view()) and (animation-range: entry)) {
                @keyframes fade-up {
                    from {
                        opacity: 0;
                        transform: translateY(60px);
                    }
                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }

                .scroll-reveal {
                    animation: fade-up auto linear both;
                    animation-timeline: view();
                    animation-range: entry 10% cover 40%;
                }
            }
        }

        /* Fallback Reveal Class if CSS Scroll-Driven Animations not supported */
        .reveal-fallback {
            opacity: 0;
            transform: translateY(40px);
            transition: opacity 0.8s ease, transform 0.8s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .reveal-fallback.active {
            opacity: 1;
            transform: translateY(0);
        }
    </style>
</head>
<body class="antialiased">

    <!-- Header / Navbar -->
    <header class="fixed top-0 left-0 right-0 z-50 glass-nav">
        <div class="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between">
            <a href="#" class="flex items-center gap-2 text-xl font-bold tracking-tight text-gray-900 dark:text-white">
                <span class="w-8 h-8 bg-brand-500 rounded-lg flex items-center justify-center text-white shadow-xs">E</span>
                Eduja
            </a>
            
            <nav class="hidden md:flex items-center gap-8 text-sm font-medium text-gray-600 dark:text-gray-400">
                <a href="#fitur" class="hover:text-gray-900 dark:hover:text-white transition-colors">Fitur Utama</a>
                <a href="/pricing" class="hover:text-gray-900 dark:hover:text-white transition-colors">Harga</a>
                <a href="/contact" class="hover:text-gray-900 dark:hover:text-white transition-colors">Kontak</a>
            </nav>

            <div class="flex items-center gap-4">
                <!-- Dark/Light Theme Toggle -->
                <button @click="$store.theme.toggle()" class="w-9 h-9 flex items-center justify-center rounded-full border border-gray-200 dark:border-white/10 hover:bg-gray-100 dark:hover:bg-white/5 text-gray-700 dark:text-gray-300 transition-colors" aria-label="Toggle Theme">
                    <i x-show="$store.theme.theme === 'light'" class="bx bx-moon text-lg"></i>
                    <i x-show="$store.theme.theme === 'dark'" class="bx bx-sun text-lg" style="display: none;"></i>
                </button>

                <a href="{{ route('login') }}" class="rounded-full bg-gray-900 text-white dark:bg-white dark:text-black px-5 py-2 text-xs font-semibold hover:bg-gray-800 dark:hover:bg-gray-100 transition-all shadow-xs">
                    Masuk Aplikasi <i class="bx bx-right-arrow-alt align-middle ml-1"></i>
                </a>
            </div>
        </div>
    </header>

    <!-- HERO SECTION -->
    <section class="relative min-h-screen pt-32 pb-20 flex flex-col justify-center items-center px-6 overflow-hidden">
        <!-- Glowing background decoration (Visible in dark mode) -->
        <div class="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[500px] h-[500px] bg-brand-500/5 dark:bg-brand-500/10 rounded-full blur-[120px] pointer-events-none"></div>

        <div class="max-w-4xl mx-auto text-center z-10">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-500/10 border border-brand-500/20 px-3.5 py-1 text-xs font-semibold text-brand-500 dark:text-brand-400 mb-6">
                <i class="bx bxs-bolt"></i> Eduja System v1.0 Live
            </span>
            
            <h1 class="text-4xl sm:text-6xl md:text-7xl font-extrabold tracking-tight gradient-text leading-[1.1] mb-6">
                Sistem Sekolah Modern.<br>Ringkas. Presisi.
            </h1>
            
            <p class="text-base sm:text-xl text-gray-500 dark:text-gray-400 max-w-2xl mx-auto leading-relaxed mb-10">
                Sederhanakan manajemen operasional kesiswaan, penagihan SPP otomatis, simpanan tabungan, presensi harian, hingga pelaporan pertanggungjawaban Dana BOS (BKU) dalam satu dasbor terpadu.
            </p>

            <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                <a href="{{ route('login') }}" class="w-full sm:w-auto rounded-full bg-brand-500 px-8 py-3.5 text-center text-sm font-semibold text-white hover:bg-brand-600 transition-all shadow-md">
                    Coba Demo Sekarang
                </a>
                <a href="#fitur" class="w-full sm:w-auto rounded-full border border-gray-200 dark:border-white/10 bg-white/2 px-8 py-3.5 text-center text-sm font-semibold text-gray-800 dark:text-white hover:bg-gray-50 dark:hover:bg-white/5 transition-all">
                    Pelajari Fitur
                </a>
            </div>
        </div>
    </section>

    <!-- SECTION: BRIEF VALUES -->
    <section id="keunggulan" class="py-20 px-6 max-w-7xl mx-auto border-t border-gray-150 dark:border-white/5 scroll-reveal">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="p-6">
                <span class="text-brand-500 text-3xl mb-4 block"><i class="bx bxs-zap"></i></span>
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2">Instan & Otomatis</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 leading-relaxed">Pembuatan tagihan SPP ribuan siswa sekali klik, didukung rekap tunggakan real-time.</p>
            </div>
            <div class="p-6">
                <span class="text-brand-500 text-3xl mb-4 block"><i class="bx bxs-lock-alt"></i></span>
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2">Aman & Terkendali</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 leading-relaxed">Pembagian hak akses (KS, Bendahara, TU) yang membatasi hak input dan pelaporan keuangan.</p>
            </div>
            <div class="p-6">
                <span class="text-brand-500 text-3xl mb-4 block"><i class="bx bxs-file-pdf"></i></span>
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2">Patuhi Regulasi BOS</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 leading-relaxed">Kompilasi Buku Kas Umum (BKU) otomatis beserta Buku Pembantu Kas, Bank, & Pajak yang siap cetak.</p>
            </div>
        </div>
    </section>

    <!-- SECTION: FEATURES -->
    <section id="fitur" class="py-20 px-6 max-w-7xl mx-auto border-t border-gray-150 dark:border-white/5">
        <div class="mb-12">
            <span class="text-xs font-bold uppercase tracking-wider text-brand-500 dark:text-brand-400">Kemampuan Utama</span>
            <h2 class="text-3xl sm:text-5xl font-bold text-gray-900 dark:text-white tracking-tight mt-2">Didesain khusus untuk tata kelola sekolah Indonesia.</h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            
            <!-- Card 1: SPP -->
            <div class="glass-card rounded-2xl p-6 flex flex-col justify-between min-h-[300px] scroll-reveal">
                <div>
                    <div class="w-12 h-12 rounded-xl bg-brand-500/10 border border-brand-500/20 text-brand-500 dark:text-brand-400 flex items-center justify-center text-xl mb-6">
                        <i class="bx bx-receipt"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">Kasir SPP Modern</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 leading-relaxed">Definisikan tarif SPP per tahun ajaran/kelas, terima angsuran pembayaran, rekap tunggakan siswa, dan cetak kuitansi seketika.</p>
                </div>
                <div class="pt-6 border-t border-gray-150 dark:border-white/5 mt-6 text-xs text-brand-500 dark:text-brand-400 font-semibold font-mono">MODUL KEUANGAN SPP</div>
            </div>

            <!-- Card 2: BKU -->
            <div class="glass-card rounded-2xl p-6 flex flex-col justify-between min-h-[300px] scroll-reveal">
                <div>
                    <div class="w-12 h-12 rounded-xl bg-brand-500/10 border border-brand-500/20 text-brand-500 dark:text-brand-400 flex items-center justify-center text-xl mb-6">
                        <i class="bx bx-book-content"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">Buku Kas Umum & Pembantu</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 leading-relaxed">Kompilasi BKU otomatis yang memisahkan pembukuan Kas Tunai, Rekening Bank, dan Pajak. Memudahkan pelaporan pertanggungjawaban dinas.</p>
                </div>
                <div class="pt-6 border-t border-gray-150 dark:border-white/5 mt-6 text-xs text-brand-500 dark:text-brand-400 font-semibold font-mono">MODUL DANA BOS & BKU</div>
            </div>

            <!-- Card 3: Tabungan -->
            <div class="glass-card rounded-2xl p-6 flex flex-col justify-between min-h-[300px] scroll-reveal">
                <div>
                    <div class="w-12 h-12 rounded-xl bg-brand-500/10 border border-brand-500/20 text-brand-500 dark:text-brand-400 flex items-center justify-center text-xl mb-6">
                        <i class="bx bx-wallet"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">Simpanan Tabungan Siswa</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 leading-relaxed">Sistem pencatatan setoran dan penarikan tabungan titipan siswa yang akurat untuk brankas internal sekolah.</p>
                </div>
                <div class="pt-6 border-t border-gray-150 dark:border-white/5 mt-6 text-xs text-brand-500 dark:text-brand-400 font-semibold font-mono">MODUL TABUNGAN INTERNAL</div>
            </div>

            <!-- Card 4: Absensi -->
            <div class="glass-card rounded-2xl p-6 flex flex-col justify-between min-h-[300px] scroll-reveal">
                <div>
                    <div class="w-12 h-12 rounded-xl bg-brand-500/10 border border-brand-500/20 text-brand-500 dark:text-brand-400 flex items-center justify-center text-xl mb-6">
                        <i class="bx bx-calendar-check"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">Presensi Harian Siswa & GTK</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 leading-relaxed">Lembar absensi digital harian dengan status Hadir, Sakit, Izin, Alfa, dan Dinas Luar (DL) bagi guru dan tenaga kependidikan.</p>
                </div>
                <div class="pt-6 border-t border-gray-150 dark:border-white/5 mt-6 text-xs text-brand-500 dark:text-brand-400 font-semibold font-mono">MODUL KESISWAAN & GTK</div>
            </div>

            <!-- Card 5: Pajak -->
            <div class="glass-card rounded-2xl p-6 flex flex-col justify-between min-h-[300px] scroll-reveal">
                <div>
                    <div class="w-12 h-12 rounded-xl bg-brand-500/10 border border-brand-500/20 text-brand-500 dark:text-brand-400 flex items-center justify-center text-xl mb-6">
                        <i class="bx bx-calculator"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">Kalkulator Pajak Belanja</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 leading-relaxed">Perhitungan potongan pajak otomatis untuk PPN (11%), PPh 21, PPh 22, dan PPh 23 pada pencatatan belanja operasional sekolah.</p>
                </div>
                <div class="pt-6 border-t border-gray-150 dark:border-white/5 mt-6 text-xs text-brand-500 dark:text-brand-400 font-semibold font-mono">MODUL PERPAJAKAN BOS</div>
            </div>

            <!-- Card 6: Bagan -->
            <div class="glass-card rounded-2xl p-6 flex flex-col justify-between min-h-[300px] scroll-reveal">
                <div>
                    <div class="w-12 h-12 rounded-xl bg-brand-500/10 border border-brand-500/20 text-brand-500 dark:text-brand-400 flex items-center justify-center text-xl mb-6">
                        <i class="bx bx-network-chart"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">Struktur Organisasi Dinamis</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 leading-relaxed">Visualisasi diagram hierarki bagan organisasi sekolah otomatis yang menarik, menghubungkan Kepala Sekolah sampai Guru Pengajar.</p>
                </div>
                <div class="pt-6 border-t border-gray-150 dark:border-white/5 mt-6 text-xs text-brand-500 dark:text-brand-400 font-semibold font-mono">MODUL STRUKTUR ORGANISASI</div>
            </div>

        </div>
    </section>

    <!-- SECTION: LIVE INTERACTIVE PREVIEW -->
    <section id="demo" class="py-20 px-6 max-w-7xl mx-auto border-t border-gray-150 dark:border-white/5 scroll-reveal">
        <div class="text-center mb-12">
            <span class="text-xs font-bold uppercase tracking-wider text-brand-500 dark:text-brand-400">Preview Aplikasi</span>
            <h2 class="text-3xl sm:text-5xl font-bold text-gray-900 dark:text-white tracking-tight mt-2">Dasbor Operasional Terpusat</h2>
            <p class="text-sm sm:text-base text-gray-500 dark:text-gray-400 max-w-lg mx-auto mt-4">Lihat ringkasan visual data siswa, penerimaan SPP, saldo kas BKU, dan tunggakan secara seketika.</p>
        </div>

        <!-- Sleek Web Browser Mockup -->
        <div class="glass-card rounded-2xl overflow-hidden border border-gray-200 dark:border-white/10 shadow-2xl max-w-5xl mx-auto">
            <!-- Browser Header -->
            <div class="bg-gray-100 dark:bg-white/5 px-4 py-3 flex items-center gap-2 border-b border-gray-200 dark:border-white/5">
                <span class="w-3 h-3 rounded-full bg-red-500/80"></span>
                <span class="w-3 h-3 rounded-full bg-yellow-500/80"></span>
                <span class="w-3 h-3 rounded-full bg-green-500/80"></span>
                <div class="bg-gray-200 dark:bg-black/20 text-[10px] text-gray-500 dark:text-gray-400 px-8 py-0.5 rounded-md ml-4 font-mono select-none">https://eduja.sch.id/dashboard</div>
            </div>
            <!-- Mockup Content -->
            <div class="p-6 bg-gray-50 dark:bg-[#0c0c14] grid grid-cols-1 md:grid-cols-4 gap-4 text-left select-none pointer-events-none transition-colors">
                <!-- Mock Cards -->
                <div class="bg-white dark:bg-white/2 p-4 rounded-xl border border-gray-200 dark:border-white/5 transition-colors">
                    <span class="text-[10px] text-gray-400 dark:text-gray-500 uppercase tracking-wider block font-semibold">Siswa Aktif</span>
                    <h5 class="text-lg font-bold text-gray-800 dark:text-white mt-1">1.240 <span class="text-[10px] text-gray-400 font-normal">siswa</span></h5>
                </div>
                <div class="bg-white dark:bg-white/2 p-4 rounded-xl border border-gray-200 dark:border-white/5 transition-colors">
                    <span class="text-[10px] text-gray-400 dark:text-gray-500 uppercase tracking-wider block font-semibold">Kas Masuk (SPP)</span>
                    <h5 class="text-lg font-bold text-green-600 dark:text-green-500 mt-1">Rp 248.500.000</h5>
                </div>
                <div class="bg-white dark:bg-white/2 p-4 rounded-xl border border-gray-200 dark:border-white/5 transition-colors">
                    <span class="text-[10px] text-gray-400 dark:text-gray-500 uppercase tracking-wider block font-semibold">Belanja Operasional</span>
                    <h5 class="text-lg font-bold text-red-500 dark:text-red-400 mt-1">Rp 120.400.000</h5>
                </div>
                <div class="bg-white dark:bg-white/2 p-4 rounded-xl border border-gray-200 dark:border-white/5 transition-colors">
                    <span class="text-[10px] text-gray-400 dark:text-gray-500 uppercase tracking-wider block font-semibold">Saldo Kas BKU</span>
                    <h5 class="text-lg font-bold text-brand-600 dark:text-brand-400 mt-1">Rp 128.100.000</h5>
                </div>
            </div>
        </div>
    </section>

    <!-- SECTION: CALL TO ACTION -->
    <section class="py-24 px-6 text-center relative overflow-hidden border-t border-gray-150 dark:border-white/5">
        <!-- Decoration (Visible in dark mode) -->
        <div class="absolute bottom-0 left-1/2 -translate-x-1/2 w-[600px] h-[300px] bg-brand-500/5 dark:bg-brand-500/10 rounded-full blur-[140px] pointer-events-none"></div>

        <div class="max-w-3xl mx-auto z-10 relative">
            <h2 class="text-3xl sm:text-5xl font-extrabold text-gray-900 dark:text-white tracking-tight leading-tight mb-6">
                Siap memodernisasi manajemen keuangan sekolah Anda?
            </h2>
            <p class="text-gray-500 dark:text-gray-400 max-w-xl mx-auto text-sm sm:text-base leading-relaxed mb-8">
                Tinggalkan pencatatan manual. Lindungi transparansi dana sekolah, percepat pelunasan tagihan, dan selesaikan BKU BOS tanpa lembur.
            </p>
            <a href="{{ route('login') }}" class="inline-flex rounded-full bg-brand-500 px-8 py-3.5 text-center text-sm font-semibold text-white hover:bg-brand-600 transition-all shadow-md">
                Akses Dasbor Demo Sekarang <i class="bx bx-right-arrow-alt align-middle ml-1"></i>
            </a>
        </div>
    </section>

    <!-- FOOTER -->
    <footer class="py-12 border-t border-gray-150 dark:border-white/5 px-6 bg-gray-50 dark:bg-black/40 transition-colors">
        <div class="max-w-7xl mx-auto flex flex-col md:flex-row items-center justify-between gap-6 text-xs text-gray-500">
            <p>&copy; 2026 Eduja System. Hak Cipta Dilindungi Undang-Undang.</p>
            <div class="flex gap-6">
                <a href="#fitur" class="hover:text-gray-800 dark:hover:text-gray-300">Fitur</a>
                <a href="/pricing" class="hover:text-gray-800 dark:hover:text-gray-300">Harga</a>
                <a href="/contact" class="hover:text-gray-800 dark:hover:text-gray-300">Kontak</a>
            </div>
        </div>
    </footer>

    <!-- INTERSECTION OBSERVER REVEAL FALLBACK SCRIPT -->
    <script>
        // Check if browser natively supports scroll-driven animations
        if (!CSS.supports('(animation-timeline: view()) and (animation-range: entry)')) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('active');
                    }
                });
            }, {
                threshold: 0.15
            });

            // Target scroll reveal elements
            document.querySelectorAll('.scroll-reveal').forEach(el => {
                el.classList.add('reveal-fallback');
                observer.observe(el);
            });
        }
    </script>
    
    <!-- Alpine.js theme store integration -->
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.store('theme', {
                init() {
                    const savedTheme = localStorage.getItem('theme');
                    this.theme = savedTheme || systemTheme;
                    this.applyTheme();
                },
                theme: 'light',
                toggle() {
                    this.theme = this.theme === 'light' ? 'dark' : 'light';
                    localStorage.setItem('theme', this.theme);
                    this.applyTheme();
                },
                applyTheme() {
                    if (this.theme === 'dark') {
                        document.documentElement.classList.add('dark');
                    } else {
                        document.documentElement.classList.remove('dark');
                    }
                }
            });
        });
    </script>
</body>
</html>
