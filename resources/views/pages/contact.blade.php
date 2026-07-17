<!DOCTYPE html>
<html lang="id" class="scroll-smooth" x-data>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hubungi Kami - Eduja</title>
    
    <!-- Meta SEO -->
    <meta name="description" content="Hubungi tim dukungan Eduja untuk demo aplikasi sekolah gratis atau konsultasi paket langganan.">
    
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
    </style>
</head>
<body class="antialiased">

    <!-- Header / Navbar -->
    <header class="fixed top-0 left-0 right-0 z-50 glass-nav">
        <div class="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between">
            <a href="/" class="flex items-center gap-2 text-xl font-bold tracking-tight text-gray-900 dark:text-white">
                <span class="w-8 h-8 bg-brand-500 rounded-lg flex items-center justify-center text-white shadow-xs">E</span>
                Eduja
            </a>
            
            <nav class="hidden md:flex items-center gap-8 text-sm font-medium text-gray-600 dark:text-gray-400">
                <a href="/#fitur" class="hover:text-gray-900 dark:hover:text-white transition-colors">Fitur Utama</a>
                <a href="/pricing" class="hover:text-gray-900 dark:hover:text-white transition-colors">Harga</a>
                <a href="/contact" class="text-brand-500 dark:text-brand-400 font-semibold transition-colors">Kontak</a>
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

    <!-- CONTACT CONTAINER -->
    <section class="pt-32 pb-24 px-6 max-w-7xl mx-auto z-10 relative">
        <div class="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[500px] h-[500px] bg-brand-500/5 dark:bg-brand-500/10 rounded-full blur-[120px] pointer-events-none"></div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-start relative z-10">
            <!-- Left Info Panel -->
            <div class="lg:col-span-5 space-y-8">
                <div>
                    <h1 class="text-4xl sm:text-5xl font-extrabold tracking-tight gradient-text leading-tight mb-4">
                        Hubungi Tim Eduja
                    </h1>
                    <p class="text-sm sm:text-base text-gray-500 dark:text-gray-400 leading-relaxed">
                        Punya pertanyaan mengenai fitur, integrasi sistem, atau ingin mengajukan demo virtual khusus untuk instansi sekolah Anda? Kami siap membantu.
                    </p>
                </div>

                <div class="space-y-6">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-lg bg-brand-500/10 text-brand-500 flex items-center justify-center text-lg">
                            <i class="bx bxs-phone-call"></i>
                        </div>
                        <div>
                            <span class="text-[10px] text-gray-400 uppercase tracking-wider block font-semibold">WhatsApp Gateway</span>
                            <a href="https://wa.me/628123456789" class="text-sm font-bold text-gray-800 dark:text-white hover:text-brand-500 transition-colors">+62 812-3456-789</a>
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-lg bg-brand-500/10 text-brand-500 flex items-center justify-center text-lg">
                            <i class="bx bxs-envelope"></i>
                        </div>
                        <div>
                            <span class="text-[10px] text-gray-400 uppercase tracking-wider block font-semibold">Email Resmi</span>
                            <a href="mailto:info@eduja.sch.id" class="text-sm font-bold text-gray-800 dark:text-white hover:text-brand-500 transition-colors">info@eduja.sch.id</a>
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-lg bg-brand-500/10 text-brand-500 flex items-center justify-center text-lg">
                            <i class="bx bxs-map"></i>
                        </div>
                        <div>
                            <span class="text-[10px] text-gray-400 uppercase tracking-wider block font-semibold">Kantor Pusat</span>
                            <p class="text-sm font-bold text-gray-800 dark:text-white">Jakarta, Indonesia</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Form Panel -->
            <div class="lg:col-span-7">
                <div class="glass-card rounded-3xl p-8">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-6">Kirim Pesan</h3>
                    
                    @if(session('success'))
                        <div class="mb-6 rounded-xl bg-green-500/10 border border-green-500/20 p-4 text-xs sm:text-sm text-green-600 dark:text-green-400 flex items-center gap-2">
                            <i class="bx bxs-check-circle text-lg"></i> {{ session('success') }}
                        </div>
                    @endif

                    <form action="{{ route('contact.send') }}" method="POST" class="space-y-5">
                        @csrf
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Nama Anda</label>
                                <input type="text" name="name" required placeholder="Masukkan nama"
                                    class="w-full rounded-xl border border-gray-200 dark:border-white/10 bg-white/50 dark:bg-white/2 py-2.5 px-4 text-sm text-gray-800 dark:text-white focus:outline-none focus:border-brand-500 transition-all" />
                            </div>
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Nama Sekolah</label>
                                <input type="text" name="school_name" required placeholder="Masukkan nama sekolah"
                                    class="w-full rounded-xl border border-gray-200 dark:border-white/10 bg-white/50 dark:bg-white/2 py-2.5 px-4 text-sm text-gray-800 dark:text-white focus:outline-none focus:border-brand-500 transition-all" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Email Sekolah</label>
                                <input type="email" name="email" required placeholder="alamat@sekolah.sch.id"
                                    class="w-full rounded-xl border border-gray-200 dark:border-white/10 bg-white/50 dark:bg-white/2 py-2.5 px-4 text-sm text-gray-800 dark:text-white focus:outline-none focus:border-brand-500 transition-all" />
                            </div>
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">No. WhatsApp</label>
                                <input type="tel" name="phone" required placeholder="Contoh: 0812..."
                                    class="w-full rounded-xl border border-gray-200 dark:border-white/10 bg-white/50 dark:bg-white/2 py-2.5 px-4 text-sm text-gray-800 dark:text-white focus:outline-none focus:border-brand-500 transition-all" />
                            </div>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Detail Kebutuhan / Pesan</label>
                            <textarea name="message" rows="4" required placeholder="Tuliskan pesan Anda..."
                                class="w-full rounded-xl border border-gray-200 dark:border-white/10 bg-white/50 dark:bg-white/2 py-2.5 px-4 text-sm text-gray-800 dark:text-white focus:outline-none focus:border-brand-500 transition-all"></textarea>
                        </div>

                        <button type="submit" class="w-full rounded-full bg-brand-500 py-3.5 text-center text-sm font-semibold text-white hover:bg-brand-600 transition-all shadow-md">
                            Kirim Formulir Demo <i class="bx bx-paper-plane align-middle ml-1"></i>
                        </button>
                    </form>
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
