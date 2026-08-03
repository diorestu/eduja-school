<!DOCTYPE html>
<html lang="id" class="scroll-smooth" x-data>
<head>
    @include('partials.public-head', [
        'title' => 'Blog Eduja — Wawasan & Panduan untuk Pemimpin Pendidikan Indonesia',
        'description' => 'Artikel, panduan, dan insight terkini seputar manajemen sekolah, teknologi pendidikan, keuangan BOS, dan kepemimpinan pendidikan dari tim Eduja.'
    ])
</head>
<body class="antialiased">

    @include('partials.public-nav', ['activePage' => 'blog'])

    {{-- HERO --}}
    <section class="relative pt-28 pb-16 px-6 text-center overflow-hidden">
        <div class="hero-orb hero-orb-1" style="opacity: 0.5;"></div>
        <div class="max-w-4xl mx-auto relative z-10">
            <div class="section-tag mb-6 inline-flex"><i class="bx bx-edit-alt"></i> Blog & Artikel</div>
            <h1 class="text-4xl sm:text-5xl font-extrabold tracking-tight leading-tight mb-5">
                <span class="gradient-text">Wawasan</span>
                <span class="text-gray-900 dark:text-white">untuk Para</span><br>
                <span class="text-gray-900 dark:text-white">Pemimpin Pendidikan.</span>
            </h1>
            <p class="text-base text-gray-500 dark:text-gray-400 max-w-xl mx-auto leading-relaxed">
                Panduan praktis, studi kasus nyata, dan insight terkini untuk kepala sekolah, pengurus yayasan, dan tenaga pendidik yang ingin sekolahnya terus berkembang.
            </p>
        </div>
    </section>

    {{-- KATEGORI FILTER --}}
    <section class="pb-10 px-6" x-data="{ activeTag: 'semua' }">
        <div class="max-w-7xl mx-auto">
            <div class="flex flex-wrap gap-2 justify-center mb-12">
                @foreach(['semua', 'keuangan sekolah', 'kepemimpinan', 'teknologi pendidikan', 'bos & regulasi', 'tips & trik'] as $tag)
                <button
                    @click="activeTag = '{{ $tag }}'"
                    :class="activeTag === '{{ $tag }}' ? 'bg-brand-500 text-white border-brand-500' : 'bg-white dark:bg-white/3 text-gray-600 dark:text-gray-400 border-gray-200 dark:border-white/10 hover:border-brand-500/50 hover:text-brand-500'"
                    class="px-4 py-2 rounded-full text-xs font-semibold border transition-all capitalize">
                    {{ ucfirst($tag) }}
                </button>
                @endforeach
            </div>

            {{-- Featured Article --}}
            <div class="glass-card rounded-3xl overflow-hidden mb-10 scroll-reveal">
                <div class="grid grid-cols-1 lg:grid-cols-2">
                    <div class="bg-gradient-to-br from-brand-500/15 via-brand-500/5 to-orange-500/10 dark:from-brand-500/20 dark:via-brand-500/8 dark:to-orange-500/12 h-64 lg:h-auto flex flex-col items-center justify-center p-12 relative overflow-hidden">
                        <div class="absolute inset-0 opacity-20">
                            <div class="absolute top-8 left-8 w-16 h-16 rounded-full border-2 border-brand-500/40"></div>
                            <div class="absolute bottom-8 right-8 w-24 h-24 rounded-full border-2 border-orange-500/30"></div>
                        </div>
                        <i class="bx bx-trophy text-7xl text-brand-500/40 dark:text-brand-400/40 relative z-10"></i>
                        <span class="mt-4 text-[10px] bg-brand-500/20 text-brand-600 dark:text-brand-400 font-bold px-3 py-1 rounded-full uppercase tracking-wider relative z-10">Artikel Utama</span>
                    </div>
                    <div class="p-8 lg:p-10 flex flex-col justify-center">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-brand-500 dark:text-brand-400 mb-3">Kepemimpinan Pendidikan</span>
                        <h2 class="text-2xl sm:text-3xl font-extrabold text-gray-900 dark:text-white tracking-tight leading-tight mb-4">
                            Dari Analog ke Digital: Panduan Transformasi Manajemen Sekolah untuk Kepala Sekolah
                        </h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400 leading-relaxed mb-6">
                            Transformasi digital bukan sekadar membeli software. Ini tentang mengubah budaya kerja, membangun kepercayaan tim, dan memilih teknologi yang benar-benar sesuai dengan kebutuhan nyata lembaga pendidikan Anda. Panduan komprehensif ini memandu Anda dari awal hingga implementasi.
                        </p>
                        <div class="flex items-center gap-4 mb-6">
                            <div class="w-10 h-10 rounded-full bg-brand-500 flex items-center justify-center text-white text-xs font-bold">AR</div>
                            <div>
                                <p class="text-xs font-bold text-gray-900 dark:text-white">Ahmad Reza, M.Ed.</p>
                                <p class="text-[11px] text-gray-500">Konsultan Pendidikan Eduja &nbsp;·&nbsp; 20 Jul 2026 &nbsp;·&nbsp; 12 menit baca</p>
                            </div>
                        </div>
                        <a href="#" class="inline-flex items-center gap-2 text-sm font-bold text-brand-500 dark:text-brand-400 hover:underline group">
                            Baca Artikel Lengkap <i class="bx bx-right-arrow-alt group-hover:translate-x-1 transition-transform"></i>
                        </a>
                    </div>
                </div>
            </div>

            {{-- Article Grid --}}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @php
                    $articles = [
                        ['tag' => 'Keuangan Sekolah', 'title' => '5 Kesalahan Pencatatan BOS yang Sering Terjadi di Sekolah', 'desc' => 'Pahami kesalahan umum dalam pelaporan Dana BOS dan cara menghindarinya agar audit berjalan lancar dan transparan.', 'author' => 'Siti R.', 'date' => '15 Jul 2026', 'read' => '8 mnt', 'icon' => 'bx-money', 'color' => 'from-orange-500/10 to-red-500/5', 'iconColor' => 'text-orange-500/40'],
                        ['tag' => 'Kepemimpinan', 'title' => '3 Strategi Kepala Sekolah Mengurangi Tunggakan SPP', 'desc' => 'Tiga pendekatan berbasis data yang terbukti berhasil diterapkan sekolah-sekolah mitra Eduja untuk meningkatkan kelancaran pembayaran.', 'author' => 'Budi S.', 'date' => '10 Jul 2026', 'read' => '6 mnt', 'icon' => 'bx-user-pin', 'color' => 'from-brand-500/10 to-teal-500/5', 'iconColor' => 'text-brand-500/40'],
                        ['tag' => 'Teknologi Pendidikan', 'title' => 'Mengapa Sekolah Efektif Memilih Sistem Manajemen Digital', 'desc' => 'Penelitian terbaru menunjukkan sekolah yang menggunakan sistem digital terintegrasi memiliki efisiensi operasional 60% lebih tinggi.', 'author' => 'Dewi M.', 'date' => '5 Jul 2026', 'read' => '7 mnt', 'icon' => 'bx-laptop', 'color' => 'from-blue-500/10 to-purple-500/5', 'iconColor' => 'text-blue-500/40'],
                        ['tag' => 'BOS & Regulasi', 'title' => 'Memahami Komponen BKU Dana BOS: Panduan Lengkap Bendahara', 'desc' => 'Penjelasan detail tentang Buku Kas Umum, Buku Pembantu Kas, Bank, dan Pajak — serta cara mengisinya dengan benar sesuai regulasi.', 'author' => 'Hasan F.', 'date' => '28 Jun 2026', 'read' => '10 mnt', 'icon' => 'bx-book', 'color' => 'from-emerald-500/10 to-green-500/5', 'iconColor' => 'text-emerald-500/40'],
                        ['tag' => 'Tips & Trik', 'title' => 'Tips Meningkatkan Produktivitas Tenaga Administrasi Sekolah', 'desc' => 'Praktik terbaik dari 50+ sekolah dalam mengoptimalkan waktu kerja staf TU dengan bantuan teknologi dan workflow yang tepat.', 'author' => 'Rina W.', 'date' => '20 Jun 2026', 'read' => '5 mnt', 'icon' => 'bx-time', 'color' => 'from-purple-500/10 to-pink-500/5', 'iconColor' => 'text-purple-500/40'],
                        ['tag' => 'Keuangan Sekolah', 'title' => 'Cara Membangun Laporan Keuangan Sekolah yang Transparan', 'desc' => 'Transparansi keuangan meningkatkan kepercayaan orang tua dan yayasan. Panduan ini mengajarkan cara membangun sistem pelaporan yang akuntabel.', 'author' => 'Ahmad R.', 'date' => '15 Jun 2026', 'read' => '9 mnt', 'icon' => 'bx-bar-chart', 'color' => 'from-amber-500/10 to-yellow-500/5', 'iconColor' => 'text-amber-500/40'],
                    ];
                @endphp

                @foreach($articles as $article)
                <a href="#" class="glass-card rounded-2xl overflow-hidden group block scroll-reveal">
                    <div class="bg-gradient-to-br {{ $article['color'] }} h-36 flex items-center justify-center relative overflow-hidden">
                        <i class="bx {{ $article['icon'] }} text-5xl {{ $article['iconColor'] }}"></i>
                    </div>
                    <div class="p-6">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-brand-500 dark:text-brand-400">{{ $article['tag'] }}</span>
                        <h3 class="text-sm font-bold text-gray-900 dark:text-white mt-2 mb-3 leading-snug group-hover:text-brand-500 dark:group-hover:text-brand-400 transition-colors line-clamp-2">{{ $article['title'] }}</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed mb-5 line-clamp-2">{{ $article['desc'] }}</p>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <div class="w-6 h-6 rounded-full bg-brand-500/20 flex items-center justify-center text-[9px] font-bold text-brand-600 dark:text-brand-400">{{ strtoupper(substr($article['author'], 0, 1)) }}</div>
                                <span class="text-[11px] text-gray-500">{{ $article['author'] }}</span>
                            </div>
                            <div class="text-[10px] text-gray-400 flex items-center gap-2">
                                <span>{{ $article['date'] }}</span>
                                <span>·</span>
                                <span>{{ $article['read'] }}</span>
                            </div>
                        </div>
                    </div>
                </a>
                @endforeach
            </div>

            {{-- Load More --}}
            <div class="mt-12 text-center">
                <button class="inline-flex items-center gap-2 rounded-full border border-gray-300 dark:border-white/15 px-8 py-3.5 text-sm font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/5 transition-all">
                    Muat Lebih Banyak Artikel <i class="bx bx-chevron-down"></i>
                </button>
            </div>
        </div>
    </section>

    {{-- NEWSLETTER --}}
    <section class="py-20 px-6 section-divider scroll-reveal">
        <div class="max-w-2xl mx-auto text-center">
            <div class="section-tag mb-5 inline-flex"><i class="bx bx-envelope"></i> Newsletter Eduja</div>
            <h2 class="text-2xl sm:text-3xl font-extrabold text-gray-900 dark:text-white tracking-tight mb-4">
                Dapatkan artikel terbaru langsung di inbox Anda
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-8 leading-relaxed">
                Bergabung dengan 3.000+ pengelola pendidikan yang mendapatkan insight mingguan seputar manajemen sekolah, keuangan BOS, dan teknologi pendidikan terkini.
            </p>
            <form class="flex flex-col sm:flex-row gap-3 max-w-md mx-auto" onsubmit="return false;">
                <input type="email" placeholder="email@sekolah.sch.id"
                       class="flex-1 rounded-full border border-gray-200 dark:border-white/10 bg-white dark:bg-white/3 px-5 py-3 text-sm text-gray-800 dark:text-white placeholder-gray-400 focus:outline-none focus:border-brand-500 transition-all">
                <button type="submit" class="rounded-full bg-brand-500 text-white px-6 py-3 text-sm font-bold hover:bg-brand-600 transition-all shadow-md">
                    Berlangganan
                </button>
            </form>
            <p class="text-[11px] text-gray-400 mt-3">Gratis selamanya. Bisa berhenti kapan saja.</p>
        </div>
    </section>

    @include('partials.public-footer')
    @include('partials.public-scripts')
</body>
</html>
