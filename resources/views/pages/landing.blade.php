<!DOCTYPE html>
<html lang="id" class="scroll-smooth" x-data>
<head>
    @include('partials.public-head', [
        'title' => 'Eduja — Operasional Sekolah, Lebih Ringan',
        'description' => 'Eduja membantu sekolah mengelola akademik, keuangan, presensi, dan komunikasi dalam satu ruang kerja yang mudah digunakan.'
    ])
    <style>
        :root { --cream:#fbf6ed; --ink:#173b3d; --teal:#087f7a; --teal-dark:#06645f; --mint:#d8f1e7; --peach:#f5c8ad; --yellow:#f6d873; --line:#dce5dc; --shadow-card:0 18px 42px rgba(23,59,61,.10); --ease-out:cubic-bezier(.16,1,.3,1); --dur-fast:160ms; }
        body { background:var(--cream)!important; color:var(--ink); font-family: 'Inter', sans-serif; }
        .eduja-display { font-family: Georgia, 'Times New Roman', serif; letter-spacing:-.06em; }
        .landing-nav { background:rgba(251,246,237,.96); border-bottom:1px solid rgba(23,59,61,.08); }
        .paper-grid { background-image:linear-gradient(rgba(8,127,122,.045) 1px,transparent 1px),linear-gradient(90deg,rgba(8,127,122,.045) 1px,transparent 1px); background-size:26px 26px; }
        .blob { border-radius:42% 58% 62% 38% / 45% 38% 62% 55%; }
        .hero-card { box-shadow:var(--shadow-card); transform:rotate(1deg); }
        .hero-card:hover { transform:rotate(0); }
        .feature-card { border:1px solid var(--line); background:rgba(255,255,255,.52); transition:background var(--dur-fast) var(--ease-out),border-color var(--dur-fast) var(--ease-out); }
        .feature-card:hover { background:#fff; border-color:rgba(8,127,122,.28); }
        .sticker { animation:float 5s ease-in-out infinite; }
        .sticker-delay { animation-delay:-2s; }
        @keyframes float { 0%,100%{transform:translateY(0) rotate(-4deg)} 50%{transform:translateY(-9px) rotate(2deg)} }
        @media (prefers-reduced-motion:reduce){.sticker{animation:none}.hero-card,.feature-card{transition:none}}
    </style>
</head>
<body class="antialiased overflow-x-clip">
    @include('partials.public-nav', ['activePage' => 'home'])

    <main>
        <section class="paper-grid relative overflow-hidden px-6 pb-20 pt-36 md:pb-28 md:pt-48">
            <div class="mx-auto grid max-w-6xl items-center gap-14 lg:grid-cols-[.9fr_1.1fr]">
                <div class="relative z-10">
                    <p class="mb-6 text-sm font-black uppercase tracking-[.2em] text-[var(--teal)]">EDUJA — Sekolah Jadi Seru</p>
                    <h1 class="eduja-display max-w-xl text-6xl leading-[.95] text-[var(--ink)] sm:text-7xl lg:text-[88px]">Operasional sekolah<br><em class="text-[var(--teal)]">lebih terarah.</em></h1>
                    <p class="mt-7 max-w-lg text-base leading-8 text-[var(--ink)]/65 sm:text-lg">Satu platform untuk mengelola akademik, keuangan, presensi, dan komunikasi sekolah—dengan informasi yang jelas dan alur kerja yang mudah diikuti.</p>
                    <div class="mt-9 flex flex-col gap-3 sm:flex-row"><a href="/contact" class="rounded-full bg-[var(--teal)] px-6 py-3.5 text-center text-sm font-bold text-white shadow-lg shadow-[var(--teal)]/20 transition hover:-translate-y-0.5">Jadwalkan demo gratis <i class="bx bx-arrow-back bx-rotate-180 ml-1 align-middle"></i></a><a href="#fitur" class="rounded-full border border-[var(--ink)]/20 px-6 py-3.5 text-center text-sm font-bold text-[var(--ink)] transition hover:bg-white/70">Lihat cara Eduja bekerja</a></div>
                    <div class="mt-9 flex items-center gap-3 text-xs font-semibold text-[var(--ink)]/55"><span class="flex -space-x-2"><i class="flex h-8 w-8 items-center justify-center rounded-full border-2 border-[var(--cream)] bg-[#f3b69b] not-italic">KS</i><i class="flex h-8 w-8 items-center justify-center rounded-full border-2 border-[var(--cream)] bg-[#91d4be] not-italic">TU</i><i class="flex h-8 w-8 items-center justify-center rounded-full border-2 border-[var(--cream)] bg-[#f3d27b] not-italic">BK</i></span> Satu ruang kerja untuk kepala sekolah, TU, bendahara, dan guru
                    </div>
                </div>
                <div class="relative mx-auto w-full max-w-xl lg:ml-auto">
                    <div class="blob absolute -right-3 top-2 h-[94%] w-[94%] bg-[var(--mint)]"></div><div class="blob absolute -bottom-7 -left-8 h-32 w-32 bg-[var(--peach)] opacity-80"></div>
                    <div class="hero-card relative z-10 rounded-[30px] border-[10px] border-white bg-white p-4 transition duration-500 sm:p-6">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-4"><div><p class="text-[10px] font-bold uppercase tracking-widest text-[var(--teal)]">Contoh tampilan produk</p><h2 class="mt-1 text-lg font-black text-slate-800">Ringkasan sekolah</h2></div><span class="text-xs font-semibold text-slate-400">Dashboard</span></div>
                        <div class="mt-5 grid grid-cols-2 gap-3"><div class="rounded-2xl bg-[#e9f6ee] p-4"><i class="bx bx-group text-xl text-[var(--teal)]"></i><p class="mt-3 text-[11px] font-semibold text-slate-500">Siswa aktif</p><strong class="text-2xl text-slate-800">1.248</strong></div><div class="rounded-2xl bg-[#fff4dc] p-4"><i class="bx bx-wallet text-xl text-[#c48814]"></i><p class="mt-3 text-[11px] font-semibold text-slate-500">SPP bulan ini</p><strong class="text-2xl text-slate-800">92<span class="text-base">%</span></strong></div></div>
                        <div class="mt-3 rounded-2xl bg-slate-50 p-4"><div class="flex justify-between text-xs font-bold text-slate-600"><span>Kehadiran siswa</span><span class="text-[var(--teal)]">98,4%</span></div><div class="mt-3 h-3 overflow-hidden rounded-full bg-slate-200"><div class="h-full w-[98%] rounded-full bg-[var(--teal)]"></div></div><div class="mt-5 flex items-end gap-2" style="height:64px"><i class="h-[38%] flex-1 rounded-t-lg bg-[#b9e1d3]"></i><i class="h-[55%] flex-1 rounded-t-lg bg-[#8bcdb7]"></i><i class="h-[48%] flex-1 rounded-t-lg bg-[#b9e1d3]"></i><i class="h-[76%] flex-1 rounded-t-lg bg-[var(--teal)]"></i><i class="h-[64%] flex-1 rounded-t-lg bg-[#8bcdb7]"></i><i class="h-[88%] flex-1 rounded-t-lg bg-[var(--teal)]"></i></div></div>
                        <div class="mt-3 flex items-center gap-3 rounded-2xl border border-[#f3ddd1] bg-[#fff9f4] p-3"><span class="flex h-9 w-9 items-center justify-center rounded-xl bg-[var(--peach)] text-lg"><i class="bx bx-check-circle text-[var(--teal)]" aria-hidden="true"></i></span><p class="text-[11px] font-semibold leading-4 text-slate-600">Ringkasan kerja hari ini<br><b class="text-slate-800">siap ditinjau tim sekolah.</b></p></div>
                    </div>
                </div>
            </div>
        </section>

        <section id="fitur" class="px-6 py-24"><div class="mx-auto max-w-6xl"><div class="max-w-2xl"><p class="text-xs font-black uppercase tracking-[.2em] text-[var(--teal)]">Yang dikerjakan EDUJA</p><h2 class="eduja-display mt-4 text-5xl leading-none sm:text-6xl">Satu ruang kerja,<br>alur yang lebih jelas.</h2></div><div class="mt-14 grid gap-4 md:grid-cols-2 lg:grid-cols-4">@foreach([['icon'=>'bx-group','title'=>'Data sekolah','desc'=>'Siswa, guru, kelas, dan tahun ajaran tersusun dalam satu konteks sekolah.'],['icon'=>'bx-wallet','title'=>'Keuangan','desc'=>'Pantau SPP, BOS, BKU, approval, dan saldo dari data yang sama.'],['icon'=>'bx-calendar-check','title'=>'Presensi','desc'=>'Catat kehadiran siswa, guru, dan tendik lalu lihat rekapnya.'],['icon'=>'bx-message-rounded-dots','title'=>'Koordinasi','desc'=>'Bagikan pengumuman dan ikuti pekerjaan yang masih menunggu tindakan.']] as $feature)<article class="feature-card rounded-[20px] p-6"><div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[var(--mint)] text-2xl text-[var(--teal)]"><i class="bx {{ $feature['icon'] }}" aria-hidden="true"></i></div><h3 class="mt-6 text-base font-black">{{ $feature['title'] }}</h3><p class="mt-2 text-sm leading-6 text-[var(--ink)]/60">{{ $feature['desc'] }}</p></article>@endforeach</div></div></section>

        <section id="cara-kerja" class="bg-[var(--ink)] px-6 py-24 text-white"><div class="mx-auto grid max-w-6xl gap-12 lg:grid-cols-[.8fr_1.2fr] lg:items-center"><div><p class="text-xs font-black uppercase tracking-[.2em] text-[var(--yellow)]">Mulai dengan nyaman</p><h2 class="eduja-display mt-4 text-5xl leading-none sm:text-6xl">Dari kenal,<br>jadi terbiasa.</h2><p class="mt-6 max-w-md leading-7 text-white/60">Kami menemani sekolah dari demo sampai sistem benar-benar dipakai oleh tim setiap hari.</p></div><div class="grid gap-3 sm:grid-cols-3">@foreach([['n'=>'01','t'=>'Kenali kebutuhan','d'=>'Cerita dulu tentang ritme sekolah Anda.'],['n'=>'02','t'=>'Atur bersama','d'=>'Data dan alur kerja disiapkan dengan rapi.'],['n'=>'03','t'=>'Jalankan harian','d'=>'Tim siap bekerja lebih ringan bersama Eduja.']] as $step)<div class="rounded-[24px] border border-white/10 bg-white/5 p-5"><span class="text-3xl font-black text-[var(--yellow)]">{{ $step['n'] }}</span><h3 class="mt-10 font-black">{{ $step['t'] }}</h3><p class="mt-2 text-sm leading-6 text-white/55">{{ $step['d'] }}</p></div>@endforeach</div></div></section>

        <section class="px-6 py-24"><div class="mx-auto flex max-w-4xl flex-col items-center rounded-[36px] bg-[var(--mint)] px-7 py-14 text-center"><h2 class="eduja-display mt-4 text-5xl leading-none sm:text-6xl">Saat sistemnya jelas,<br>sekolah bisa melangkah lebih jauh.</h2><p class="mt-5 max-w-lg text-sm leading-7 text-[var(--ink)]/65">Temukan bagaimana EDUJA membantu tim sekolah bekerja lebih efektif, transparan, dan fokus pada pendidikan.</p><a href="/contact" class="mt-8 rounded-full bg-[var(--teal)] px-7 py-3.5 text-sm font-bold text-white transition hover:bg-[#06645f]">Jadwalkan konsultasi <i class="bx bx-right-arrow-alt ml-1"></i></a></div></section>
    </main>
    @include('partials.public-footer')
    @include('partials.public-scripts')
</body>
</html>
