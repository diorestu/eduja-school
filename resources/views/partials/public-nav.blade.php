{{-- Public Navbar Partial --}}
{{-- Pass $activePage variable from parent view to highlight the active link --}}
<header class="fixed top-0 left-0 right-0 z-50 glass-nav" id="site-header">
    <div class="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between">

        {{-- Logo --}}
        <a href="/" class="flex items-center" aria-label="Eduja - Beranda">
            <img src="/images/logo/logo-wide.png" alt="Eduja - Sekolah Makin Seru" class="h-7 sm:h-8 lg:h-9 w-auto object-contain">
        </a>

        {{-- Desktop Nav --}}
        <nav class="hidden md:flex items-center gap-1 text-sm font-medium" aria-label="Navigasi Utama">
            <a href="/" id="nav-home"
               class="px-4 py-2 rounded-full transition-colors {{ ($activePage ?? '') === 'home' ? 'text-brand-600 dark:text-brand-400 bg-brand-500/8 font-semibold' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100/60 dark:hover:bg-white/5' }}">
                Beranda
            </a>
            <a href="/layanan" id="nav-layanan"
               class="px-4 py-2 rounded-full transition-colors {{ ($activePage ?? '') === 'layanan' ? 'text-brand-600 dark:text-brand-400 bg-brand-500/8 font-semibold' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100/60 dark:hover:bg-white/5' }}">
                Layanan
            </a>
            <a href="/pricing" id="nav-harga"
               class="px-4 py-2 rounded-full transition-colors {{ ($activePage ?? '') === 'harga' ? 'text-brand-600 dark:text-brand-400 bg-brand-500/8 font-semibold' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100/60 dark:hover:bg-white/5' }}">
                Harga
            </a>
            <a href="/blog" id="nav-blog"
               class="px-4 py-2 rounded-full transition-colors {{ ($activePage ?? '') === 'blog' ? 'text-brand-600 dark:text-brand-400 bg-brand-500/8 font-semibold' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100/60 dark:hover:bg-white/5' }}">
                Blog
            </a>
            <a href="/contact" id="nav-kontak"
               class="px-4 py-2 rounded-full transition-colors {{ ($activePage ?? '') === 'kontak' ? 'text-brand-600 dark:text-brand-400 bg-brand-500/8 font-semibold' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100/60 dark:hover:bg-white/5' }}">
                Kontak
            </a>
        </nav>

        <div class="flex items-center gap-3">
            {{-- Dark/Light Theme Toggle --}}
            <button @click="$store.theme.toggle()"
                    class="w-9 h-9 flex items-center justify-center rounded-full border border-gray-200 dark:border-white/10 hover:bg-gray-100 dark:hover:bg-white/5 text-gray-700 dark:text-gray-300 transition-colors"
                    aria-label="Toggle Tema">
                <i x-show="$store.theme.theme === 'light'" class="bx bx-moon text-lg"></i>
                <i x-show="$store.theme.theme === 'dark'" class="bx bx-sun text-lg" style="display:none;"></i>
            </button>

            {{-- Login CTA --}}
            <a href="{{ route('login') }}"
               id="btn-masuk-aplikasi"
               class="hidden sm:inline-flex items-center gap-1.5 rounded-full bg-brand-500 text-white px-5 py-2 text-xs font-semibold hover:bg-brand-600 transition-all shadow-sm">
                Masuk Aplikasi <i class="bx bx-right-arrow-alt"></i>
            </a>

            {{-- Mobile Menu Button --}}
            <button id="mobile-menu-btn"
                    class="md:hidden w-9 h-9 flex items-center justify-center rounded-full border border-gray-200 dark:border-white/10 text-gray-700 dark:text-gray-300"
                    aria-label="Buka menu">
                <i class="bx bx-menu text-xl" id="mobile-menu-icon"></i>
            </button>
        </div>
    </div>

    {{-- Mobile Menu Dropdown --}}
    <div id="mobile-menu"
         class="hidden md:hidden border-t border-gray-100 dark:border-white/5 px-6 py-4 space-y-1 glass-nav">
        <a href="/" class="block px-4 py-2.5 rounded-xl text-sm font-medium {{ ($activePage ?? '') === 'home' ? 'text-brand-600 dark:text-brand-400 bg-brand-500/8' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-white/5' }}">Beranda</a>
        <a href="/layanan" class="block px-4 py-2.5 rounded-xl text-sm font-medium {{ ($activePage ?? '') === 'layanan' ? 'text-brand-600 dark:text-brand-400 bg-brand-500/8' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-white/5' }}">Layanan</a>
        <a href="/pricing" class="block px-4 py-2.5 rounded-xl text-sm font-medium {{ ($activePage ?? '') === 'harga' ? 'text-brand-600 dark:text-brand-400 bg-brand-500/8' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-white/5' }}">Harga</a>
        <a href="/blog" class="block px-4 py-2.5 rounded-xl text-sm font-medium {{ ($activePage ?? '') === 'blog' ? 'text-brand-600 dark:text-brand-400 bg-brand-500/8' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-white/5' }}">Blog</a>
        <a href="/contact" class="block px-4 py-2.5 rounded-xl text-sm font-medium {{ ($activePage ?? '') === 'kontak' ? 'text-brand-600 dark:text-brand-400 bg-brand-500/8' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-white/5' }}">Kontak</a>
        <div class="pt-3 border-t border-gray-100 dark:border-white/5">
            <a href="{{ route('login') }}" class="block w-full text-center rounded-full bg-brand-500 text-white px-5 py-2.5 text-sm font-semibold hover:bg-brand-600 transition-all">
                Masuk Aplikasi
            </a>
        </div>
    </div>
</header>

<script>
    // Mobile menu toggle
    document.getElementById('mobile-menu-btn').addEventListener('click', function() {
        const menu = document.getElementById('mobile-menu');
        const icon = document.getElementById('mobile-menu-icon');
        menu.classList.toggle('hidden');
        icon.classList.toggle('bx-menu');
        icon.classList.toggle('bx-x');
    });
</script>
