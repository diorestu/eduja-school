<!DOCTYPE html>
<html lang="id" class="scroll-smooth" x-data>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Harga Layanan - Eduja</title>
    
    <!-- Meta SEO -->
    <meta name="description" content="Skema harga fleksibel per siswa dari Eduja. Hitung simulasi biaya langganan Basic dan Premium secara langsung.">
    
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
        }
        html.dark ::-webkit-scrollbar-thumb {
            background: #1a1a24;
        }
        html:not(.dark) ::-webkit-scrollbar-thumb {
            background: #e2e8f0;
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

        /* Range Slider Styling */
        input[type="range"] {
            -webkit-appearance: none;
            appearance: none;
            width: 100%;
            height: 6px;
            border-radius: 3px;
            background: #e2e8f0;
            outline: none;
        }
        html.dark input[type="range"] {
            background: #1f2937;
        }
        input[type="range"]::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #006266;
            cursor: pointer;
            transition: transform 0.1s;
        }
        input[type="range"]::-webkit-slider-thumb:hover {
            transform: scale(1.2);
        }
    </style>
</head>
<body class="antialiased" x-data="{ billingCycle: 'monthly', studentCount: 250 }">

    <!-- Header / Navbar -->
    <header class="fixed top-0 left-0 right-0 z-50 glass-nav">
        <div class="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between">
            <a href="/" class="flex items-center gap-2 text-xl font-bold tracking-tight text-gray-900 dark:text-white">
                <span class="w-8 h-8 bg-brand-500 rounded-lg flex items-center justify-center text-white shadow-xs">E</span>
                Eduja
            </a>
            
            <nav class="hidden md:flex items-center gap-8 text-sm font-medium text-gray-600 dark:text-gray-400">
                <a href="/#fitur" class="hover:text-gray-900 dark:hover:text-white transition-colors">Fitur Utama</a>
                <a href="/pricing" class="text-brand-500 dark:text-brand-400 font-semibold transition-colors">Harga</a>
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

    <!-- PRICING HERO -->
    <section class="pt-32 pb-8 px-6 text-center relative overflow-hidden">
        <div class="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[500px] h-[500px] bg-brand-500/5 dark:bg-brand-500/10 rounded-full blur-[120px] pointer-events-none"></div>

        <div class="max-w-3xl mx-auto z-10 relative">
            <h1 class="text-4xl sm:text-6xl font-extrabold tracking-tight gradient-text leading-tight mb-4">
                Skema Biaya Fleksibel
            </h1>
            <p class="text-base sm:text-lg text-gray-500 dark:text-gray-400 max-w-xl mx-auto leading-relaxed mb-8">
                Tanpa biaya lisensi server. Biaya adil dan transparan dihitung langsung dari jumlah siswa aktif Anda.
            </p>

            <!-- Billing Cycle Switcher -->
            <div class="inline-flex items-center justify-center p-1 rounded-full bg-gray-100 dark:bg-white/5 border border-gray-200 dark:border-white/10 mb-8">
                <button @click="billingCycle = 'monthly'" :class="billingCycle === 'monthly' ? 'bg-white text-gray-900 dark:bg-brand-500 dark:text-white shadow-xs' : 'text-gray-500 dark:text-gray-400'" class="rounded-full px-5 py-2 text-xs font-semibold transition-all">
                    Bulanan
                </button>
                <button @click="billingCycle = 'yearly'" :class="billingCycle === 'yearly' ? 'bg-white text-gray-900 dark:bg-brand-500 dark:text-white shadow-xs' : 'text-gray-500 dark:text-gray-400'" class="rounded-full px-5 py-2 text-xs font-semibold transition-all">
                    Tahunan <span class="text-[9px] bg-brand-500/10 text-brand-600 dark:bg-white/20 dark:text-white px-2 py-0.5 rounded-full ml-1 font-mono">Hemat 20%</span>
                </button>
            </div>
        </div>
    </section>

    <!-- INTERACTIVE SLIDER CALCULATOR -->
    <section class="pb-16 px-6 max-w-3xl mx-auto z-10 relative">
        <div class="glass-card rounded-3xl p-6 sm:p-8">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4 mb-6">
                <div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Simulasi Jumlah Siswa</h3>
                    <p class="text-xs text-gray-500">Geser untuk menyesuaikan jumlah siswa sekolah Anda</p>
                </div>
                <div class="bg-brand-500/10 text-brand-600 dark:bg-brand-500/20 dark:text-brand-400 rounded-2xl px-5 py-2 text-center">
                    <span class="text-2xl font-extrabold block leading-none" x-text="studentCount"></span>
                    <span class="text-[9px] uppercase tracking-wider font-semibold font-mono">Siswa Aktif</span>
                </div>
            </div>
            
            <div class="mb-4">
                <input type="range" min="50" max="2500" step="10" x-model.number="studentCount" class="cursor-pointer" />
            </div>
            
            <div class="flex justify-between text-[10px] text-gray-400 font-mono">
                <span>50 Siswa</span>
                <span>1.250 Siswa</span>
                <span>2.500+ Siswa</span>
            </div>
        </div>
    </section>

    <!-- PRICING CARDS -->
    <section class="pb-24 px-6 max-w-5xl mx-auto z-10 relative">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-stretch">
            
            <!-- Tier 1: Basic Plan -->
            <div class="glass-card rounded-3xl p-8 flex flex-col justify-between min-h-[450px]">
                <div>
                    <div class="flex justify-between items-start mb-6">
                        <div>
                            <h3 class="text-2xl font-bold text-gray-900 dark:text-white">Paket Basic</h3>
                            <p class="text-xs text-gray-400 uppercase tracking-wider font-semibold mt-1">Esensial Operasional</p>
                        </div>
                        <div class="text-right">
                            <span class="text-2xl font-extrabold text-brand-500" x-text="billingCycle === 'monthly' ? 'Rp 10.000' : 'Rp 8.000'"></span>
                            <span class="text-[10px] text-gray-500 block">/ siswa / bulan</span>
                        </div>
                    </div>

                    <div class="bg-gray-100/50 dark:bg-white/2 rounded-2xl p-4 mb-6 border border-gray-150 dark:border-white/5">
                        <span class="text-xs text-gray-400 block mb-1">Estimasi Biaya Sekolah Anda:</span>
                        <span class="text-xl font-extrabold text-gray-900 dark:text-white" x-text="new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(studentCount * (billingCycle === 'monthly' ? 10000 : 8000))"></span>
                        <span class="text-xs text-gray-500" x-text="billingCycle === 'monthly' ? '/ bulan' : '/ bulan (ditagih tahunan)'"></span>
                    </div>
                    
                    <ul class="space-y-3.5 text-xs text-gray-600 dark:text-gray-300 border-t border-gray-150 dark:border-white/5 pt-6">
                        <li><i class="bx bx-check text-brand-500 mr-2"></i> Kapasitas Disesuaikan Dinamis</li>
                        <li><i class="bx bx-check text-brand-500 mr-2"></i> Modul Dapodik, Kelas & Rombel</li>
                        <li><i class="bx bx-check text-brand-500 mr-2"></i> Kasir Penerimaan SPP & Kuitansi</li>
                        <li><i class="bx bx-check text-brand-500 mr-2"></i> Pencatatan Belanja & Buku Kas Umum BOS</li>
                        <li><i class="bx bx-check text-brand-500 mr-2"></i> Absensi Kehadiran GTK (Guru/Staf)</li>
                        <li class="opacity-40"><i class="bx bx-x text-red-500 mr-2"></i> Sub-ledger Buku Pembantu Kas/Bank/Pajak</li>
                        <li class="opacity-40"><i class="bx bx-x text-red-500 mr-2"></i> Buku Tabungan Siswa</li>
                    </ul>
                </div>
                <div class="mt-8">
                    <a href="/contact" class="block w-full text-center rounded-full bg-gray-900 text-white dark:bg-white dark:text-black py-3.5 text-xs font-semibold hover:bg-gray-800 dark:hover:bg-gray-100 transition-all">
                        Hubungi Sales & Demo
                    </a>
                </div>
            </div>

            <!-- Tier 2: Premium Plan -->
            <div class="glass-card rounded-3xl p-8 flex flex-col justify-between min-h-[450px] border-brand-500/30 dark:border-brand-500/50 ring-2 ring-brand-500/20">
                <div>
                    <div class="flex justify-between items-start mb-6">
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-2xl font-bold text-gray-900 dark:text-white">Paket Premium</h3>
                                <span class="bg-brand-500/10 text-brand-600 dark:bg-brand-500/20 dark:text-brand-400 text-[9px] font-bold px-2.5 py-0.5 rounded-full uppercase tracking-wider">Lengkap</span>
                            </div>
                            <p class="text-xs text-gray-400 uppercase tracking-wider font-semibold mt-1">Keleluasaan Finansial</p>
                        </div>
                        <div class="text-right">
                            <span class="text-2xl font-extrabold text-brand-500" x-text="billingCycle === 'monthly' ? 'Rp 25.000' : 'Rp 20.000'"></span>
                            <span class="text-[10px] text-gray-500 block">/ siswa / bulan</span>
                        </div>
                    </div>

                    <div class="bg-brand-500/5 dark:bg-brand-500/10 rounded-2xl p-4 mb-6 border border-brand-500/10">
                        <span class="text-xs text-brand-500 dark:text-brand-400 block mb-1">Estimasi Biaya Sekolah Anda:</span>
                        <span class="text-xl font-extrabold text-gray-900 dark:text-white" x-text="new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(studentCount * (billingCycle === 'monthly' ? 25000 : 20000))"></span>
                        <span class="text-xs text-gray-500" x-text="billingCycle === 'monthly' ? '/ bulan' : '/ bulan (ditagih tahunan)'"></span>
                    </div>
                    
                    <ul class="space-y-3.5 text-xs text-gray-600 dark:text-gray-300 border-t border-gray-150 dark:border-white/5 pt-6">
                        <li><i class="bx bx-check text-brand-500 mr-2"></i> Seluruh Modul Esensial (Basic)</li>
                        <li><i class="bx bx-check text-brand-500 mr-2"></i> Buku Pembantu Kas, Bank, & Pajak</li>
                        <li><i class="bx bx-check text-brand-500 mr-2"></i> Modul Presensi Harian Siswa</li>
                        <li><i class="bx bx-check text-brand-500 mr-2"></i> Buku Tabungan Siswa Terintegrasi</li>
                        <li><i class="bx bx-check text-brand-500 mr-2"></i> Bagan Organisasi Visual Dinamis</li>
                        <li><i class="bx bx-check text-brand-500 mr-2"></i> Laporan Keuangan SPP per Kelas & Cetak BKU</li>
                        <li><i class="bx bx-check text-brand-500 mr-2"></i> Prioritas Dukungan Layanan Pemasangan</li>
                    </ul>
                </div>
                <div class="mt-8">
                    <a href="/contact" class="block w-full text-center rounded-full bg-brand-500 py-3.5 text-xs font-semibold text-white hover:bg-brand-600 transition-all shadow-md">
                        Minta Penawaran Resmi
                    </a>
                </div>
            </div>

        </div>
    </section>

    <!-- FOOTER -->
    <footer class="py-12 border-t border-gray-150 dark:border-white/5 px-6 bg-gray-50 dark:bg-black/40 transition-colors">
        <div class="max-w-7xl mx-auto flex flex-col md:flex-row items-center justify-between gap-6 text-xs text-gray-500">
            <p>&copy; 2026 Eduja System. Hak Cipta Dilindungi Undang-Undang.</p>
            <div class="flex gap-6">
                <a href="/#fitur" class="hover:text-gray-800 dark:hover:text-gray-300">Fitur</a>
                <a href="/pricing" class="hover:text-gray-800 dark:hover:text-gray-300">Harga</a>
                <a href="/contact" class="hover:text-gray-800 dark:hover:text-gray-300">Kontak</a>
            </div>
        </div>
    </footer>

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
