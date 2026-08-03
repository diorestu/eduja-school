{{-- Public Footer Partial --}}
<footer class="border-t border-brand-800 bg-brand-900 text-white transition-colors">
    <div class="max-w-7xl mx-auto px-6 py-16">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-12 mb-12">
            {{-- Brand Column --}}
            <div class="md:col-span-1 space-y-4">
                <a href="/" aria-label="Eduja Beranda">
                    <img src="/images/logo/logo-wide-dark.png" alt="EDUJA — Sekolah Jadi Seru" class="mb-[10px] h-[50px] w-auto object-contain">
                </a>
                <p class="text-xs text-white/70 leading-relaxed max-w-[200px]">
                    Platform operasional sekolah yang membantu setiap tim bekerja lebih terarah, kolaboratif, dan berdampak.
                </p>
                <div class="flex items-center gap-3">
                    <a href="https://wa.me/628123456789" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp Eduja"
                       class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 text-white/70 hover:text-white flex items-center justify-center transition-colors">
                        <i class="bx bxl-whatsapp text-lg"></i>
                    </a>
                    <a href="mailto:info@eduja.sch.id" aria-label="Email Eduja"
                       class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 text-white/70 hover:text-white flex items-center justify-center transition-colors">
                        <i class="bx bx-envelope text-lg"></i>
                    </a>
                    <a href="#" aria-label="Instagram Eduja"
                       class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 text-white/70 hover:text-white flex items-center justify-center transition-colors">
                        <i class="bx bxl-instagram text-lg"></i>
                    </a>
                </div>
            </div>

            {{-- Platform Column --}}
            <div class="space-y-3">
                <h4 class="text-xs font-bold uppercase tracking-wider text-white">Platform</h4>
                <ul class="space-y-2">
                    <li><a href="/" class="text-xs text-white/70 hover:text-white transition-colors">Beranda</a></li>
                    <li><a href="/layanan" class="text-xs text-white/70 hover:text-white transition-colors">Layanan Kami</a></li>
                    <li><a href="/pricing" class="text-xs text-white/70 hover:text-white transition-colors">Paket & Harga</a></li>
                    <li><a href="/blog" class="text-xs text-white/70 hover:text-white transition-colors">Blog & Artikel</a></li>
                    <li><a href="/contact" class="text-xs text-white/70 hover:text-white transition-colors">Hubungi Kami</a></li>
                </ul>
            </div>

            {{-- Layanan Column --}}
            <div class="space-y-3">
                <h4 class="text-xs font-bold uppercase tracking-wider text-white">Modul Unggulan</h4>
                <ul class="space-y-2">
                    <li><a href="/layanan#spp" class="text-xs text-white/70 hover:text-white transition-colors">Kasir SPP Digital</a></li>
                    <li><a href="/layanan#bos" class="text-xs text-white/70 hover:text-white transition-colors">BKU Dana BOS</a></li>
                    <li><a href="/layanan#presensi" class="text-xs text-white/70 hover:text-white transition-colors">Presensi Harian</a></li>
                    <li><a href="/layanan#tabungan" class="text-xs text-white/70 hover:text-white transition-colors">Tabungan Siswa</a></li>
                    <li><a href="/layanan#ai" class="text-xs text-white/70 hover:text-white transition-colors">Asisten AI Sekolah</a></li>
                </ul>
            </div>

            {{-- CTA Column --}}
            <div class="space-y-4">
                <h4 class="text-xs font-bold uppercase tracking-wider text-white">Mulai Sekarang</h4>
                <p class="text-xs text-white/70 leading-relaxed">
                    Kenali cara EDUJA membantu sekolah Anda bekerja lebih efektif setiap hari.
                </p>
                <a href="/contact" class="inline-flex items-center gap-2 rounded-full bg-brand-500 text-white px-5 py-2.5 text-xs font-semibold hover:bg-brand-600 transition-all shadow-sm">
                    Jadwalkan Demo Gratis <i class="bx bx-right-arrow-alt"></i>
                </a>
            </div>
        </div>

        {{-- Bottom Bar --}}
        <div class="pt-8 border-t border-white/15 flex flex-col sm:flex-row items-center justify-between gap-4">
            <p class="text-[11px] text-white/60">
                &copy; {{ date('Y') }} Eduja. Hak Cipta Dilindungi Undang-Undang. &nbsp;|&nbsp; Made with <i class="bx bxs-heart text-brand-500 text-xs align-middle"></i> for Indonesian Schools
            </p>
            <div class="flex items-center gap-4 text-[11px] text-white/60">
                <a href="#" class="hover:text-white transition-colors">Kebijakan Privasi</a>
                <a href="#" class="hover:text-white transition-colors">Syarat & Ketentuan</a>
            </div>
        </div>
    </div>
</footer>
