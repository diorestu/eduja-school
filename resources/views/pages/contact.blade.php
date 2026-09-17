<!DOCTYPE html>
<html lang="id" class="scroll-smooth" x-data>
<head>
    @include('partials.public-head', [
        'title' => 'Kontak Eduja — Demo untuk SMP & SMK',
        'description' => 'Hubungi tim Eduja untuk demo dan konsultasi implementasi platform manajemen SMP atau SMK Anda.',
        'keywords' => 'demo aplikasi sekolah, konsultasi sistem sekolah, kontak Eduja, implementasi aplikasi sekolah'
    ])
</head>
<body class="antialiased">

    @include('partials.public-nav', ['activePage' => 'kontak'])

    {{-- HERO --}}
    <section class="relative pt-28 pb-12 px-6 overflow-hidden">
        <div class="hero-orb hero-orb-1" style="opacity: 0.5;"></div>
        <div class="max-w-7xl mx-auto relative z-10">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-start">

                {{-- LEFT INFO PANEL --}}
                <div class="lg:col-span-5 space-y-8 pt-4">
                    <div>
                        <div class="section-tag mb-5 inline-flex"><i class="bx bx-message-dots"></i> Bicara dengan Kami</div>
                        <h1 class="text-4xl sm:text-5xl font-extrabold tracking-tight leading-tight mb-5">
                            <span class="gradient-text">Mari rapikan</span><br>
                            <span class="text-gray-900 dark:text-white">kebutuhan sekolah Anda.</span>
                        </h1>
                        <p class="text-sm sm:text-base text-gray-500 dark:text-gray-400 leading-relaxed">
                            Ceritakan kebutuhan SMP atau SMK Anda. Kami akan menunjukkan modul yang paling relevan, alur implementasinya, dan cara memulai tanpa membuat tim sekolah kewalahan.
                        </p>
                    </div>

                    {{-- Contact Info --}}
                    <div class="space-y-5">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-2xl bg-brand-500/10 border border-brand-500/15 text-brand-500 flex items-center justify-center text-xl shrink-0">
                                <i class="bx bxl-whatsapp"></i>
                            </div>
                            <div>
                                <span class="text-[10px] text-gray-400 uppercase tracking-wider font-bold block mb-0.5">WhatsApp — Respons Cepat</span>
                                <a href="https://wa.me/628179792288" target="_blank" rel="noopener noreferrer"
                                   class="text-sm font-bold text-gray-800 dark:text-white hover:text-brand-500 dark:hover:text-brand-400 transition-colors">
                                    0817 9792 288
                                </a>
                                <span class="text-[10px] text-gray-400 block mt-0.5">Senin–Jumat, 08.00–17.00 WIB</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-2xl bg-brand-500/10 border border-brand-500/15 text-brand-500 flex items-center justify-center text-xl shrink-0">
                                <i class="bx bxs-envelope"></i>
                            </div>
                            <div>
                                <span class="text-[10px] text-gray-400 uppercase tracking-wider font-bold block mb-0.5">Email Resmi</span>
                                <a href="mailto:info@eduja.id"
                                   class="text-sm font-bold text-gray-800 dark:text-white hover:text-brand-500 dark:hover:text-brand-400 transition-colors">
                                    info@eduja.id
                                </a>
                                <span class="text-[10px] text-gray-400 block mt-0.5">Dibalas dalam 1 hari kerja</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-2xl bg-brand-500/10 border border-brand-500/15 text-brand-500 flex items-center justify-center text-xl shrink-0">
                                <i class="bx bxs-map"></i>
                            </div>
                            <div>
                                <span class="text-[10px] text-gray-400 uppercase tracking-wider font-bold block mb-0.5">Kantor Pusat</span>
                                <p class="text-sm font-bold text-gray-800 dark:text-white">Jakarta, Indonesia</p>
                                <span class="text-[10px] text-gray-400 block mt-0.5">Layanan tersedia untuk seluruh Indonesia</span>
                            </div>
                        </div>
                    </div>

                    {{-- Value Props --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @foreach([
                            ['bx-check-shield', 'Demo 100% gratis', 'Tanpa kartu kredit, tanpa kewajiban.'],
                            ['bx-time-five', 'Onboarding cepat', 'Sekolah aktif dalam 3–5 hari kerja.'],
                            ['bx-support', 'Pendampingan penuh', 'Tim kami hadir selama implementasi.'],
                            ['bx-trending-up', 'Hasil terukur', 'Efisiensi operasional meningkat sejak hari pertama.'],
                        ] as $v)
                        <div class="flex items-start gap-3 p-4 glass-card rounded-xl">
                            <i class="bx {{ $v[0] }} text-brand-500 text-lg mt-0.5 shrink-0"></i>
                            <div>
                                <p class="text-xs font-bold text-gray-900 dark:text-white">{{ $v[1] }}</p>
                                <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">{{ $v[2] }}</p>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>

                {{-- RIGHT FORM PANEL --}}
                <div class="lg:col-span-7">
                    <div class="glass-card rounded-3xl p-8 sm:p-10">
                        <div class="mb-6">
                            <h2 class="text-xl font-extrabold text-gray-900 dark:text-white">Jadwalkan Demo atau Kirim Pesan</h2>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">Isi formulir di bawah ini dan tim kami akan menghubungi Anda melalui WhatsApp atau email dalam 1×24 jam kerja.</p>
                        </div>

                        @if(session('success'))
                            <div class="mb-6 rounded-2xl bg-green-500/10 border border-green-500/20 p-5 flex items-start gap-3">
                                <i class="bx bxs-check-circle text-green-500 text-xl shrink-0 mt-0.5"></i>
                                <div>
                                    <p class="text-sm font-bold text-green-700 dark:text-green-400">Formulir Terkirim!</p>
                                    <p class="text-xs text-green-600 dark:text-green-400/80 mt-1">{{ session('success') }}</p>
                                </div>
                            </div>
                        @endif

                        <form action="{{ route('contact.send') }}" method="POST" class="space-y-5">
                            @csrf
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                <div>
                                    <label class="mb-2 block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Nama Lengkap <span class="text-red-400">*</span></label>
                                    <input type="text" name="name" required placeholder="Masukkan nama Anda"
                                           class="w-full rounded-xl border border-gray-200 dark:border-white/10 bg-white/60 dark:bg-white/3 py-3 px-4 text-sm text-gray-800 dark:text-white placeholder-gray-400 transition-all">
                                </div>
                                <div>
                                    <label class="mb-2 block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Jabatan</label>
                                    <select name="role"
                                            class="w-full rounded-xl border border-gray-200 dark:border-white/10 bg-white/60 dark:bg-white/3 py-3 px-4 text-sm text-gray-800 dark:text-gray-300 transition-all">
                                        <option value="">Pilih jabatan Anda</option>
                                        <option>Kepala Sekolah</option>
                                        <option>Pengurus Yayasan</option>
                                        <option>Bendahara Sekolah</option>
                                        <option>Tenaga Administrasi (TU)</option>
                                        <option>Guru / Wali Kelas</option>
                                        <option>Lainnya</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label class="mb-2 block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Nama Lembaga / Sekolah <span class="text-red-400">*</span></label>
                                <input type="text" name="school_name" required placeholder="Contoh: SMP Negeri 5 Bandung / Yayasan Nur Ilmu"
                                       class="w-full rounded-xl border border-gray-200 dark:border-white/10 bg-white/60 dark:bg-white/3 py-3 px-4 text-sm text-gray-800 dark:text-white placeholder-gray-400 transition-all">
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                <div>
                                    <label class="mb-2 block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Email Sekolah <span class="text-red-400">*</span></label>
                                    <input type="email" name="email" required placeholder="admin@sekolah.sch.id"
                                           class="w-full rounded-xl border border-gray-200 dark:border-white/10 bg-white/60 dark:bg-white/3 py-3 px-4 text-sm text-gray-800 dark:text-white placeholder-gray-400 transition-all">
                                </div>
                                <div>
                                    <label class="mb-2 block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">No. WhatsApp <span class="text-red-400">*</span></label>
                                    <input type="tel" name="phone" required placeholder="08xx-xxxx-xxxx"
                                           class="w-full rounded-xl border border-gray-200 dark:border-white/10 bg-white/60 dark:bg-white/3 py-3 px-4 text-sm text-gray-800 dark:text-white placeholder-gray-400 transition-all">
                                </div>
                            </div>

                            <div>
                                <label class="mb-2 block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Kebutuhan / Pesan <span class="text-red-400">*</span></label>
                                <textarea name="message" rows="4" required
                                          placeholder="Ceritakan kondisi sekolah Anda dan apa yang ingin Anda capai bersama Eduja..."
                                          class="w-full rounded-xl border border-gray-200 dark:border-white/10 bg-white/60 dark:bg-white/3 py-3 px-4 text-sm text-gray-800 dark:text-white placeholder-gray-400 transition-all resize-none"></textarea>
                            </div>

                            <div class="flex items-start gap-3">
                                <input type="checkbox" id="agree" name="agree" required class="mt-1 accent-brand-500">
                                <label for="agree" class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed cursor-pointer">
                                    Saya setuju dihubungi oleh tim Eduja melalui WhatsApp atau email untuk keperluan demo dan konsultasi.
                                </label>
                            </div>

                            <button type="submit" id="btn-send-contact"
                                    class="group w-full rounded-full bg-brand-500 py-4 text-center text-sm font-bold text-white hover:bg-brand-600 transition-all shadow-lg shadow-brand-500/25 flex items-center justify-center gap-2">
                                <i class="bx bx-paper-plane text-base group-hover:translate-x-1 transition-transform"></i>
                                Kirim & Jadwalkan Demo Gratis
                            </button>
                            <p class="text-center text-[11px] text-gray-400">🔒 Data Anda aman dan tidak akan disebarluaskan.</p>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- TESTIMONIAL MINI --}}
    <section class="py-16 px-6 section-divider scroll-reveal">
        <div class="max-w-5xl mx-auto text-center">
            <p class="text-xs text-gray-400 uppercase tracking-wider font-bold mb-8">Bergabunglah bersama mereka yang sudah merasakannya</p>
            <div class="flex flex-wrap justify-center gap-3">
                @foreach(['SMA Muhammadiyah Bandung', 'MTs Nurul Huda Surabaya', 'SD Al-Azhar Jakarta', 'SMK Teknik Semarang', 'SMP Yayasan Bina Putra', 'SDIT Pelangi Bali', 'SMA Negeri 3 Makassar', 'MAN 1 Yogyakarta'] as $school)
                <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-white/5 border border-gray-200 dark:border-white/10 px-4 py-2 rounded-full">{{ $school }}</span>
                @endforeach
                <span class="text-xs font-semibold text-brand-500 dark:text-brand-400 bg-brand-500/10 border border-brand-500/20 px-4 py-2 rounded-full">+192 sekolah lainnya...</span>
            </div>
        </div>
    </section>

    @include('partials.public-footer')
    @include('partials.public-scripts')
</body>
</html>
